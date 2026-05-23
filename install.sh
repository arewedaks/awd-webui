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
# 4. Set Permission Akhir
#============================================#
echo ""
echo "[4/4] Mengatur permissions akhir..."

# www directory
if [ -d "${PHP_DATA_DIR}/files/www" ]; then
    find "${PHP_DATA_DIR}/files/www" -type f -exec chmod 0644 {} \;
    echo "  -> www permission [OK]"
fi

# tmp directory
if [ -d "${PHP_DATA_DIR}/files/tmp" ]; then
    chmod 0755 "${PHP_DATA_DIR}/files/tmp"
    echo "  -> tmp permission [OK]"
fi

# tmp directory untuk PHP script (/data/adb/php8/files/tmp)
if [ ! -d "${PHP_DATA_DIR}/files/tmp" ]; then
    mkdir -p "${PHP_DATA_DIR}/files/tmp"
    chmod 0755 "${PHP_DATA_DIR}/files/tmp"
    echo "  -> /data/adb/php8/files/tmp dibuat [OK]"
else
    chmod 0755 "${PHP_DATA_DIR}/files/tmp"
    echo "  -> /data/adb/php8/files/tmp permission [OK]"
fi

#============================================#
# Selesai
#============================================#
echo ""
echo "========================================"
echo " ✅ Instalasi Selesai!"
echo "========================================"