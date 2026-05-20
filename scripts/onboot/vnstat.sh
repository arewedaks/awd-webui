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
    
    echo "$(date): Mencoba menjalankan vnstatd..." >> $LOGFILE
    
    if pgrep vnstatd > /dev/null; then
        echo "$(date): vnstatd sudah berjalan" >> $LOGFILE
    else
        su -c "/data/data/com.termux/files/usr/bin/vnstatd -d"
        echo "$(date): vnstatd dijalankan" >> $LOGFILE
    fi
)&