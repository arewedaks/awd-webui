#!/system/bin/sh
(
    until [ "$(getprop sys.boot_completed)" = "1" ]; do
        sleep 5
    done

    sh /data/adb/php8/scripts/airplane/modpes start
    sleep 3
) &