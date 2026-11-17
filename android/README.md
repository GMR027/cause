# Cauce para Android (Rust + Tauri 2)

Envoltura ligera hacia la plataforma web: una pantalla local (`ui/index.html`) revisa
`GET /app/android/version.json`, ofrece actualizar si hay una versión mayor y luego abre el sitio en
el WebView pasando por `/app/android/entrar?v=…&id=…` (cookie `cauce_android` con la versión
instalada → tabla `dispositivos_android`, visible para admin en Ajustes → Android).

## Requisitos (ya instalados en este equipo)
Rust + `rustup target add aarch64-linux-android`, `cargo install tauri-cli --version "^2"`,
Android SDK (`~/Android/Sdk`), NDK 27, JDK 17.

## Compilar y publicar
1. Sube `"version"` en `src-tauri/tauri.conf.json` (Android exige un número mayor para actualizar).
2. `./compilar.sh` → `dist/cauce-android_X.Y.Z.apk` firmado (arm64). `--instalar` lo instala por ADB.
3. En el servidor (o en el pod): `php bin/publicar_android.php <apk> X.Y.Z "notas"`. Copia el APK a
   `storage/android/` (volumen persistente) y lo registra en `app_android_versiones`.

**Llave de firma:** `~/.config/cauce-android/` (fuera del repo y fuera de la imagen Docker:
`android/` está en `.dockerignore`). Respáldala: sin ella no se pueden publicar actualizaciones
que se instalen sobre la app existente.

## Desarrollo en el teléfono
`adb connect IP:PUERTO`, `./compilar.sh --instalar`. Para probar contra el servidor local
(`php -S 127.0.0.1:8011 -t public public/router.php`): `adb reverse tcp:8011 tcp:8011` y en la app
«Cambiar servidor» → `http://localhost:8011` (HTTP plano solo hacia localhost; todo lo demás exige HTTPS).

## Pruebas
`cargo test --manifest-path src-tauri/pruebas/Cargo.toml` prueba `src/politica.rs` (validación del
servidor, política de navegación y descarga de actualizaciones) sin compilar Tauri ni WebKit.

## Qué hace el código nativo
- `src-tauri/src/lib.rs`: ventana con política de navegación (solo el servidor elegido dentro de la
  app; mapas, WhatsApp, tel:, mailto: y sitios externos se abren con la app del sistema, desde otro
  hilo) y comandos `fijar_servidor` y `abrir_actualizacion`. Las páginas del servidor no tienen
  acceso a Rust (`capabilities/default.json`: `local: true`, solo `core:app:allow-version`).
- `gen/android/.../MainActivity.kt`: márgenes de borde a borde (Android 15+) y descargas
  (PDF/Excel/CSV) al gestor de descargas con la cookie de sesión.
- `gen/android/app/build.gradle.kts`: firma de publicación; `res/xml/network_security_config.xml`.

## Ícono
`src-tauri/icons/fuente.png` sale de `public/assets/img/icon-source.png` con margen para el ícono
adaptativo. Para regenerarlo: `cd src-tauri && cargo tauri icon icons/fuente.png` y devuelve
`ic_launcher_background` a `#137A66` (el comando lo reescribe en blanco).
