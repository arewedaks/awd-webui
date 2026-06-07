#!/system/bin/sh
# Hotspot WiFi Watchdog Service
# Fungsi: Monitor & Auto-enable Hotspot WiFi

LOGFILE=/sdcard/TetheringManager.log

# --- 1. KONFIGURASI AWAL ---

# Tunggu Booting
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

# --- 2. FUNGSI-FUNGSI UTAMA ---

blink_flash() {
    if [ ! -z "$FLASH_PATH" ]; then
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH
    fi
}

# --- BAGIAN A: HOTSPOT WIFI ---
is_hotspot_on() {
    # Cek status AP via dumpsys (Lebih akurat)
    if dumpsys wifi | grep -q "mWifiApState=13"; then
        return 0
    elif dumpsys wifi | grep -q "CurState=ApEnabledState"; then
        return 0
    else
        # Fallback cek IP wlan
        ip addr show wlan0 | grep -q "192.168."
        return $?
    fi
}

enable_hotspot() {
    log_msg "[WIFI] Menyalakan Hotspot..."
    cmd connectivity start-tethering wifi
    if [ $? -ne 0 ]; then
        service call tethering 4 null s16 random
    fi
}

# --- 3. INISIALISASI ---

# Tambah IP Loopback untuk Web Server
if ! ip addr show lo | grep -q "192.168.8.1"; then
    ip addr add 192.168.8.1/24 dev lo
    log_msg "[INIT] IP Loopback ditambahkan."
fi

# Cek Awal Hotspot saat boot
if ! is_hotspot_on; then
    enable_hotspot
    sleep 5
    if is_hotspot_on; then blink_flash; fi
fi

log_msg "[START] Tethering Manager Monitoring Started..."

# --- 4. LOOPING UTAMA (WATCHDOG) ---
while true; do
    
    # === CEK 1: HOTSPOT WIFI ===
    if ! is_hotspot_on; then
        log_msg "[WARN] Hotspot mati! Mencoba nyalakan kembali..."
        enable_hotspot
        # Tunggu sebentar untuk memastikan sistem memproses
        sleep 10 
        if is_hotspot_on; then
            log_msg "[SUCCESS] Hotspot berhasil direstore."
            blink_flash
        fi
    fi

    # Delay 15 detik (Cukup cepat untuk responsif, cukup lama untuk hemat baterai)
    sleep 15
done