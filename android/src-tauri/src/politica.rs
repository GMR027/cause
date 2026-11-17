//! Reglas puras (sin Tauri) de la app: qué servidor se acepta y qué se abre dentro de la app.
//! Viven aparte para poder probarlas en cualquier equipo:
//!   cargo test --manifest-path pruebas/Cargo.toml   (desde android/src-tauri)

use url::Url;

/// Un servidor válido: HTTPS siempre; HTTP solo hacia localhost (pruebas con `adb reverse`;
/// la configuración de red de Android bloquea cualquier otro HTTP plano).
pub fn origen_valido(texto: &str) -> Result<String, String> {
    let url = Url::parse(texto.trim()).map_err(|_| "La dirección no es válida.".to_string())?;
    let host = url.host_str().ok_or("La dirección no tiene servidor.")?.to_string();
    match url.scheme() {
        "https" => {}
        "http" if es_local(&host) => {}
        _ => return Err("Usa una dirección https:// (http solo se permite para localhost).".into()),
    }
    Ok(url.origin().ascii_serialization())
}

fn es_local(host: &str) -> bool {
    host == "localhost" || host == "127.0.0.1"
}

/// ¿Se abre dentro de la app? La pantalla local, el servidor de confianza y recursos internos.
pub fn navegacion_interna(url: &Url, servidor: Option<&str>) -> bool {
    match url.scheme() {
        "about" | "data" | "blob" | "tauri" | "asset" => return true,
        "http" | "https" => {}
        _ => return false,
    }
    if url.host_str() == Some("tauri.localhost") {
        return true;
    }
    servidor.is_some_and(|s| url.origin().ascii_serialization() == s)
}

/// La actualización solo se descarga del servidor de confianza y de su ruta de descarga.
pub fn descarga_permitida(texto: &str, servidor: Option<&str>) -> Result<Url, String> {
    let destino = Url::parse(texto).map_err(|_| "Dirección de descarga inválida.".to_string())?;
    if servidor != Some(destino.origin().ascii_serialization().as_str()) || destino.path() != "/app/android/descargar" {
        return Err("La descarga no viene del servidor de confianza.".into());
    }
    Ok(destino)
}

#[cfg(test)]
mod pruebas {
    use super::*;

    #[test]
    fn servidores_permitidos() {
        assert_eq!(origen_valido("https://cause.iguzman.com.mx/").unwrap(), "https://cause.iguzman.com.mx");
        assert_eq!(origen_valido("http://localhost:8011").unwrap(), "http://localhost:8011");
        assert!(origen_valido("http://192.168.1.10:8017").is_err());
        assert!(origen_valido("http://ejemplo.com").is_err());
        assert!(origen_valido("ftp://192.168.1.10").is_err());
        assert!(origen_valido("no es url").is_err());
    }

    #[test]
    fn politica_de_navegacion() {
        let s = Some("https://cause.iguzman.com.mx");
        let u = |t: &str| Url::parse(t).unwrap();
        assert!(navegacion_interna(&u("https://cause.iguzman.com.mx/admin"), s));
        assert!(navegacion_interna(&u("http://tauri.localhost/index.html"), s));
        assert!(!navegacion_interna(&u("https://www.google.com/maps?q=1,2"), s));
        assert!(!navegacion_interna(&u("https://cause.iguzman.com.mx.evil.com/"), s));
        assert!(!navegacion_interna(&u("tel:5551234567"), s));
        assert!(!navegacion_interna(&u("https://cause.iguzman.com.mx/"), None));
    }

    #[test]
    fn descarga_de_actualizacion() {
        let s = Some("https://cause.iguzman.com.mx");
        assert!(descarga_permitida("https://cause.iguzman.com.mx/app/android/descargar", s).is_ok());
        assert!(descarga_permitida("https://cause.iguzman.com.mx/otra/ruta", s).is_err());
        assert!(descarga_permitida("https://evil.com/app/android/descargar", s).is_err());
        assert!(descarga_permitida("http://cause.iguzman.com.mx/app/android/descargar", s).is_err());
        assert!(descarga_permitida("https://cause.iguzman.com.mx/app/android/descargar", None).is_err());
    }
}
