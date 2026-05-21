#!/system/bin/sh
#============================================#
#  AweDaks PHP8 WebServer - Install Script  #
#============================================#

# === Konfigurasi ===
DIR_MODUL="/data/adb/modules/php8-webserver"
PHP_DATA_DIR="/data/adb/php8"
PHP_BIN_DIR="${PHP_DATA_DIR}/files/bin"
SYSTEM_UID="1000"
SYSTEM_GID="1000"
VERSION="3.3.7"

# === Inisialisasi ===
[ ! -d "$DIR_MODUL" ] && {
    echo "❌ Folder $DIR_MODUL tidak ditemukan!"
    exit 1
}
cd "$DIR_MODUL"

#============================================#
# 1. Update Versi Module
#============================================#
echo ""
echo "╔══════════════════════════════════════╗"
echo "║  📦 Memperbarui versi modul Magisk   ║"
echo "╚══════════════════════════════════════╝"

VERSION_CODE=$(date +'%Y%m%d')
if [ -f "module.prop" ]; then
    sed -i "s/^version=.*/version=${VERSION}/g" module.prop
    sed -i "s/^versionCode=.*/versionCode=${VERSION_CODE}/g" module.prop
    echo "✅ module.prop updated → v${VERSION} (${VERSION_CODE})"
fi

#============================================#
# 2. Install Files & Scripts
#============================================#
echo ""
echo "╔══════════════════════════════════════╗"
echo "║  📂 Menginstal Files & Scripts        ║"
echo "╚══════════════════════════════════════╝"

install_from_repo() {
    local source_dir="$1"
    local dest_dir="$2"
    local label="$3"
    local count=0

    if [ ! -d "$source_dir" ]; then
        echo "⚠️  $label: Folder sumber tidak ditemukan"
        return
    fi

    mkdir -p "$dest_dir"
    cp -r "$source_dir"/* "$dest_dir/" 2>/dev/null
    count=$(find "$source_dir" -type f | wc -l)
    echo "✅ $label: $count file diinstal"
}

# Copy files dan scripts ke /data/adb/php8/
install_from_repo "files" "$PHP_DATA_DIR/files" "Files"
install_from_repo "scripts" "$PHP_DATA_DIR/scripts" "Scripts"

#============================================#
# 3. Fix Permission
#============================================#
echo ""
echo "╔══════════════════════════════════════╗"
echo "║  🔧 Mengatur File Permissions         ║"
echo "╚══════════════════════════════════════╝"

set_perm_recursive() {
    find "$1" -type d -exec chown "$2:$3" {} \; -exec chmod "$4" {} \;
    find "$1" -type f -exec chown "$2:$3" {} \; -exec chmod "$5" {} \;
}

set_perm_single() {
    [ -f "$1" ] || return
    chown "$2:$3" "$1"
    chmod "$4" "$1"
}

# Permission untuk direktori utama
set_perm_recursive "$DIR_MODUL" 0 0 0755 0644
set_perm_recursive "$PHP_DATA_DIR" 0 0 0755 0644

# Permission untuk sub-direktori
set_perm_recursive "${PHP_DATA_DIR}/scripts" 0 0 0755 0755
set_perm_recursive "${PHP_DATA_DIR}/files/config" 0 0 0755 0644
set_perm_recursive "${PHP_DATA_DIR}/files/www" "$SYSTEM_UID" "$SYSTEM_GID" 0755 0644
set_perm_recursive "$PHP_BIN_DIR" "$SYSTEM_UID" "$SYSTEM_GID" 0755 0755
set_perm_recursive "${PHP_DATA_DIR}/files/tmp" 0 0 0755 0644

# Permission khusus untuk scripts
echo "🚀 Applying scripts permissions..."
for script in \
    "${PHP_DATA_DIR}/scripts/php_run" \
    "${PHP_DATA_DIR}/scripts/ttyd_run" \
    "${PHP_DATA_DIR}/scripts/autorun" \
    "${PHP_DATA_DIR}/scripts/sfa" \
    "${PHP_DATA_DIR}/scripts/php_inotifyd"; do
    set_perm_single "$script" 0 0 0755
done

# Permission khusus untuk binaries
echo "🚀 Applying binaries permissions..."
for binary in \
    "${PHP_BIN_DIR}/php" \
    "${PHP_BIN_DIR}/ttyd"; do
    set_perm_single "$binary" 0 0 0755
done

# Permission untuk config files
echo "🚀 Applying config permissions..."
for config in \
    "${PHP_DATA_DIR}/files/config/php.config" \
    "${PHP_DATA_DIR}/files/config/php.ini" \
    "${PHP_DATA_DIR}/files/config/onboot.cfg"; do
    set_perm_single "$config" "$SYSTEM_UID" "$SYSTEM_GID" 0644
done

echo "✅ Permissions berhasil diterapkan!"

#============================================#
# 4. Auto Move Files (jika ada file tambahan)
#============================================#
echo ""
echo "╔══════════════════════════════════════╗"
echo "║  📦 Memindahkan File Tambahan        ║"
echo "╚══════════════════════════════════════╝"

auto_move() {
    local source_dir="$1"
    local moved=0

    [ ! -d "$source_dir" ] && return
    [ -z "$(ls -A "$source_dir" 2>/dev/null)" ] && {
        echo "ℹ️  Tidak ada file tambahan"
        return
    }

    for file in "$source_dir"/*; do
        [ ! -f "$file" ] && continue

        filename=$(basename "$file")
        target=""

        case "$filename" in
            php|ttyd|nginx|caddy|lighttpd)
                target="${PHP_BIN_DIR}/${filename}"
                ;;
            php.ini|php.config|www.conf|nginx.conf|caddy.conf|lighttpd.conf)
                target="${PHP_DATA_DIR}/files/config/${filename}"
                ;;
            php_run|ttyd_run|autorun|sfa|php_inotifyd|nginx_run|caddy_run|lighttpd_run)
                target="${PHP_DATA_DIR}/scripts/${filename}"
                ;;
            *.php|*.html|*.htm|*.css|*.js|*.json|*.xml|*.txt|*.md)
                target="${PHP_DATA_DIR}/files/www/${filename}"
                ;;
            *)
                continue
                ;;
        esac

        if [ -n "$target" ]; then
            mkdir -p "$(dirname "$target")"
            mv -f "$file" "$target"
            echo "  📦 ${filename} → $(dirname "$target")/"
            moved=$((moved + 1))
        fi
    done

    [ $moved -gt 0 ] && echo "✅ $moved file tambahan dipindahkan!" || echo "ℹ️  Tidak ada file tambahan."
}

auto_move "$PHP_DATA_DIR"

#============================================#
# Selesai
#============================================#
echo ""
echo "╔══════════════════════════════════════╗"
echo "║  ✅ Instalasi Selesai!                ║"
echo "╚══════════════════════════════════════╝"