#!/system/bin/sh
# RNDIS USB Tethering Service
# Fungsi: Monitor & Auto-enable RNDIS saat kabel USB dicolok

LOGFILE=/sdcard/RNDISManager.log

# --- 1. KONFIGURASI AWAL ---

# Tunggu Booting Selesai
while [ "$(getprop init.svc.bootanim)" != "stopped" ]; do
    sleep 2
done

# Setup Logging
log_msg() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOGFILE
    # Rotasi log untuk mencegah file bengkak (maks ~1000 baris)
    if [ $(wc -l < "$LOGFILE") -gt 1000 ]; then
        tail -n 800 "$LOGFILE" > "${LOGFILE}.tmp"
        mv "${LOGFILE}.tmp" "$LOGFILE"
    fi
}

# Setup Flashlight Path
FLASH_PATH=""
for path in /sys/class/leds/flashlight /sys/class/leds/torch-light0 /sys/class/leds/led:torch_0; do
    if [ -f "$path/brightness" ]; then
        FLASH_PATH="$path/brightness"
        break
    fi
done

# Auto Detect Interface USB
if ip link show rndis0 >/dev/null 2>&1; then
    IFACE_USB="rndis0"
elif ip link show usb0 >/dev/null 2>&1; then
    IFACE_USB="usb0"
else
    IFACE_USB="rndis0" # Default
fi

# --- 2. FUNGSI-FUNGSI ---

# Indicator LED (5x blink)
blink_flash() {
    if [ ! -z "$FLASH_PATH" ]; then
        for i in 1 2 3 4 5; do
            echo 1 > $FLASH_PATH; sleep 0.3
            echo 0 > $FLASH_PATH; sleep 0.3
        done
    fi
}

# Cek apakah RNDIS aktif (ada IP address)
is_rndis_connected() {
    ip addr show $IFACE_USB | grep -q "inet "
    return $?
}

# Aktifkan RNDIS USB
enable_rndis() {
    log_msg "[USB] Mengaktifkan RNDIS pada $IFACE_USB..."
    setprop sys.usb.config rndis,adb
}

# --- 3. INISIALISASI ---
log_msg "[INIT] RNDIS Manager Started"
log_msg "[INIT] Interface: $IFACE_USB"

# --- 4. LOOPING UTAMA ---
while true; do
    # Cek apakah kabel USB tercolok (1 = Connected)
    USB_ONLINE=$(cat /sys/class/power_supply/usb/online 2>/dev/null)

    if [ "$USB_ONLINE" = "1" ]; then
        # Kabel dicolok - Cek status RNDIS
        if ! is_rndis_connected; then
            log_msg "[WARN] USB colok tapi RNDIS belum aktif. Mengaktifkan..."
            enable_rndis

            sleep 10

            if is_rndis_connected; then
                log_msg "[SUCCESS] RNDIS Connected! IP obtained."
                blink_flash
            else
                log_msg "[FAIL] RNDIS enable gagal. Retry next cycle."
            fi
        fi
    else
        # Kabelcopot - Log status (opsional, bisa dihapus untuk mengurangi log)
        # Cek apakah RNDIS still connected (untuk debugging)
        if is_rndis_connected; then
            log_msg "[INFO] RNDIS masih ada IP tapi USB offline"
        fi
    fi

    # Delay 15 detik
    sleep 15
done