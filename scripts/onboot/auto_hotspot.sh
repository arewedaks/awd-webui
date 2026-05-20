#!/system/bin/sh
LOGFILE=/sdcard/TetheringManager.log
MAX_LOG_SIZE=524288
until [ "$(getprop sys.boot_completed)" = "1" ]; do
    sleep 5
done
export PATH=/data/data/com.termux/files/usr/bin:/system/bin:/system/xbin:$PATH
log_msg() {
    if [ -f "$LOGFILE" ] && [ "$(wc -c < "$LOGFILE")" -gt "$MAX_LOG_SIZE" ]; then
        tail -c 262144 "$LOGFILE" > "${LOGFILE}.tmp" && mv "${LOGFILE}.tmp" "$LOGFILE"
    fi
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

FLASH_PATH=""
for path in /sys/class/leds/flashlight /sys/class/leds/torch-light0 /sys/class/leds/led:torch_0; do
    if [ -f "$path/brightness" ]; then
        FLASH_PATH="$path/brightness"
        break
    fi
done

blink_flash() {
    [ -z "$FLASH_PATH" ] && return
    i=0
    while [ $i -lt 5 ]; do
        echo 1 > "$FLASH_PATH"; sleep 1
        echo 0 > "$FLASH_PATH"; sleep 1
        i=$((i + 1))
    done
}
get_usb_iface() {
    for iface in ncm0 rndis0 usb0; do
        if ip link show $iface >/dev/null 2>&1; then
            echo "$iface"
            return
        fi
    done
    echo "rndis0" # Fallback default
}
is_hotspot_on() {
    dumpsys wifi | grep -iE "mWifiApState=13|ApEnabledState|ap_enabled" >/dev/null && return 0
    dumpsys tethering | grep -A 3 "Tethered interfaces:" | grep -qE "wlan|swlan" && return 0
    ip addr show wlan0 2>/dev/null | grep -q "192.168." && return 0
    ip addr show swlan0 2>/dev/null | grep -q "192.168." && return 0
    return 1
}

enable_hotspot() {
    log_msg "[WIFI] Starting hotspot..."
    cmd connectivity start-tethering wifi >/dev/null 2>&1
    
    if ! is_hotspot_on; then
        service call tethering 4 null s16 random >/dev/null 2>&1
    fi
}

is_usb_tethered() {
    local iface
    iface=$(get_usb_iface)
    ip addr show "$iface" 2>/dev/null | grep -q "inet "
}

enable_usb_tether() {
    local iface
    iface=$(get_usb_iface)
    log_msg "[USB] Enabling USB Tethering on $iface..."
    cmd connectivity start-tethering usb >/dev/null 2>&1
    if ! is_usb_tethered; then
        setprop sys.usb.config rndis,adb
    fi
    sleep 3
}

if ! ip addr show lo 2>/dev/null | grep -q "192.168.8.1"; then
    ip addr add 192.168.8.1/24 dev lo
    log_msg "[INIT] Loopback IP added."
fi

if ! is_hotspot_on; then
    enable_hotspot
    sleep 5
    if is_hotspot_on; then
        log_msg "[BOOT] Hotspot active."
        blink_flash
    fi
fi

log_msg "[START] Watchdog running."

while true; do
    if ! is_hotspot_on; then
        log_msg "[WARN] Hotspot down. Restarting..."
        enable_hotspot
        sleep 10
        if is_hotspot_on; then
            log_msg "[OK] Hotspot restored."
            blink_flash
        fi
    fi
    USB_ONLINE=$(cat /sys/class/power_supply/usb/online 2>/dev/null)
    if [ "$USB_ONLINE" = "1" ]; then
        if ! is_usb_tethered; then
            log_msg "[WARN] USB connected but no IP. Fixing..."
            enable_usb_tether
            sleep 10
            if is_usb_tethered; then
                log_msg "[OK] USB Tethering connected."
                blink_flash
            fi
        fi
    fi

    sleep 15
done