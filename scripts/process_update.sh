#!/system/bin/sh
#============================================#
#  AweDaks PHP8 WebServer - Update Script   #
#============================================#

URL_DOWNLOAD="$1"
SOURCE_TYPE="${2:-url}"
TARGET_DIR="/data/adb/php8"
MODULE_DIR="/data/adb/modules/php8-webserver"
UPDATE_FILE="/sdcard/update_temp.zip"

# Bersihkan
rm -f "$UPDATE_FILE"

echo "0%"

# --- AMBIL FILE UPDATE ---
if [ "$SOURCE_TYPE" = "local" ]; then
    # Dari path lokal - copy langsung
    echo "Memuat file lokal..."
    if [ ! -f "$URL_DOWNLOAD" ]; then
        echo "ERROR: File tidak ditemukan: $URL_DOWNLOAD"
        exit 1
    fi
    cp "$URL_DOWNLOAD" "$UPDATE_FILE"
else
    # Dari URL - download
    echo "Downloading..."

    DOWNLOAD_SUCCESS=0

    # 1. Coba Busybox Wget
    if busybox wget --help >/dev/null 2>&1; then
        busybox wget --no-check-certificate -U "Mozilla/5.0" -O "$UPDATE_FILE" "$URL_DOWNLOAD" 2>&1 | tr '\r' '\n'
        [ -s "$UPDATE_FILE" ] && DOWNLOAD_SUCCESS=1
    fi

    # 2. Jika gagal, coba System Curl
    if [ $DOWNLOAD_SUCCESS -eq 0 ] && [ -f "/system/bin/curl" ]; then
        /system/bin/curl -k -L -A "Mozilla/5.0" -o "$UPDATE_FILE" "$URL_DOWNLOAD" 2>&1 | tr '\r' '\n'
        [ -s "$UPDATE_FILE" ] && DOWNLOAD_SUCCESS=1
    fi

    # 3. Fallback Curl biasa
    if [ $DOWNLOAD_SUCCESS -eq 0 ] && command -v curl >/dev/null 2>&1; then
        curl -k -L -A "Mozilla/5.0" -o "$UPDATE_FILE" "$URL_DOWNLOAD" 2>&1 | tr '\r' '\n'
        [ -s "$UPDATE_FILE" ] && DOWNLOAD_SUCCESS=1
    fi
fi

# --- VERIFIKASI DOWNLOAD ---
if [ ! -s "$UPDATE_FILE" ]; then
    echo "ERROR: Gagal mendapatkan file update"
    exit 1
fi

SIZE=$(wc -c < "$UPDATE_FILE")
if [ $SIZE -lt 1000 ]; then
    echo "ERROR: File terlalu kecil"
    rm -f "$UPDATE_FILE"
    exit 1
fi

# --- EKSTRAKSI ---
echo "90%"
echo "Extracting..."

# Buat backup folder lama jika ada files/scripts
[ -d "${MODULE_DIR}/files" ] && mv "${MODULE_DIR}/files" "${MODULE_DIR}/files.bak"
[ -d "${MODULE_DIR}/scripts" ] && mv "${MODULE_DIR}/scripts" "${MODULE_DIR}/scripts.bak"

# Extract ke module directory
busybox unzip -o "$UPDATE_FILE" -d "$MODULE_DIR" >/dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "95%"
    echo "Finalizing..."

    # Hapus backup lama
    rm -rf "${MODULE_DIR}/files.bak"
    rm -rf "${MODULE_DIR}/scripts.bak"

    # Copy files/ dan scripts/ ke /data/adb/php8/
    if [ -d "${MODULE_DIR}/files" ]; then
        rm -rf "${TARGET_DIR}/files"
        cp -r "${MODULE_DIR}/files" "${TARGET_DIR}/"
    fi

    if [ -d "${MODULE_DIR}/scripts" ]; then
        rm -rf "${TARGET_DIR}/scripts"
        cp -r "${MODULE_DIR}/scripts" "${TARGET_DIR}/"
    fi

    # Jalankan install.sh dari module directory
    if [ -f "${MODULE_DIR}/install.sh" ]; then
        chmod 755 "${MODULE_DIR}/install.sh"
        sh "${MODULE_DIR}/install.sh"
    fi

    rm -f "$UPDATE_FILE"
    echo "100%"
    echo "SUKSES"
else
    # Rollback jika gagal
    [ -d "${MODULE_DIR}/files.bak" ] && mv "${MODULE_DIR}/files.bak" "${MODULE_DIR}/files"
    [ -d "${MODULE_DIR}/scripts.bak" ] && mv "${MODULE_DIR}/scripts.bak" "${MODULE_DIR}/scripts"
    echo "ERROR: Extraction failed"
    rm -f "$UPDATE_FILE"
    exit 1
fi