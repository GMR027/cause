//! Cauce para Android (Tauri 2).
//!
//! La app es una ventana hacia la plataforma web: la pantalla local `ui/index.html` revisa la
//! versión publicada en el servidor y después abre el sitio dentro del WebView. Este módulo:
//!   • crea la ventana principal con una política de navegación: solo el servidor elegido y la
//!     pantalla local se abren dentro de la app; cualquier otro enlace (mapas, WhatsApp, tel:,
//!     correo, sitios externos) se abre con la aplicación del sistema;
//!   • expone `fijar_servidor`, que la pantalla local llama antes de navegar, para que la política
//!     sepa cuál es el servidor de confianza.
//! Las páginas del servidor NO tienen acceso a los comandos de Rust (la capacidad es solo local).

use std::sync::Mutex;

use tauri::{Manager, WebviewUrl, WebviewWindowBuilder};
use tauri_plugin_opener::OpenerExt;
use url::Url;

mod politica;
use politica::{descarga_permitida, navegacion_interna, origen_valido};

/// Origen (esquema://host:puerto) del servidor de confianza.
#[derive(Default)]
struct Servidor(Mutex<Option<String>>);

/// La pantalla local avisa a qué servidor va a entrar (se valida aquí, no en JavaScript).
#[tauri::command]
fn fijar_servidor(url: String, estado: tauri::State<'_, Servidor>) -> Result<String, String> {
    let origen = origen_valido(&url)?;
    *estado.0.lock().unwrap() = Some(origen.clone());
    Ok(origen)
}

/// Abre la descarga del APK nuevo con el navegador del sistema (Android pide confirmar la
/// instalación). Solo acepta la ruta de descarga del servidor de confianza.
#[tauri::command]
fn abrir_actualizacion(url: String, app: tauri::AppHandle, estado: tauri::State<'_, Servidor>) -> Result<(), String> {
    let servidor = estado.0.lock().unwrap().clone();
    let destino = descarga_permitida(&url, servidor.as_deref())?;
    app.opener().open_url(destino.as_str(), None::<&str>).map_err(|e| e.to_string())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .manage(Servidor::default())
        .invoke_handler(tauri::generate_handler![fijar_servidor, abrir_actualizacion])
        .setup(|app| {
            let handle = app.handle().clone();
            let builder = WebviewWindowBuilder::new(app, "main", WebviewUrl::App("index.html".into()))
                .on_navigation(move |url| {
                    let servidor = handle.state::<Servidor>().0.lock().unwrap().clone();
                    if navegacion_interna(url, servidor.as_deref()) {
                        return true;
                    }
                    // Enlace externo: lo abre la aplicación del sistema (Chrome, Maps, WhatsApp…).
                    // Fuera del hilo de la interfaz: el plugin de Android no responde si se llama desde aquí.
                    let (h, destino) = (handle.clone(), url.to_string());
                    std::thread::spawn(move || {
                        if let Err(e) = h.opener().open_url(destino, None::<&str>) {
                            eprintln!("no se pudo abrir el enlace externo: {e}");
                        }
                    });
                    false
                });
            #[cfg(desktop)]
            let builder = builder.title("Cauce").inner_size(420.0, 860.0);
            builder.build()?;
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("no fue posible iniciar Cauce");
}
