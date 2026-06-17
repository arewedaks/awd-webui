#!/system/bin/sh
#============================================#
#  AweDaks PHP8 WebServer - Update Script   #
#============================================#

URL_DOWNLOAD="$1"
SOURCE_TYPE="${2:-url}"
TARGET_DIR="/data/adb/php8"
TEMP_DIR="/data/adb/php8/update_temp"
UPDATE_FILE="/sdcard/update_temp.zip"
LOG_FILE="/sdcard/ota_update.log"

# Inisialisasi log
log() {
    local msg="$1"
    echo "$msg"
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $msg" >> "$LOG_FILE"
}

log "=========================================="
log "OTA Update Started"
log "Source: $URL_DOWNLOAD"
log "Type: $SOURCE_TYPE"
log "=========================================="

# Bersihkan
rm -f "$UPDATE_FILE"

log "0%"
log "Initializing..."

# --- AMBIL FILE UPDATE ---
if [ "$SOURCE_TYPE" = "local" ]; then
    # Dari path lokal - copy langsung
    log "Loading local file..."

    if [ ! -f "$URL_DOWNLOAD" ]; then
        log "ERROR: File not found: $URL_DOWNLOAD"
        log "File does not exist at specified path."
        exit 1
    fi

    log "Copying file to temp location..."
    cp "$URL_DOWNLOAD" "$UPDATE_FILE"

    if [ $? -ne 0 ]; then
        log "ERROR: Failed to copy file"
        exit 1
    fi

    log "File copied successfully"
else
    # Dari URL - download
    log "Downloading..."

    DOWNLOAD_SUCCESS=0
    DOWNLOAD_METHOD=""

    # 1. Coba Busybox Wget
    if busybox wget --help >/dev/null 2>&1; then
        log "Trying: Busybox wget..."
        busybox wget --no-check-certificate -U "Mozilla/5.0" -O "$UPDATE_FILE" "$URL_DOWNLOAD" 2>&1 | tr '\r' '\n' >> "$LOG_FILE"
        if [ -s "$UPDATE_FILE" ]; then
            DOWNLOAD_SUCCESS=1
            DOWNLOAD_METHOD="Busybox wget"
            log "Download method: Busybox wget"
        fi
    fi

    # 2. Jika gagal, coba System Curl
    if [ $DOWNLOAD_SUCCESS -eq 0 ] && [ -f "/system/bin/curl" ]; then
        log "Trying: System curl..."
        /system/bin/curl -k -L -A "Mozilla/5.0" -o "$UPDATE_FILE" "$URL_DOWNLOAD" 2>&1 >> "$LOG_FILE"
        if [ -s "$UPDATE_FILE" ]; then
            DOWNLOAD_SUCCESS=1
            DOWNLOAD_METHOD="System curl"
            log "Download method: System curl"
        fi
    fi

    # 3. Fallback Curl biasa
    if [ $DOWNLOAD_SUCCESS -eq 0 ] && command -v curl >/dev/null 2>&1; then
        log "Trying: Regular curl..."
        curl -k -L -A "Mozilla/5.0" -o "$UPDATE_FILE" "$URL_DOWNLOAD" 2>&1 >> "$LOG_FILE"
        if [ -s "$UPDATE_FILE" ]; then
            DOWNLOAD_SUCCESS=1
            DOWNLOAD_METHOD="Regular curl"
            log "Download method: Regular curl"
        fi
    fi

    if [ $DOWNLOAD_SUCCESS -eq 0 ]; then
        log "ERROR: All download methods failed"
        log "Please check your internet connection and URL."
        exit 1
    fi
fi

# --- VERIFIKASI DOWNLOAD ---
log "Verifying downloaded file..."

if [ ! -s "$UPDATE_FILE" ]; then
    log "ERROR: File is empty or not found"
    exit 1
fi

SIZE=$(wc -c < "$UPDATE_FILE")
log "File size: $SIZE bytes"

if [ $SIZE -lt 1000 ]; then
    log "ERROR: File too small ($SIZE bytes)"
    log "Expected at least 1000 bytes."
    rm -f "$UPDATE_FILE"
    exit 1
fi

log "File verification passed"

# --- CEK STRUKTUR ZIP ---
log "Checking ZIP structure..."
UNZIP_LIST=$(busybox unzip -l "$UPDATE_FILE" 2>&1)
log "ZIP contents preview:"
echo "$UNZIP_LIST" | head -20 >> "$LOG_FILE"

# Cek folder/file yang diperlukan
HAS_FILES=0
HAS_SCRIPTS=0
HAS_MODULES=0
HAS_INSTALL=0
HAS_MODULE_PROP=0

if echo "$UNZIP_LIST" | grep -q "files/"; then
    HAS_FILES=1
    log "Found: files/"
fi

if echo "$UNZIP_LIST" | grep -q "scripts/"; then
    HAS_SCRIPTS=1
    log "Found: scripts/"
fi

if echo "$UNZIP_LIST" | grep -q "modules/"; then
    HAS_MODULES=1
    log "Found: modules/"
fi

if echo "$UNZIP_LIST" | grep -q "install.sh"; then
    HAS_INSTALL=1
    log "Found: install.sh"
fi

# Wajib ada module.prop untuk validasi
if echo "$UNZIP_LIST" | grep -qE "modules/php8-webserver/module\.prop"; then
    HAS_MODULE_PROP=1
    log "Found: modules/php8-webserver/module.prop [VALID]"
fi

# Validasi minimum requirement
if [ $HAS_FILES -eq 0 ] && [ $HAS_SCRIPTS -eq 0 ] && [ $HAS_MODULES -eq 0 ]; then
    log "ERROR: Invalid update package"
    log "Must contain at least one of: files/, scripts/, or modules/"
    rm -f "$UPDATE_FILE"
    exit 1
fi

if [ $HAS_MODULE_PROP -eq 0 ]; then
    log "ERROR: Invalid update package"
    log "Missing modules/php8-webserver/module.prop"
    rm -f "$UPDATE_FILE"
    exit 1
fi

if [ $HAS_INSTALL -eq 0 ]; then
    log "ERROR: Invalid update package"
    log "Missing install.sh"
    rm -f "$UPDATE_FILE"
    exit 1
fi

# --- PERSIAPAN FOLDER ---
log "Preparing directories..."

# Hapus folder temp lama jika ada
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"
mkdir -p "${TARGET_DIR}/files/tmp"
chmod 755 "${TARGET_DIR}/files/tmp"

# --- BACKUP SEBELUM EXTRACT ---
log "Backing up existing files..."
[ -d "${TARGET_DIR}/files" ] && mv "${TARGET_DIR}/files" "${TARGET_DIR}/files.bak" 2>> "$LOG_FILE"
[ -d "${TARGET_DIR}/scripts" ] && mv "${TARGET_DIR}/scripts" "${TARGET_DIR}/scripts.bak" 2>> "$LOG_FILE"

# --- EKSTRAK KE FOLDER TEMP ---
log "90%"
log "Extracting to temp directory..."
busybox unzip -o "$UPDATE_FILE" -d "$TEMP_DIR" 2>&1 >> "$LOG_FILE"
EXTRACT_RESULT=$?

if [ $EXTRACT_RESULT -eq 0 ]; then
    log "95%"
    log "Finalizing..."

    # Jalankan install.sh dari folder temp
    if [ -f "${TEMP_DIR}/install.sh" ]; then
        log "Running install.sh..."
        chmod 755 "${TEMP_DIR}/install.sh"
        sh "${TEMP_DIR}/install.sh" 2>&1 >> "$LOG_FILE"
        INSTALL_RESULT=$?
        if [ $INSTALL_RESULT -eq 0 ]; then
            log "install.sh completed successfully"
        else
            log "WARNING: install.sh returned error code $INSTALL_RESULT"
        fi
    fi

    # --- RESTORE USER CONFIG ---
    log "Restoring user config files..."
    if [ -d "${TARGET_DIR}/files.bak/config" ]; then
        mkdir -p "${TARGET_DIR}/files/config"
        cp -af "${TARGET_DIR}/files.bak/config/"* "${TARGET_DIR}/files/config/" 2>> "$LOG_FILE"
        log "User config files restored successfully"
    fi

    # Hapus backup lama
    rm -rf "${TARGET_DIR}/files.bak" 2>> "$LOG_FILE"
    rm -rf "${TARGET_DIR}/scripts.bak" 2>> "$LOG_FILE"

    # Bersihkan
    rm -f "$UPDATE_FILE"
    rm -rf "$TEMP_DIR"
    log "100%"
    log "=========================================="
    log "UPDATE SUCCEEDED"
    log "Log saved to: $LOG_FILE"
    log "=========================================="
    echo "SUKSES"
else
    # Rollback jika gagal
    log "ERROR: Extraction failed (code: $EXTRACT_RESULT)"
    log "Rolling back..."

    [ -d "${TARGET_DIR}/files.bak" ] && mv "${TARGET_DIR}/files.bak" "${TARGET_DIR}/files" 2>> "$LOG_FILE"
    [ -d "${TARGET_DIR}/scripts.bak" ] && mv "${TARGET_DIR}/scripts.bak" "${TARGET_DIR}/scripts" 2>> "$LOG_FILE"

    rm -f "$UPDATE_FILE"
    rm -rf "$TEMP_DIR"
    log "=========================================="
    log "UPDATE FAILED - Rollback completed"
    log "Check log for details: $LOG_FILE"
    log "=========================================="
    echo "ERROR"
    exit 1
fi