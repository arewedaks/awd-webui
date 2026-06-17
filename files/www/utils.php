<?php
// Mencegah akses langsung ke file ini via browser
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

/**
 * Eksekusi perintah bash dengan aman dan menangkap pesan error (stderr).
 * @param string $cmd Perintah bash yang akan dieksekusi.
 * @return string|false Hasil eksekusi atau false jika gagal.
 */
function run_root($cmd) {
    if (!function_exists('shell_exec')) return false;
    // Menggunakan 2>&1 untuk menangkap output error (berguna untuk debug/notifikasi)
    return shell_exec($cmd . " 2>&1");
}

/**
 * Format string ke format ukuran bytes yang mudah dibaca manusia (MB, GB, dll).
 * @param int|string $bytes Ukuran dalam byte.
 * @param int $precision Jumlah angka desimal.
 * @return string Ukuran yang telah diformat.
 */
function format_bytes($bytes, $precision = 2) {
    $bytes = (float) $bytes;
    if ($bytes <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    $pow = floor(log($bytes) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Dapatkan IP Host server saat ini secara aman.
 * @return string IP Address Host.
 */
function get_host_ip() {
    return $_SERVER['SERVER_NAME'] ?? $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
}

/**
 * Keamanan: Sanitasi string untuk argumen shell agar terhindar dari injeksi.
 * @param string $arg Argumen shell.
 * @return string Argumen yang telah disanitasi.
 */
function safe_arg($arg) {
    return escapeshellarg($arg);
}

/**
 * Memeriksa apakah perangkat saat ini menggunakan SoftApHelper versi PRO.
 * Mengirimkan broadcast ke APK dan menunggu respon lisensi.
 * @return bool True jika Pro, False jika Free.
 */
function is_pro_user() {
    if (session_status() == PHP_SESSION_NONE) {
        @session_start();
    }
    
    // Gunakan cache sesi agar tidak nge-lag saat pindah tab atau navigasi
    if (isset($_SESSION['is_pro_status'])) {
        return $_SESSION['is_pro_status'];
    }

    $jsonFile = '/data/data/com.awd.modemtools/files/awd_license.json';
    
    // Hapus file lama jika ada agar tidak membaca status usang
    run_root("rm -f $jsonFile");
    
    // Trigger ekstrak lisensi dari APK
    run_root("am broadcast -a com.awd.modemtools.CHECK_LICENSE -n com.awd.modemtools/.LicenseReceiver");
    
    // Tunggu maksimal 2 detik untuk respon
    $timeout = 15; 
    while ($timeout > 0) {
        // Karena file ini dibuat oleh aplikasi (non-root), cek keberadaannya bisa langsung via PHP file_exists untuk mempercepat (karena su sangat lambat)
        // Kita juga bisa pakai run_root jika izin file_exists ditolak, tapi ini lebih baik dicek langsung
        if (file_exists($jsonFile)) break;
        $check = trim(run_root("ls $jsonFile 2>/dev/null"));
        if (!empty($check) && strpos($check, 'No such file') === false) break;
        
        usleep(100000); // 100ms
        $timeout--;
    }

    $jsonData = trim(run_root("cat $jsonFile 2>/dev/null"));
    if (!empty($jsonData)) {
        $data = json_decode($jsonData, true);
        if (isset($data['status']) && $data['status'] === 'PRO') {
            $_SESSION['is_pro_status'] = true;
            return true;
        }
    }
    
    $_SESSION['is_pro_status'] = false;
    return false;
}

/**
 * Merender tampilan HTML Lock Screen untuk fitur PRO.
 * Menghentikan eksekusi script selanjutnya.
 * @param string $featureName Nama fitur yang dikunci.
 */
function render_pro_lock_screen($featureName) {
    echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AWD PRO Required</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", Roboto, sans-serif; background: #0a0502; color: #FDF5E6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; padding: 20px; text-align: center; }
        .lock-card { background: rgba(255, 248, 240, 0.05); padding: 40px 30px; border-radius: 24px; border: 1px solid rgba(184, 115, 51, 0.3); box-shadow: 0 10px 40px rgba(0,0,0,0.8); max-width: 400px; width: 100%; backdrop-filter: blur(10px); }
        .lock-icon { font-size: 4rem; margin-bottom: 20px; animation: pulse 2s infinite; }
        h1 { font-size: 1.5rem; font-weight: 800; color: #C19A6B; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
        p { font-size: 0.95rem; color: #C0B2A2; margin-bottom: 30px; line-height: 1.6; }
        .btn-upgrade { background: rgba(184, 115, 51, 0.85); color: #fff; padding: 14px 24px; border-radius: 14px; text-decoration: none; font-weight: 700; font-size: 0.95rem; border: 1px solid rgba(255,255,255,0.2); transition: 0.3s; display: inline-block; }
        .btn-upgrade:hover { background: #B87333; transform: scale(1.05); }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>
    <div class="lock-card">
        <div class="lock-icon">🔒</div>
        <h1>AWD PRO REQUIRED</h1>
        <p>Fitur <b>' . htmlspecialchars($featureName) . '</b> hanya tersedia untuk pengguna AWD-WebUI versi PRO. Silakan hubungi Admin untuk melakukan upgrade lisensi Anda.</p>
        <a href="https://t.me/Arewedaks" class="btn-upgrade" target="_blank">Hubungi Admin</a>
    </div>
</body>
</html>';
    exit;
}
?>
