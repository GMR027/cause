<?php

declare(strict_types=1);

namespace Cauce\Helpers;

/**
 * Validación básica de formularios con mensajes traducidos.
 */
final class Validator
{
    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, ?string $label = null): self
    {
        if ($this->stringValue($field) === '') {
            $this->add('common.validation.required', $field, $label);
        }

        return $this;
    }

    public function email(string $field, ?string $label = null): self
    {
        $value = $this->stringValue($field);

        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->add('common.validation.email', $field, $label);
        }

        return $this;
    }

    public function minLength(string $field, int $min, ?string $label = null): self
    {
        $value = $this->stringValue($field);

        if ($value !== '' && mb_strlen($value) < $min) {
            $this->add('common.validation.min', $field, $label, [':min' => (string) $min]);
        }

        return $this;
    }

    public function maxLength(string $field, int $max, ?string $label = null): self
    {
        $value = $this->stringValue($field);

        if ($value !== '' && mb_strlen($value) > $max) {
            $this->add('common.validation.max', $field, $label, [':max' => (string) $max]);
        }

        return $this;
    }

    public function regex(string $field, string $pattern, ?string $label = null, string $messageKey = 'common.validation.regex'): self
    {
        $value = $this->stringValue($field);

        if ($value !== '' && preg_match($pattern, $value) !== 1) {
            $this->add($messageKey, $field, $label);
        }

        return $this;
    }

    /**
     * @param list<string> $allowed
     */
    public function in(string $field, array $allowed, ?string $label = null): self
    {
        $value = $this->stringValue($field);

        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->add('common.validation.in', $field, $label);
        }

        return $this;
    }

    public function confirmed(string $field, string $confirmationField, ?string $label = null): self
    {
        if ($this->stringValue($field) !== $this->stringValue($confirmationField)) {
            $this->add('common.validation.confirmed', $field, $label);
        }

        return $this;
    }

    public function password(string $field, ?string $label = null): self
    {
        $value = $this->stringValue($field);
        $min = (int) config('auth.password.min_length', 8);
        $max = (int) config('auth.password.max_length', 255);

        if ($value === '') {
            $this->add('common.validation.required', $field, $label);
            return $this;
        }

        if (mb_strlen($value) < $min
            || mb_strlen($value) > $max
            || preg_match('/[0-9]/', $value) !== 1
            || preg_match('/[^A-Za-z0-9]/', $value) !== 1
        ) {
            $this->add('common.validation.password', $field, $label);
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<string, string> $extra
     */
    private function add(string $key, string $field, ?string $label, array $extra = []): void
    {
        $label ??= __('common.fields.' . $field);

        $this->errors[] = __($key, array_merge([
            ':field' => $label,
        ], $extra));
    }

    private function stringValue(string $field): string
    {
        $value = $this->data[$field] ?? '';

        return is_string($value) ? trim($value) : (is_scalar($value) ? trim((string) $value) : '');
    }
}
