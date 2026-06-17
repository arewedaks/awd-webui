#!/system/bin/sh
#==============================================#
#  AreweDaks PHP8 WebServer - Install Script   #
#==============================================#

# === Konfigurasi ===
EXTRACT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_DATA_DIR="/data/adb/php8"
MAGISK_MOD_DIR="/data/adb/modules/php8-webserver"

echo ""
echo "========================================"
echo " AreweDaks PHP8 WebServer - Install"
echo "========================================"
echo " Extract Dir: $EXTRACT_DIR"
echo ""

#============================================#
# Copy files/ → /data/adb/php8/files/
#============================================#
echo "Menginstal files/..."

if [ -d "${EXTRACT_DIR}/files" ]; then
    mkdir -p "${PHP_DATA_DIR}/files"
    cp -r "${EXTRACT_DIR}/files/"* "${PHP_DATA_DIR}/files/"

    # Set permission
    chmod -R 0755 "${PHP_DATA_DIR}/files"
    echo "  -> files/ -> ${PHP_DATA_DIR}/files/ [OK]"
else
    echo "  -> files/ tidak ditemukan [SKIP]"
fi

#============================================#
# Copy scripts/ → /data/adb/php8/scripts/
#============================================#
echo ""
echo "Menginstal scripts/..."

if [ -d "${EXTRACT_DIR}/scripts" ]; then
    mkdir -p "${PHP_DATA_DIR}/scripts"
    cp -r "${EXTRACT_DIR}/scripts/"* "${PHP_DATA_DIR}/scripts/"

    # Set executable permission untuk semua script
    find "${PHP_DATA_DIR}/scripts" -type f -exec chmod 0755 {} \;
    echo "  -> scripts/ -> ${PHP_DATA_DIR}/scripts/ [OK]"
else
    echo "  -> scripts/ tidak ditemukan [SKIP]"
fi

#============================================#
# Copy modules/ → /data/adb/modules/
#============================================#
echo ""
echo "Menginstal modules/..."

if [ -d "${EXTRACT_DIR}/modules" ]; then
    # Hapus module lama dulu agar tidak ada file sisa
    if [ -d "/data/adb/modules/php8-webserver" ]; then
        # Timpa file lama dengan yang baru (force overwrite)
        cp -rf "${EXTRACT_DIR}/modules/"* "/data/adb/modules/"
    else
        cp -r "${EXTRACT_DIR}/modules/"* "/data/adb/modules/"
    fi
    echo "  -> modules/ ditimpa ke /data/adb/modules/ [OK]"
else
    echo "  -> modules/ tidak ditemukan [SKIP]"
fi

#============================================#
# Copy bin/ (php, ttyd, dll) → /data/adb/php8/files/bin/
#============================================#
echo ""
echo "Menginstal bin/..."

# Pastikan folder bin ada
mkdir -p "${PHP_DATA_DIR}/files/bin"

if [ -d "${EXTRACT_DIR}/bin" ]; then
    cp -rf "${EXTRACT_DIR}/bin/"* "${PHP_DATA_DIR}/files/bin/"
    
    # Set executable permission untuk semua binary di dalam bin
    find "${PHP_DATA_DIR}/files/bin" -type f -exec chmod 0755 {} \;
    echo "  -> bin/ -> ${PHP_DATA_DIR}/files/bin/ [OK]"
else
    echo "  -> bin/ tidak ditemukan di ${EXTRACT_DIR} [SKIP]"
fi

#============================================#
# Install TTYD Termux Wrapper
#============================================#
echo ""
echo "Menginstal TTYD Termux Wrapper..."

TERMUX_USR="/data/data/com.termux/files/usr"
WRAPPER_SRC="${EXTRACT_DIR}/scripts/awd_webui_wrapper.sh"
WRAPPER_DEST="${TERMUX_USR}/tmp/awd_webui_wrapper.sh"

if [ -f "$WRAPPER_SRC" ]; then
    if [ ! -d "${TERMUX_USR}/tmp" ]; then
        mkdir -p "${TERMUX_USR}/tmp"
    fi
    
    BASH_BIN="${TERMUX_USR}/bin/bash"
    if [ -f "$BASH_BIN" ]; then
        USER_UID=$(stat -c %u "$BASH_BIN")
        cp -f "$WRAPPER_SRC" "$WRAPPER_DEST"
        chmod 0755 "$WRAPPER_DEST"
        chown $USER_UID:$USER_UID "$WRAPPER_DEST"
        echo "  -> awd_webui_wrapper.sh -> ${TERMUX_USR}/tmp/ [OK]"
    else
        echo "  -> Termux bash tidak ditemukan. (Termux tidak terinstal?) [SKIP]"
    fi
else
    echo "  -> awd_webui_wrapper.sh tidak ditemukan di ${EXTRACT_DIR}/scripts [SKIP]"
fi

#============================================#
# Install APK secara Silent (Folder app/)
#============================================#
echo ""
echo "Menginstal APK dari folder app/..."

if [ -d "${EXTRACT_DIR}/app" ]; then
    # Cari semua file .apk di dalam folder app/
    APK_COUNT=$(ls -1 "${EXTRACT_DIR}/app/"*.apk 2>/dev/null | wc -l)
    
    if [ "$APK_COUNT" -gt 0 ]; then
        for APK_FILE in "${EXTRACT_DIR}/app/"*.apk; do
            APK_NAME=$(basename "$APK_FILE")
            echo "  -> Menginstal ${APK_NAME}..."
            
            # Gunakan cmd package untuk silent install, fallback ke pm install
            INSTALL_SUCCESS=0
            if cmd package install -r -d "$APK_FILE" > /dev/null 2>&1; then
                echo "     [OK] Berhasil diinstal"
                INSTALL_SUCCESS=1
            elif pm install -r "$APK_FILE" > /dev/null 2>&1; then
                echo "     [OK] Berhasil diinstal (via pm fallback)"
                INSTALL_SUCCESS=1
            else
                echo "     [FAIL] Gagal menginstal ${APK_NAME}"
            fi
            
            # Jika aplikasi adalah SoftApHelper (com.awd.modemtools), langsung suntikkan izin rahasia
            if [ "$INSTALL_SUCCESS" -eq 1 ]; then
                # Kita tidak tahu nama file APK aslinya, jadi tembak saja nama package-nya, jika ada tidak akan error.
                pm grant com.awd.modemtools android.permission.SEND_SMS > /dev/null 2>&1
                appops set com.awd.modemtools WRITE_SMS allow > /dev/null 2>&1
                echo "     [OK] Menyuntikkan Izin Kirim/Hapus SMS Otomatis (Jika berlaku)"
            fi
        done
    else
        echo "  -> Tidak ada file APK di folder app/ [SKIP]"
    fi
else
    echo "  -> Folder app/ tidak ditemukan [SKIP]"
fi

#============================================#
# Install Modul Magisk Tambahan (Online)
#============================================#
echo ""
echo "Memeriksa unduhan modul Magisk tambahan..."

DOWNLOAD_LIST="${EXTRACT_DIR}/magisk_downloads.txt"

if [ -f "$DOWNLOAD_LIST" ]; then
    # Cek koneksi internet sederhana
    if ping -c 1 -W 3 8.8.8.8 > /dev/null 2>&1 || ping -c 1 -W 3 1.1.1.1 > /dev/null 2>&1; then
        echo "  -> Koneksi internet tersedia. Memulai unduhan..."
        
        # Membaca daftar link baris per baris
        while IFS= read -r URL || [ -n "$URL" ]; do
            # Abaikan baris kosong atau komentar yang diawali dengan '#'
            case "$URL" in
                ""|\#*) continue ;;
            esac
            
            ZIP_NAME=$(basename "$URL" | cut -d? -f1) # Bersihkan query string jika ada
            if [ -z "$ZIP_NAME" ]; then ZIP_NAME="module.zip"; fi
            TMP_ZIP="/data/local/tmp/${ZIP_NAME}"
            
            echo "  -> Mengunduh: $ZIP_NAME ..."
            
            # Gunakan wget atau curl (biasanya tersedia di sistem Android/Magisk)
            if curl -L -s -o "$TMP_ZIP" "$URL" || wget -q -O "$TMP_ZIP" "$URL"; then
                echo "     [OK] Unduhan selesai, memulai instalasi modul..."
                
                # Eksekusi instalasi modul Magisk
                magisk --install-module "$TMP_ZIP" > /dev/null 2>&1
                if [ $? -eq 0 ]; then
                    echo "     [OK] Modul $ZIP_NAME berhasil diinstal"
                else
                    echo "     [FAIL] Gagal menginstal modul $ZIP_NAME"
                fi
                
                # Hapus file zip sementara
                rm -f "$TMP_ZIP"
            else
                echo "     [FAIL] Gagal mengunduh dari $URL"
                rm -f "$TMP_ZIP"
            fi
        done < "$DOWNLOAD_LIST"
    else
        echo "  -> Tidak ada koneksi internet. Unduhan modul Magisk dibatalkan. [SKIP]"
    fi
else
    echo "  -> magisk_downloads.txt tidak ditemukan [SKIP]"
fi

#============================================#
# Set Permission Akhir & Verifikasi Folder
#============================================#
echo ""
echo "Mengatur permissions & verifikasi folder..."

# Folder dan file yang diperbolehkan di /data/adb/php8
ALLOWED_FOLDERS="files scripts update_temp"

# Hapus folder dan file yang tidak diperbolehkan
if [ -d "${PHP_DATA_DIR}" ]; then
    for item in "${PHP_DATA_DIR}"/*; do
        # Skip jika tidak ada item
        [ -e "$item" ] || continue

        if [ -d "$item" ]; then
            folder_name=$(basename "$item")
            if echo "$ALLOWED_FOLDERS" | grep -qw "$folder_name"; then
                # Folder diperbolehkan
                :
            else
                rm -rf "$item"
                echo "  -> Hapus folder tidak valid: $folder_name [OK]"
            fi
        elif [ -f "$item" ]; then
            # Hapus juga file yang tidak valid
            rm -f "$item"
            echo "  -> Hapus file tidak valid: $(basename "$item") [OK]"
        fi
    done
fi

# Buat folder tmp jika belum ada
if [ ! -d "${PHP_DATA_DIR}/files/tmp" ]; then
    mkdir -p "${PHP_DATA_DIR}/files/tmp"
    echo "  -> tmp folder dibuat [OK]"
fi

# Set permission www (folder: 0755, file: 0644)
if [ -d "${PHP_DATA_DIR}/files/www" ]; then
    chmod 0755 "${PHP_DATA_DIR}/files/www"
    find "${PHP_DATA_DIR}/files/www" -type f -exec chmod 0644 {} \;
    echo "  -> www folder (0755) & files (0644) [OK]"
fi

# Set permission bin (folder & file: 0755)
if [ -d "${PHP_DATA_DIR}/files/bin" ]; then
    chmod -R 0755 "${PHP_DATA_DIR}/files/bin"
    echo "  -> bin folder & files (0755) [OK]"
fi

# Set permission tmp (folder: 0755)
if [ -d "${PHP_DATA_DIR}/files/tmp" ]; then
    chmod 0755 "${PHP_DATA_DIR}/files/tmp"
    echo "  -> tmp permission (0755) [OK]"
fi

# Set permission scripts (folder & file: 0755)
if [ -d "${PHP_DATA_DIR}/scripts" ]; then
    chmod -R 0755 "${PHP_DATA_DIR}/scripts"
    echo "  -> scripts folder & files (0755) [OK]"
fi

# Set permission module Magisk (php8-webserver)
if [ -d "${MAGISK_MOD_DIR}" ]; then
    chmod 0644 "${MAGISK_MOD_DIR}/module.prop"
    echo "  -> module.prop (0644) [OK]"
    chmod 0755 "${MAGISK_MOD_DIR}/service.sh"
    echo "  -> service.sh (0755) [OK]"
fi

#============================================#
# Enable LSPosed Module Automatically
#============================================#
echo ""
echo "Mengaktifkan modul AWD Modem di LSPosed..."

if [ -f "/data/adb/lspd/config/modules_config.db" ] && [ -f "${PHP_DATA_DIR}/files/bin/php" ]; then
    ${PHP_DATA_DIR}/files/bin/php -r "
        try {
            \$db = new SQLite3('/data/adb/lspd/config/modules_config.db');
            
            // Aktifkan modul
            \$db->exec(\"INSERT INTO modules_state (module_pkg_name, user_id, enabled, scope_request_blocked) VALUES ('com.awd.modemtools', 0, 1, 0) ON CONFLICT(module_pkg_name, user_id) DO UPDATE SET enabled=1\");
            
            // Daftar aplikasi/framework yang harus dicentang (Scope)
            // PERBAIKAN: Gunakan 'system' bukan 'android'
            \$scopes = [
                'system', 
                'com.android.networkstack.tethering', 
                'com.android.networkstack.tethering.inprocess',
                'com.google.android.networkstack.tethering',
                'com.google.android.networkstack.tethering.inprocess',
                'com.android.wifi'
            ];
            
            foreach (\$scopes as \$pkg) {
                // Skema LSPosed baru
                @\$db->exec(\"INSERT OR IGNORE INTO scopes (module_pkg_name, user_id, app_pkg_name) VALUES ('com.awd.modemtools', 0, '\$pkg')\");
                // Skema LSPosed lama (fallback)
                @\$db->exec(\"INSERT OR IGNORE INTO scope (module_pkg_name, user_id, app_pkg_name) VALUES ('com.awd.modemtools', 0, '\$pkg')\");
            }
            
            echo \"  -> Modul AWD Modem & System Framework (Scope) berhasil diaktifkan [OK]\n\";
        } catch (Exception \$e) {
            echo \"  -> Gagal mengaktifkan modul: \" . \$e->getMessage() . \" [FAIL]\n\";
        }
    "
else
    echo "  -> LSPosed tidak ditemukan atau PHP belum terinstal [SKIP]"
fi

#============================================#
# Selesai
#============================================#
echo ""
echo "========================================"
echo " ✅ Instalasi Selesai!"
echo "========================================"