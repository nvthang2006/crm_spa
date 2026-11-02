const AUTH_STORAGE_SERVICE: &str = "vn.crmspa.desktop";
const AUTH_STORAGE_KEY_PREFIX: &str = "sb-";
const AUTH_STORAGE_KEY_SUFFIX: &str = "-auth-token";
const AUTH_STORAGE_VALUE_LIMIT: usize = 16_384;

fn validate_auth_storage_key(key: &str) -> Result<(), String> {
    if key.len() > 128 || !key.starts_with(AUTH_STORAGE_KEY_PREFIX) || !key.ends_with(AUTH_STORAGE_KEY_SUFFIX) {
        return Err("Khóa lưu trữ phiên không hợp lệ.".to_owned());
    }

    Ok(())
}

#[tauri::command]
fn credential_read(key: String) -> Result<Option<String>, String> {
    validate_auth_storage_key(&key)?;

    #[cfg(windows)]
    {
        match keyring::Entry::new(AUTH_STORAGE_SERVICE, &key).and_then(|entry| entry.get_password()) {
            Ok(value) => Ok(Some(value)),
            Err(keyring::Error::NoEntry) => Ok(None),
            Err(error) => Err("Không đọc được phiên đăng nhập từ Windows Credential Manager.".to_owned() + &error.to_string()),
        }
    }

    #[cfg(not(windows))]
    {
        Err("Lưu phiên đăng nhập an toàn hiện chỉ hỗ trợ Windows.".to_owned())
    }
}

#[tauri::command]
fn credential_write(key: String, value: String) -> Result<(), String> {
    validate_auth_storage_key(&key)?;
    if value.len() > AUTH_STORAGE_VALUE_LIMIT {
        return Err("Dữ liệu phiên đăng nhập vượt quá giới hạn.".to_owned());
    }

    #[cfg(windows)]
    {
        keyring::Entry::new(AUTH_STORAGE_SERVICE, &key)
            .and_then(|entry| entry.set_password(&value))
            .map_err(|error| "Không lưu được phiên đăng nhập trong Windows Credential Manager: ".to_owned() + &error.to_string())
    }

    #[cfg(not(windows))]
    {
        Err("Lưu phiên đăng nhập an toàn hiện chỉ hỗ trợ Windows.".to_owned())
    }
}

#[tauri::command]
fn credential_delete(key: String) -> Result<(), String> {
    validate_auth_storage_key(&key)?;

    #[cfg(windows)]
    {
        match keyring::Entry::new(AUTH_STORAGE_SERVICE, &key).and_then(|entry| entry.delete_credential()) {
            Ok(()) | Err(keyring::Error::NoEntry) => Ok(()),
            Err(error) => Err("Không xóa được phiên đăng nhập khỏi Windows Credential Manager: ".to_owned() + &error.to_string()),
        }
    }

    #[cfg(not(windows))]
    {
        Err("Lưu phiên đăng nhập an toàn hiện chỉ hỗ trợ Windows.".to_owned())
    }
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_updater::Builder::new().build())
        .invoke_handler(tauri::generate_handler![credential_read, credential_write, credential_delete])
        .run(tauri::generate_context!())
        .expect("Không thể khởi động CRM Spa Desktop");
}
