#!/system/bin/sh
# Unified Tethering Manager (Hotspot + RNDIS Watchdog)
# Gabungan script auto_hotspot & auto_rndis

LOGFILE=/sdcard/TetheringManager.log

# --- 1. KONFIGURASI AWAL ---

# Tunggu Booting
while [ "$(getprop init.svc.bootanim)" != "stopped" ]; do
    sleep 2
done

# Setup Logging
log_msg() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOGFILE
}

# Setup Flashlight Path
FLASH_PATH=""
for path in /sys/class/leds/flashlight /sys/class/leds/torch-light0 /sys/class/leds/led:torch_0; do
    if [ -f "$path/brightness" ]; then
        FLASH_PATH="$path/brightness"
        break
    fi
done

# Setup Interface RNDIS (Auto Detect)
if ip link show rndis0 >/dev/null 2>&1; then
    IFACE_USB="rndis0"
elif ip link show usb0 >/dev/null 2>&1; then
    IFACE_USB="usb0"
else
    IFACE_USB="rndis0" # Default
fi

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

# --- BAGIAN B: RNDIS USB ---
is_rndis_connected() {
    # Cek apakah interface USB punya IP
    ip addr show $IFACE_USB | grep -q "inet "
    return $?
}

enable_rndis() {
    log_msg "[USB] Mengaktifkan RNDIS pada $IFACE_USB..."
    setprop sys.usb.config rndis,adb
    sleep 3
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

    # === CEK 2: RNDIS USB ===
    # Cek apakah kabel USB tercolok (1 = Connected)
    USB_ONLINE=$(cat /sys/class/power_supply/usb/online 2>/dev/null)
    
    if [ "$USB_ONLINE" = "1" ]; then
        # Hanya jalankan logika RNDIS jika kabel dicolok
        if ! is_rndis_connected; then
            # Cek properti saat ini agar tidak spam command
            CUR_PROP=$(getprop sys.usb.config)
            
            # Jika IP tidak ada, dan config bukan rndis (atau kita paksa refresh)
            log_msg "[WARN] Kabel colok tapi IP RNDIS tidak ada. Fix..."
            enable_rndis
            
            sleep 10
            if is_rndis_connected; then
                 log_msg "[SUCCESS] RNDIS Connected & IP Obtained."
                 blink_flash
            fi
        fi
    fi

    # Delay 15 detik (Cukup cepat untuk responsif, cukup lama untuk hemat baterai)
    sleep 15
done