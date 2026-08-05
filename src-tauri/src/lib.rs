use std::net::{TcpListener, TcpStream};
use std::path::PathBuf;
use std::process::{Child, Command};
use std::sync::Mutex;
use std::thread;
use std::time::{Duration, Instant};

use tauri::{Manager, WebviewUrl, WebviewWindowBuilder, WindowEvent};

struct PhpServer(Mutex<Option<Vec<Child>>>);

fn find_free_port() -> u16 {
    TcpListener::bind(("127.0.0.1", 0))
        .expect("cannot bind to a free port")
        .local_addr()
        .expect("cannot resolve local address")
        .port()
}

fn php_binary(app: &tauri::AppHandle) -> PathBuf {
    if let Ok(dir) = app.path().resource_dir() {
        let bundled = dir.join("php").join("php.exe");
        if bundled.is_file() {
            return bundled;
        }
    }
    PathBuf::from("php")
}

fn php_dir(app: &tauri::AppHandle) -> Option<PathBuf> {
    let php = php_binary(app);
    if php.components().count() > 1 {
        php.parent().map(PathBuf::from)
    } else {
        None
    }
}

fn prepend_php_to_path(cmd: &mut Command, app: &tauri::AppHandle) {
    if let Some(dir) = php_dir(app) {
        let current = std::env::var("PATH").unwrap_or_default();
        cmd.env("PATH", format!("{};{}", dir.display(), current));
    }
}

fn app_root(app: &tauri::AppHandle) -> PathBuf {
    if let Ok(dir) = app.path().resource_dir() {
        if dir.join("artisan").is_file() {
            return dir;
        }
    }
    let manifest = PathBuf::from(env!("CARGO_MANIFEST_DIR"));
    manifest
        .parent()
        .expect("src-tauri has no parent directory")
        .to_path_buf()
}

fn wait_ready(port: u16) -> bool {
    let deadline = Instant::now() + Duration::from_secs(60);
    while Instant::now() < deadline {
        if TcpStream::connect(("127.0.0.1", port)).is_ok() {
            return true;
        }
        thread::sleep(Duration::from_millis(300));
    }
    false
}

fn run_php(app: &tauri::AppHandle, args: &[String], wait: bool) -> Option<Child> {
    let php = php_binary(app);
    let root = app_root(app);
    let data_dir = app.path().app_data_dir().expect("cannot resolve app data dir");
    let db_path = data_dir.join("smartrkas.sqlite");

    let mut cmd = Command::new(&php);
    cmd.args(args)
        .current_dir(&root)
        .env("SMARTRKAS_DATA_DIR", &data_dir)
        .env("DB_DATABASE", &db_path)
        .env("APP_ENV", "production")
        .stdout(std::process::Stdio::null())
        .stderr(std::process::Stdio::null());
    prepend_php_to_path(&mut cmd, app);

    #[cfg(windows)]
    {
        use std::os::windows::process::CommandExt;
        const CREATE_NO_WINDOW: u32 = 0x0800_0000;
        cmd.creation_flags(CREATE_NO_WINDOW);
    }

    let mut child = cmd.spawn().ok()?;
    if wait {
        let _ = child.wait();
        None
    } else {
        Some(child)
    }
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .setup(|app| {
            let handle = app.handle().clone();
            let data_dir = app
                .path()
                .app_data_dir()
                .expect("cannot resolve app data dir");
            std::fs::create_dir_all(&data_dir).expect("cannot create app data dir");

            let db_path = data_dir.join("smartrkas.sqlite");
            let first_run = !db_path.is_file();

            if first_run {
                run_php(
                    &handle,
                    &["artisan".to_string(), "app:install".to_string()],
                    true,
                );
            }

            let port = find_free_port();
            let php = php_binary(&handle);
            let root = app_root(&handle);

            let mut cmd = Command::new(&php);
            cmd.arg("artisan")
                .arg("serve")
                .arg("--host=127.0.0.1")
                .arg(format!("--port={port}"))
                .arg("--no-reload")
                .current_dir(&root)
                .env("SMARTRKAS_DATA_DIR", &data_dir)
                .env("DB_DATABASE", &db_path)
                .env("APP_ENV", "production")
                .stdout(std::process::Stdio::null())
                .stderr(std::process::Stdio::null());
            prepend_php_to_path(&mut cmd, &handle);

            #[cfg(windows)]
            {
                use std::os::windows::process::CommandExt;
                const CREATE_NO_WINDOW: u32 = 0x0800_0000;
                cmd.creation_flags(CREATE_NO_WINDOW);
            }

            let child = cmd.spawn().expect("cannot start the SmartRKAS web server");
            let scheduler = run_php(
                &handle,
                &["artisan".to_string(), "schedule:work".to_string()],
                false,
            );

            let mut children = vec![child];
            if let Some(scheduler) = scheduler {
                children.push(scheduler);
            }
            app.manage(PhpServer(Mutex::new(Some(children))));

            if !wait_ready(port) {
                eprintln!("SmartRKAS web server did not start in time");
            }

            let url = format!("http://127.0.0.1:{port}/");
            let window = WebviewWindowBuilder::new(
                &handle,
                "main",
                WebviewUrl::External(url.parse().expect("cannot parse server url")),
            )
            .title("SmartRKAS")
            .inner_size(1280.0, 800.0)
            .min_inner_size(1024.0, 680.0)
            .resizable(true)
            .build()
            .expect("cannot create main window");

            let _ = window.set_focus();
            Ok(())
        })
        .on_window_event(|window, event| {
            if let WindowEvent::CloseRequested { .. } = event {
                let app = window.app_handle();
                if let Some(state) = app.try_state::<PhpServer>() {
                    let mut guard = state.0.lock().unwrap();
                    if let Some(mut children) = guard.take() {
                        for child in children.iter_mut() {
                            let _ = child.kill();
                            let _ = child.wait();
                        }
                    }
                }
                app.exit(0);
            }
        })
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
