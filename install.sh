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
# 1. Copy files/ → /data/adb/php8/files/
#============================================#
echo "[1/5] Menginstal files/..."

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
# 2. Copy scripts/ → /data/adb/php8/scripts/
#============================================#
echo ""
echo "[2/5] Menginstal scripts/..."

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
# 3. Copy modules/ → /data/adb/modules/
#============================================#
echo ""
echo "[3/5] Menginstal modules/..."

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
# 4. Set Permission Akhir & Verifikasi Folder
#============================================#
echo ""
echo "[4/4] Mengatur permissions & verifikasi folder..."

# Folder yang diperbolehkan di /data/adb/php8
ALLOWED_FOLDERS="files scripts update_temp"

# Hapus folder yang tidak diperbolehkan
if [ -d "${PHP_DATA_DIR}" ]; then
    for item in "${PHP_DATA_DIR}"/*; do
        if [ -d "$item" ]; then
            folder_name=$(basename "$item")
            if echo "$ALLOWED_FOLDERS" | grep -qw "$folder_name"; then
                # Folder diperbolehkan
                :
            else
                rm -rf "$item"
                echo "  -> Hapus folder tidak valid: $folder_name [OK]"
            fi
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
# Selesai
#============================================#
echo ""
echo "========================================"
echo " ✅ Instalasi Selesai!"
echo "========================================"