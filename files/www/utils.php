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
?>
