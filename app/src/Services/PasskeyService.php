<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\PasskeyRepository;
use RuntimeException;

/**
 * Llaves de acceso (WebAuthn): entrar con huella, rostro o el bloqueo de
 * pantalla del dispositivo.
 *
 * Sin atestación ("none"): se guarda la llave pública que entrega el
 * navegador (getPublicKey(), SPKI) y en cada acceso se verifica la firma
 * sobre authenticatorData + SHA-256(clientDataJSON). Se exige verificación
 * del usuario (UV), así que el teléfono pide huella, rostro o PIN.
 */
final class PasskeyService
{
    /** ES256 y RS256: los que usan Android, iOS, Windows Hello y macOS. */
    public const ALGORITHMS = [-7, -257];

    private const TTL = 300;

    private const FLAG_UP = 0x01;

    private const FLAG_UV = 0x04;

    private const FLAG_AT = 0x40;

    private PasskeyRepository $repo;

    public function __construct(?PasskeyRepository $repo = null)
    {
        $this->repo = $repo ?? new PasskeyRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function registrationOptions(array $user): array
    {
        $userId = (int) $user['id'];
        $handle = $this->repo->handleFor($userId) ?? self::b64(random_bytes(32));
        $challenge = self::b64(random_bytes(32));
        $_SESSION['_passkey_create'] = [
            'challenge' => $challenge,
            'handle' => $handle,
            'user_id' => $userId,
            'expires' => time() + self::TTL,
        ];

        return [
            'challenge' => $challenge,
            'rp' => ['id' => $this->rpId(), 'name' => 'Cauce'],
            'user' => [
                'id' => $handle,
                'name' => (string) $user['email'],
                'displayName' => (string) ($user['alias'] ?? $user['email']),
            ],
            'pubKeyCredParams' => array_map(
                static fn (int $alg): array => ['type' => 'public-key', 'alg' => $alg],
                self::ALGORITHMS
            ),
            'timeout' => self::TTL * 1000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey' => 'required',
                'requireResidentKey' => true,
                'userVerification' => 'required',
            ],
            'excludeCredentials' => array_map(
                static fn (array $row): array => ['type' => 'public-key', 'id' => (string) $row['credencial_id']],
                $this->repo->forUser($userId)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $data credential_id, client_data, authenticator_data, public_key, algorithm, name
     */
    public function register(array $user, array $data): int
    {
        $state = $this->takeState('_passkey_create');

        if ((int) ($state['user_id'] ?? 0) !== (int) $user['id']) {
            throw new RuntimeException('expired');
        }

        $clientRaw = self::unb64((string) ($data['client_data'] ?? ''));
        $this->checkClientData($clientRaw, 'webauthn.create', (string) $state['challenge']);

        $authData = self::unb64((string) ($data['authenticator_data'] ?? ''));
        $flags = $this->checkAuthData($authData);

        if (($flags & self::FLAG_AT) === 0 || strlen($authData) < 55) {
            throw new RuntimeException('invalid');
        }

        $credentialLength = unpack('n', substr($authData, 53, 2))[1];
        $credentialRaw = substr($authData, 55, $credentialLength);
        $credentialId = (string) ($data['credential_id'] ?? '');

        if ($credentialLength < 16 || $credentialLength > 1023 || !hash_equals(self::b64($credentialRaw), $credentialId)) {
            throw new RuntimeException('invalid');
        }

        $algorithm = (int) ($data['algorithm'] ?? 0);
        $pem = $this->publicKeyPem(self::unb64((string) ($data['public_key'] ?? '')), $algorithm);

        if ($this->repo->findByCredential($credentialId) !== null) {
            throw new RuntimeException('duplicate');
        }

        $name = trim((string) ($data['name'] ?? ''));

        return $this->repo->create(
            (int) $user['id'],
            $credentialId,
            $pem,
            $algorithm,
            (string) $state['handle'],
            $this->counter($authData),
            mb_substr($name !== '' ? $name : __('auth.passkeys.default_name'), 0, 80)
        );
    }

    /**
     * Opciones para entrar o desbloquear. Al desbloquear solo valen las
     * llaves de esa persona; al entrar, el dispositivo ofrece las suyas.
     *
     * @return array<string, mixed>
     */
    public function assertionOptions(?int $userId = null): array
    {
        $challenge = self::b64(random_bytes(32));
        $_SESSION['_passkey_get'] = [
            'challenge' => $challenge,
            'user_id' => $userId,
            'expires' => time() + self::TTL,
        ];

        $allow = [];

        if ($userId !== null) {
            foreach ($this->repo->forUser($userId) as $row) {
                $allow[] = ['type' => 'public-key', 'id' => (string) $row['credencial_id']];
            }
        }

        return [
            'challenge' => $challenge,
            'rpId' => $this->rpId(),
            'timeout' => self::TTL * 1000,
            'userVerification' => 'required',
            'allowCredentials' => $allow,
        ];
    }

    /**
     * Verifica la firma y devuelve el id de la persona dueña de la llave.
     *
     * @param array<string, string> $data credential_id, client_data, authenticator_data, signature, user_handle
     */
    public function verify(array $data): int
    {
        $state = $this->takeState('_passkey_get');
        $credential = $this->repo->findByCredential((string) ($data['credential_id'] ?? ''));

        if ($credential === null) {
            throw new RuntimeException('unknown');
        }

        if ($state['user_id'] !== null && (int) $state['user_id'] !== (int) $credential['user_id']) {
            throw new RuntimeException('unknown');
        }

        $handle = (string) ($data['user_handle'] ?? '');

        if ($handle !== '' && !hash_equals((string) $credential['usuario_handle'], $handle)) {
            throw new RuntimeException('invalid');
        }

        $clientRaw = self::unb64((string) ($data['client_data'] ?? ''));
        $this->checkClientData($clientRaw, 'webauthn.get', (string) $state['challenge']);

        $authData = self::unb64((string) ($data['authenticator_data'] ?? ''));
        $this->checkAuthData($authData);

        $signature = self::unb64((string) ($data['signature'] ?? ''));
        $ok = openssl_verify(
            $authData . hash('sha256', $clientRaw, true),
            $signature,
            (string) $credential['llave_publica'],
            OPENSSL_ALGO_SHA256
        );

        if ($ok !== 1) {
            throw new RuntimeException('invalid');
        }

        // Muchas llaves sincronizadas siempre mandan 0; si cuentan, debe subir.
        $counter = $this->counter($authData);
        $stored = (int) $credential['contador'];

        if (($counter > 0 || $stored > 0) && $counter <= $stored) {
            throw new RuntimeException('cloned');
        }

        $this->repo->markUsed((int) $credential['id'], $counter);

        return (int) $credential['user_id'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->repo->forUser($userId);
    }

    public function hasAny(int $userId): bool
    {
        return $this->repo->countFor($userId) > 0;
    }

    public function delete(int $userId, int $id): bool
    {
        return $this->repo->delete($id, $userId);
    }

    public function rpId(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        if ($host === '') {
            $host = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));
        }

        // Sin puerto; IPv6 entre corchetes no aplica a WebAuthn.
        return preg_replace('/:\d+$/', '', $host) ?? $host;
    }

    /**
     * @return array<string, mixed>
     */
    private function takeState(string $key): array
    {
        $state = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        if (!is_array($state) || (int) ($state['expires'] ?? 0) < time() || !is_string($state['challenge'] ?? null)) {
            throw new RuntimeException('expired');
        }

        return $state;
    }

    private function checkClientData(string $raw, string $type, string $challenge): void
    {
        $client = json_decode($raw, true);

        if (!is_array($client) || ($client['type'] ?? null) !== $type
            || !is_string($client['challenge'] ?? null)
            || !hash_equals($challenge, rtrim((string) $client['challenge'], '='))
        ) {
            throw new RuntimeException('invalid');
        }

        $origin = is_string($client['origin'] ?? null) ? (string) $client['origin'] : '';

        // Dentro de la app Android el origen es la huella del certificado de firma.
        if ($origin !== '' && hash_equals($this->androidOrigin(), $origin)) {
            return;
        }

        $scheme = strtolower((string) parse_url($origin, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $local = in_array($host, ['localhost', '127.0.0.1'], true);

        if ($host !== $this->rpId() || !($scheme === 'https' || ($scheme === 'http' && $local))) {
            throw new RuntimeException('origin');
        }

        if (!empty($client['crossOrigin'])) {
            throw new RuntimeException('origin');
        }
    }

    private function androidOrigin(): string
    {
        $hex = preg_replace('/[^0-9a-f]/i', '', (string) config('security.android.cert_sha256', '')) ?? '';
        $raw = strlen($hex) === 64 ? hex2bin($hex) : false;

        return $raw === false ? "\0" : 'android:apk-key-hash:' . self::b64($raw);
    }

    private function checkAuthData(string $authData): int
    {
        if (strlen($authData) < 37 || !hash_equals(hash('sha256', $this->rpId(), true), substr($authData, 0, 32))) {
            throw new RuntimeException('invalid');
        }

        $flags = ord($authData[32]);

        if (($flags & self::FLAG_UP) === 0 || ($flags & self::FLAG_UV) === 0) {
            throw new RuntimeException('no_uv');
        }

        return $flags;
    }

    private function counter(string $authData): int
    {
        return (int) unpack('N', substr($authData, 33, 4))[1];
    }

    private function publicKeyPem(string $der, int $algorithm): string
    {
        if ($der === '' || !in_array($algorithm, self::ALGORITHMS, true)) {
            throw new RuntimeException('unsupported');
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw new RuntimeException('unsupported');
        }

        $details = openssl_pkey_get_details($key);
        $type = is_array($details) ? (int) ($details['type'] ?? -1) : -1;
        $expected = $algorithm === -7 ? OPENSSL_KEYTYPE_EC : OPENSSL_KEYTYPE_RSA;

        if ($type !== $expected || ($algorithm === -7 && ($details['ec']['curve_name'] ?? '') !== 'prime256v1')) {
            throw new RuntimeException('unsupported');
        }

        return $pem;
    }

    public static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function unb64(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $value) !== 1) {
            return '';
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
