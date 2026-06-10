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

# --- 2. FUNGSI-FUNGSI UTAMA ---

blink_flash() {
    if [ ! -z "$FLASH_PATH" ]; then
        # Looping 5 kali kedipan
        for i in 1 2 3 4 5; do
            # Jika ada switch master, nyalakan dulu
            if [ ! -z "$SWITCH_PATH" ]; then echo 1000 > "$SWITCH_PATH" 2>/dev/null; fi
            echo $FLASH_MAX > "$FLASH_PATH"
            sleep 0.3
            
            echo 0 > "$FLASH_PATH"
            if [ ! -z "$SWITCH_PATH" ]; then echo 0 > "$SWITCH_PATH" 2>/dev/null; fi
            sleep 0.3
        done
    fi
}

# --- BAGIAN A: HOTSPOT WIFI ---
is_hotspot_on() {
    # 1. Cek interface hotspot murni (ap0, swlan0, wlan1)
    # Ini cara paling real-time dan akurat tanpa membaca history log.
    for iface in ap0 swlan0 wlan1; do
        if ip addr show $iface 2>/dev/null | grep -q "inet "; then
            return 0
        fi
    done

    # 2. Cek wlan0 (jika ROM menggunakan wlan0 sebagai hotspot)
    if ip addr show wlan0 2>/dev/null | grep -q "inet "; then
        # Hotspot bertindak sebagai router (gateway), sehingga wlan0 tidak akan memiliki default gateway.
        # Jika terhubung ke WiFi biasa, wlan0 PASTI memiliki default gateway.
        if ! ip route show table 0 2>/dev/null | grep -q "default via.*dev wlan0"; then
            # Pastikan IP adalah kelas privat (umumnya hotspot menggunakan 192.168.x.x atau 10.x.x.x)
            if ip addr show wlan0 | grep -qE "inet (192\.168\.|10\.|172\.)"; then
                return 0
            fi
        fi
    fi

    # 3. Cek Dumpsys dengan membatasi baris (menghindari history log lama terbaca)
    if dumpsys wifi | head -n 30 | grep -qiE "mWifiApState=13|CurState=ApEnabledState|mApEnabled=true"; then
        return 0
    fi
    if dumpsys tethering | head -n 30 | grep -qiE "TetherState.TETHERED.*(wlan|ap0|swlan)"; then
        return 0
    fi

    return 1
}

enable_hotspot() {
    log_msg "[WIFI] Menyalakan Hotspot..."
    cmd connectivity start-tethering wifi
    if [ $? -ne 0 ]; then
        service call tethering 4 null s16 random
    fi
}

# --- 3. INISIALISASI ---

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