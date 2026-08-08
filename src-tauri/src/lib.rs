use std::net::{TcpListener, TcpStream};
use std::path::PathBuf;
use std::process::{Child, Command};
use std::sync::Mutex;
use std::thread;
use std::time::{Duration, Instant};

use tauri::{Manager, WebviewUrl, WebviewWindowBuilder, WindowEvent};
use tauri_plugin_dialog::DialogExt;

#[cfg(windows)]
use std::os::windows::io::RawHandle;

struct PhpServer {
    children: Mutex<Option<Vec<Child>>>,
    // Handle job dijaga hidup seumur app. Saat app keluar (termasuk bila
    // di-kill paksa), OS menutup handle ini sehingga seluruh anak proses PHP
    // ikut dimatikan (JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE) — mencegah proses
    // yatim yang mengunci file instalasi.
    #[cfg(windows)]
    _job: Mutex<Option<JobHandle>>,
}

/// Pembungkus RawHandle agar bisa disimpan dalam state Tauri (Send + Sync).
/// Field sengaja tidak dibaca: nilai hanya dipertahankan agar handle job tetap
/// hidup; saat proses app berakhir (termasuk di-kill paksa), OS menutup handle
/// dan anak proses PHP ikut dimatikan.
#[cfg(windows)]
#[allow(dead_code)]
struct JobHandle(RawHandle);

#[cfg(windows)]
unsafe impl Send for JobHandle {}

#[cfg(windows)]
unsafe impl Sync for JobHandle {}

#[cfg(windows)]
mod job {
    use std::os::windows::io::AsRawHandle;
    use std::process::Child;

    use windows_sys::Win32::Foundation::{CloseHandle, HANDLE};
    use windows_sys::Win32::System::JobObjects::{
        AssignProcessToJobObject, CreateJobObjectW, JobObjectExtendedLimitInformation,
        SetInformationJobObject, JOBOBJECT_EXTENDED_LIMIT_INFORMATION,
        JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE,
    };

    pub(crate) fn create_kill_on_close_job() -> Option<HANDLE> {
        unsafe {
            let job = CreateJobObjectW(std::ptr::null(), std::ptr::null());
            if job.is_null() {
                return None;
            }

            let mut info: JOBOBJECT_EXTENDED_LIMIT_INFORMATION = std::mem::zeroed();
            info.BasicLimitInformation.LimitFlags = JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE;

            let ok = SetInformationJobObject(
                job,
                JobObjectExtendedLimitInformation,
                &info as *const _ as *const std::ffi::c_void,
                std::mem::size_of::<JOBOBJECT_EXTENDED_LIMIT_INFORMATION>() as u32,
            );

            if ok == 0 {
                let _ = CloseHandle(job);
                return None;
            }

            Some(job)
        }
    }

    pub(crate) fn assign(child: &Child, job: HANDLE) {
        unsafe {
            // Gagal di-skip aman (mis. proses sudah berada di job lain).
            let _ = AssignProcessToJobObject(job, child.as_raw_handle());
        }
    }
}

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

/// Argumen `-d` untuk memuat CA bundle (HTTPS Telegram/GitHub API). Wajib
/// absolut karena `curl.cainfo`/`openssl.cafile` adalah direktif
/// PHP_INI_SYSTEM — `ini_set()` di bootstrap PHP no-op, dan path relatif di
/// php.ini resolve terhadap direktori php.exe (bukan direktori instalasi).
fn cacert_args(app: &tauri::AppHandle) -> Vec<String> {
    let Some(dir) = php_dir(app) else {
        return Vec::new();
    };
    let cacert = dir.join("extras").join("ssl").join("cacert.pem");
    if !cacert.is_file() {
        return Vec::new();
    }
    let path = cacert.display().to_string();
    vec![
        "-d".to_string(),
        format!("curl.cainfo={path}"),
        "-d".to_string(),
        format!("openssl.cafile={path}"),
    ]
}

/// Direktori tambahan scan ini yang berisi `cacert.ini` mengarah ke CA bundle
/// absolut. DI-SET lewat env `PHP_INI_SCAN_DIR` pada SEMUA proses PHP supaya
/// diwariskan otomatis ke seluruh child proses (termasuk yang di-spawn
/// `schedule:work`) — tidak seperti argumen `-d` yang TIDAK ikut diteruskan
/// ke proses anak yang di-spawn Laravel/Symfony. File ditulis ulang setiap
/// startup karena path instalasi bisa berubah antar-instalasi.
fn cacert_scan_dir(app: &tauri::AppHandle) -> Option<PathBuf> {
    let Some(dir) = php_dir(app) else {
        return None;
    };
    let cacert = dir.join("extras").join("ssl").join("cacert.pem");
    if !cacert.is_file() {
        return None;
    }
    let data_dir = app.path().app_data_dir().ok()?;
    let scan_dir = data_dir.join("php-ini-scan");
    std::fs::create_dir_all(&scan_dir).ok()?;
    let contents = format!(
        "curl.cainfo=\"{}\"\nopenssl.cafile=\"{}\"\n",
        cacert.display().to_string().replace('\\', "\\\\"),
        cacert.display().to_string().replace('\\', "\\\\"),
    );
    let _ = std::fs::write(scan_dir.join("cacert.ini"), contents);
    Some(scan_dir)
}

fn apply_cacert_scan(cmd: &mut Command, app: &tauri::AppHandle) {
    if let Some(scan) = cacert_scan_dir(app) {
        cmd.env("PHP_INI_SCAN_DIR", scan);
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
    let mut all_args = cacert_args(app);
    all_args.extend(args.iter().cloned());
    cmd.args(&all_args)
        .current_dir(&root)
        .env("SMARTRKAS_DATA_DIR", &data_dir)
        .env("DB_DATABASE", &db_path)
        .env("APP_ENV", "production")
        .env("APP_VERSION", env!("CARGO_PKG_VERSION"))
        .stdout(std::process::Stdio::null())
        .stderr(std::process::Stdio::null());
    prepend_php_to_path(&mut cmd, app);
    apply_cacert_scan(&mut cmd, app);

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

/// Simpan file biner yang diunduh dari server lokal (session cookie dipegang
/// webview, jadi JS meng-fetch URL lalu mengirim hasilnya sebagai base64).
/// Menampilkan dialog "Save As" native secara blocking. Return None bila user
/// membatalkan; Some(path) bila file berhasil disimpan.
#[tauri::command]
async fn save_download(
    app: tauri::AppHandle,
    base64_data: String,
    filename: String,
) -> Result<Option<String>, String> {
    use base64::Engine;

    let bytes = base64::engine::general_purpose::STANDARD
        .decode(base64_data.as_bytes())
        .map_err(|e| format!("Data yang diterima tidak valid ({e})."))?;

    let default_name = if filename.trim().is_empty() {
        "download".to_string()
    } else {
        filename
    };

    let picked = app
        .dialog()
        .file()
        .set_file_name(&default_name)
        .blocking_save_file();

    let Some(picked) = picked else {
        return Ok(None);
    };

    let Some(save_path) = picked.as_path() else {
        return Err("Lokasi penyimpanan tidak valid.".to_string());
    };

    std::fs::write(save_path, &bytes).map_err(|e| format!("Gagal menyimpan file ({e})."))?;

    Ok(Some(save_path.display().to_string()))
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .plugin(tauri_plugin_dialog::init())
        .invoke_handler(tauri::generate_handler![save_download])
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

            run_php(
                &handle,
                &["artisan".to_string(), "migrate".to_string(), "--force".to_string()],
                true,
            );

            let port = find_free_port();
            let php = php_binary(&handle);
            let root = app_root(&handle);

            // Jalankan PHP built-in server LANGSUNG (tanpa `artisan serve`).
            // ServeCommand hanya meneruskan env ke proses server anak dan
            // TIDAK meneruskan argumen `-d` — akibatnya curl.cainfo /
            // openssl.cafile tidak pernah termuat di proses yang benar-benar
            // menangani HTTP, sehingga HTTPS (Telegram/GitHub) gagal. Dengan
            // spawn langsung, `-d` berlaku di proses server itu sendiri.
            let server_router = root
                .join("vendor")
                .join("laravel")
                .join("framework")
                .join("src")
                .join("Illuminate")
                .join("Foundation")
                .join("resources")
                .join("server.php");

            let mut cmd = Command::new(&php);
            for a in cacert_args(&handle) {
                cmd.arg(a);
            }
            cmd.arg("-S")
                .arg(format!("127.0.0.1:{port}"))
                .arg(&server_router)
                .current_dir(&root.join("public"))
                .env("SMARTRKAS_DATA_DIR", &data_dir)
                .env("DB_DATABASE", &db_path)
                .env("APP_ENV", "production")
                .env("APP_VERSION", env!("CARGO_PKG_VERSION"))
                .stdout(std::process::Stdio::null())
                .stderr(std::process::Stdio::null());
            prepend_php_to_path(&mut cmd, &handle);
            apply_cacert_scan(&mut cmd, &handle);

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

            #[cfg(windows)]
            let job_handle = job::create_kill_on_close_job();

            #[cfg(windows)]
            if let Some(job_handle) = job_handle {
                for child in children.iter() {
                    job::assign(child, job_handle);
                }
            }

            #[cfg(windows)]
            let server_state = PhpServer {
                children: Mutex::new(Some(children)),
                _job: Mutex::new(job_handle.map(JobHandle)),
            };

            #[cfg(not(windows))]
            let server_state = PhpServer {
                children: Mutex::new(Some(children)),
            };

            app.manage(server_state);

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
                    let mut guard = state.children.lock().unwrap();
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
