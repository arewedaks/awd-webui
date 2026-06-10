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

# Setup Flashlight Path & Brightness
FLASH_PATH=""
FLASH_MAX="1"
SWITCH_PATH=""

# Cek apakah ada master switch untuk LED (biasanya di HP Snapdragon/Qualcomm)
if [ -f "/sys/class/leds/led:switch/brightness" ]; then
    SWITCH_PATH="/sys/class/leds/led:switch/brightness"
fi

# Tambahkan beberapa kemungkinan path senter dari berbagai jenis HP (Snapdragon/Mediatek/Exynos)
for path in /sys/class/leds/flashlight /sys/class/leds/torch-light0 /sys/class/leds/led:torch_0 /sys/class/leds/led:switch_0 /sys/class/leds/white:flash /sys/devices/virtual/camera/flash/rear_flash; do
    if [ -f "$path/brightness" ]; then
        FLASH_PATH="$path/brightness"
        if [ -f "$path/max_brightness" ]; then
            FLASH_MAX=$(cat "$path/max_brightness")
            # Jaga-jaga jika max_brightness terbaca 0 atau kosong, fallback ke 1 (boolean standard)
            if [ -z "$FLASH_MAX" ] || [ "$FLASH_MAX" -eq 0 ]; then FLASH_MAX="1"; fi
        fi
        break
    fi
done

# Auto Detect Interface USB (Fungsi agar real-time)
get_usb_iface() {
    if ip link show rndis0 >/dev/null 2>&1; then
        echo "rndis0"
    elif ip link show usb0 >/dev/null 2>&1; then
        echo "usb0"
    else
        echo "rndis0" # Default fallback
    fi
}

# --- 2. FUNGSI-FUNGSI ---

# Indicator LED (5x blink)
blink_flash() {
    if [ ! -z "$FLASH_PATH" ]; then
        for i in 1 2 3 4 5; do
            if [ ! -z "$SWITCH_PATH" ]; then echo 1000 > "$SWITCH_PATH" 2>/dev/null; fi
            echo $FLASH_MAX > "$FLASH_PATH"
            sleep 0.3
            
            echo 0 > "$FLASH_PATH"
            if [ ! -z "$SWITCH_PATH" ]; then echo 0 > "$SWITCH_PATH" 2>/dev/null; fi
            sleep 0.3
        done
    fi
}

# Cek apakah RNDIS aktif (ada IP address)
is_rndis_connected() {
    local iface=$(get_usb_iface)
    if ip addr show $iface 2>/dev/null | grep -q "inet "; then
        return 0
    fi
    return 1
}

# Aktifkan RNDIS USB
enable_rndis() {
    local iface=$(get_usb_iface)
    log_msg "[USB] Mengaktifkan RNDIS pada $iface..."
    
    # Cara lama yang paling ampuh untuk sebagian besar Custom ROM jadul/modifikasi
    setprop sys.usb.config rndis,adb
    
    # Tunggu sebentar agar sistem sempat me-restart USB daemon
    sleep 2
}

# --- 3. INISIALISASI ---
log_msg "[INIT] RNDIS Manager Started"
log_msg "[INIT] Interface: $IFACE_USB"

# --- 4. LOOPING UTAMA ---
while true; do
    # Cek apakah kabel USB tercolok (Bisa dicolok ke PC atau Charger)
    USB_ONLINE=$(cat /sys/class/power_supply/usb/online 2>/dev/null)
    # Cek apakah terhubung ke PC (Data Host) bukan sekadar kepala charger biasa
    # Jika dicolok ke PC, biasanya state akan CONFIGURED atau CONNECTED. Jika charger biasa, mungkin DISCONNECTED
    USB_STATE=$(cat /sys/class/android_usb/android0/state 2>/dev/null)

    # Jika USB_STATE tidak tersedia, kita fallback ke USB_ONLINE saja
    if [ -z "$USB_STATE" ]; then USB_STATE="CONFIGURED"; fi

    if [ "$USB_ONLINE" = "1" ] && [ "$USB_STATE" != "DISCONNECTED" ]; then
        # Kabel dicolok ke PC - Cek status RNDIS
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