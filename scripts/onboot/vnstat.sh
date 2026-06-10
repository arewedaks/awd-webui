#!/system/bin/sh
LOGFILE=/sdcard/vnstat.log

(
    echo "$(date): Menunggu sistem boot..." >> $LOGFILE
    until [ "$(getprop sys.boot_completed)" = "1" ]; do
        sleep 2
    done

    until [ -d "/data/data/com.termux/files" ]; do
        echo "$(date): Menunggu Termux..." >> $LOGFILE
        sleep 2
    done
    
    export PATH=/data/data/com.termux/files/usr/bin:/system/bin:$PATH
    export LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib
    export LD_PRELOAD=/data/data/com.termux/files/usr/lib/libtermux-exec.so
    export PREFIX=/data/data/com.termux/files/usr
    export HOME=/data/data/com.termux/files/home

    sleep 15
    
    echo "$(date): Watchdog vnstatd dimulai..." >> $LOGFILE
    
    # Looping Utama (Watchdog) berjalan setiap 60 detik
    while true; do
        if ! pgrep vnstatd > /dev/null; then
            echo "$(date): vnstatd mati atau belum jalan! Menjalankan ulang..." >> $LOGFILE
            # Magisk 'su' menghapus environment, jadi kita harus pass ulang environmentnya langsung di dalam perintah
            su -c "PREFIX=/data/data/com.termux/files/usr LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib PATH=/data/data/com.termux/files/usr/bin:/system/bin:\$PATH /data/data/com.termux/files/usr/bin/vnstatd -d"
            echo "$(date): vnstatd berhasil dipanggil" >> $LOGFILE
        fi
        
        # Delay 60 detik (tidak perlu terlalu cepat untuk menghemat baterai, karena daemon vnstat jarang crash)
        sleep 60
    done
)&