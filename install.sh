#!/system/bin/sh
#============================================#
#  AweDaks PHP8 WebServer - Install Script   #
#============================================#

# === Konfigurasi ===
DIR_MODUL="/data/adb/modules/php8-webserver"
PHP_DATA_DIR="/data/adb/php8"
WWW_DIR="${PHP_DATA_DIR}/files/www"

# === Inisialisasi ===
[ ! -d "$DIR_MODUL" ] && {
    echo "ERROR: Folder $DIR_MODUL tidak ditemukan!"
    exit 1
}
cd "$DIR_MODUL"

echo "========================================"
echo " AweDaks PHP8 WebServer - Install"
echo "========================================"

#============================================#
# 1. Set Permission
#============================================#
echo ""
echo "[1/4] Mengatur Permissions..."

# www directory
if [ -d "$WWW_DIR" ]; then
    find "$WWW_DIR" -type d -exec chmod 0755 {} \;
    find "$WWW_DIR" -type f -exec chmod 0644 {} \;
    echo "  -> www permission: OK"
fi

# scripts directory
if [ -d "${PHP_DATA_DIR}/scripts" ]; then
    find "${PHP_DATA_DIR}/scripts" -type d -exec chmod 0755 {} \;
    find "${PHP_DATA_DIR}/scripts" -type f -exec chmod 0755 {} \;
    echo "  -> scripts permission: OK"
fi

# config directory
if [ -d "${PHP_DATA_DIR}/files/config" ]; then
    find "${PHP_DATA_DIR}/files/config" -type d -exec chmod 0755 {} \;
    find "${PHP_DATA_DIR}/files/config" -type f -exec chmod 0644 {} \;
    echo "  -> config permission: OK"
fi

# bin directory
if [ -d "${PHP_DATA_DIR}/files/bin" ]; then
    find "${PHP_DATA_DIR}/files/bin" -type d -exec chmod 0755 {} \;
    find "${PHP_DATA_DIR}/files/bin" -type f -exec chmod 0755 {} \;
    echo "  -> bin permission: OK"
fi

# tmp directory
if [ -d "${PHP_DATA_DIR}/files/tmp" ]; then
    chmod 0755 "${PHP_DATA_DIR}/files/tmp"
    echo "  -> tmp permission: OK"
fi

#============================================#
# 2. Move Files to /files/www
#============================================#
echo ""
echo "[2/4] Memindahkan File ke /files/www..."

if [ -d "$WWW_DIR" ]; then
    count=0
    for item in *; do
        # Skip directories and special files
        [ -d "$item" ] && continue
        [ "$item" = "modules" ] && continue
        [ "$item" = "install.sh" ] && continue

        if [ -f "$item" ]; then
            mv -f "$item" "$WWW_DIR/"
            count=$((count + 1))
        fi
    done
    echo "  -> $count file dipindahkan"
else
    echo "  -> ERROR: $WWW_DIR tidak ditemukan"
fi

#============================================#
# 3. Update Version from version.php
#============================================#
echo ""
echo "[3/4] Memperbarui Versi Module..."

if [ -f "version.php" ]; then
    NEW_VER=$(grep -oP "define\s*\(\s*['\"]CURRENT_VERSION['\"]\s*,\s*['\"]\K[^'\"]+" "version.php" 2>/dev/null)
    if [ -n "$NEW_VER" ]; then
        echo "  -> Versi detected: $NEW_VER"

        # Update /data/adb/modules/php8-webserver/module.prop
        if [ -f "${DIR_MODUL}/module.prop" ]; then
            sed -i "s/^version=.*/version=${NEW_VER}/g" "${DIR_MODUL}/module.prop"
            sed -i "s/^versionCode=.*/versionCode=$(date +'%Y%m%d')/g" "${DIR_MODUL}/module.prop"
            echo "  -> ${DIR_MODUL}/module.prop updated"
        else
            echo "  -> WARNING: module.prop tidak ditemukan"
        fi
    else
        echo "  -> WARNING: Tidak bisa baca versi dari version.php"
    fi
else
    echo "  -> WARNING: version.php tidak ditemukan"
fi

#============================================#
# 4. Move modules/php8-webserver contents
#============================================#
echo ""
echo "[4/4] Memindahkan Isi Module..."

if [ -d "modules/php8-webserver" ]; then
    # Pastikan direktori tujuan ada
    mkdir -p "$DIR_MODUL"

    # Copy isi modules/php8-webserver ke /data/adb/modules/php8-webserver
    cp -r modules/php8-webserver/* "$DIR_MODUL/" 2>/dev/null
    echo "  -> modules/php8-webserver -> $DIR_MODUL"
else
    echo "  -> WARNING: modules/php8-webserver tidak ditemukan"
fi

#============================================#
# Selesai
#============================================#
echo ""
echo "========================================"
echo " Install Selesai!"
echo "========================================"