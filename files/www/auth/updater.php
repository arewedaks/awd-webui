<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$modulePropPath = '/data/adb/modules/php8-webserver/module.prop';
if (file_exists($modulePropPath)) {
    $content = file_get_contents($modulePropPath);
    if (preg_match('/^version=(.+)$/m', $content, $m)) {
        define('CURRENT_VERSION', trim($m[1]));
    } else {
        define('CURRENT_VERSION', '0.0.0');
    }
} else {
    define('CURRENT_VERSION', '0.0.0');
}

function decrypt_link($code) {
    $binary_paths = ['/data/adb/php8/files/bin/crypto.so', '/data/adb/php8/files/bin/safe_decrypt'];
    $binary_path = '';
    foreach ($binary_paths as $path) { if (file_exists($path)) { $binary_path = $path; break; } }
    if (empty($binary_path)) return false;
    $safe_code = preg_replace('/[^a-zA-Z0-9+\/=:]/', '', $code);
    $command = $binary_path . " -d " . escapeshellarg($safe_code) . " 2>&1";
    $result = trim(shell_exec($command));
    if (empty($result) || strpos($result, 'ENC::') === 0) return false;
    return $result;
}

function getTelegramData() {
    $credsBinary = '/data/adb/php8/files/bin/secure.so';
    if (!file_exists($credsBinary)) return ['err' => 'Binary missing'];
    $output = shell_exec("$credsBinary 2>&1");
    $lines = explode("\n", trim($output));
    if (count($lines) < 2) return ['err' => 'Creds failed'];
    $token = trim($lines[0]);
    $chatId = trim($lines[1]);
    $url = "https://api.telegram.org/bot$token/getChat?chat_id=$chatId";
    $jsonRaw = shell_exec("curl -s -k \"$url\"");
    if (!$jsonRaw) return ['err' => 'Telegram connection failed'];
    $data = json_decode($jsonRaw, true);
    if (!isset($data['ok']) || $data['ok'] !== true) return ['err' => 'Telegram error'];
    $pin = $data['result']['pinned_message'] ?? null;
    if (!$pin) return ['err' => 'No pinned message'];
    $text = $pin['text'] ?? $pin['caption'] ?? '';
    if (empty($text)) return ['err' => 'Empty content'];
    $version = '';
    if (preg_match('/v?(\d+\.\d+(\.\d+)?)/i', $text, $matches)) $version = $matches[1];
    else return ['err' => 'Version format error'];
    $downloadUrl = ''; $rawLink = '';
    if (preg_match('/(https?:\/\/[^\s"]+|ENC::[a-zA-Z0-9+\/=]+)/', $text, $matches)) {
        $rawLink = $matches[0];
        if (strpos($rawLink, 'ENC::') === 0) {
            $decrypted = decrypt_link($rawLink);
            if ($decrypted) $downloadUrl = $decrypted;
            else return ['err' => 'Decryption failed'];
        } else { $downloadUrl = $rawLink; }
    } else { return ['err' => 'URL missing']; }
    $cleanLog = trim(str_replace($rawLink, '', $text));
    return ['status' => 'success', 'ver' => $version, 'url' => $downloadUrl, 'log' => $cleanLog];
}

if (isset($_REQUEST['api'])) {
    $act = $_REQUEST['api'];
    if ($act === 'check') {
        header('Content-Type: application/json');
        $res = getTelegramData();
        if (isset($res['err'])) echo json_encode(['status' => 'error', 'msg' => $res['err']]);
        else {
            $newVer = $res['ver'];
            $isAvail = version_compare($newVer, CURRENT_VERSION, '>');
            echo json_encode(['status' => 'ok', 'avail' => $isAvail, 'ver' => $newVer, 'url' => $res['url'], 'log' => $res['log']]);
        }
        exit;
    }

    // ===================== //
    // UPLOAD LOCAL FILE API  //
    // ===================== //
    if ($act === 'upload_file') {
        header('Content-Type: application/json');
        if (!isset($_FILES['file'])) {
            echo json_encode(['status' => 'error', 'msg' => 'Tidak ada file di-upload.']);
            exit;
        }
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'msg' => 'Upload gagal: ' . $file['error']]);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            echo json_encode(['status' => 'error', 'msg' => 'Hanya file .zip yang didukung.']);
            exit;
        }
        $destDir = '/data/adb/php8';
        $destName = 'manual_update_' . uniqid() . '.zip';
        $destPath = $destDir . '/' . $destName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan file ke ' . $destDir]);
            exit;
        }
        echo json_encode(['status' => 'ok', 'path' => $destPath, 'name' => $file['name']]);
        exit;
    }

    // ===================== //
    // MANUAL UPDATE API     //
    // ===================== //

    // Verifikasi update.zip dari URL atau path lokal
    if ($act === 'verify_update') {
        header('Content-Type: application/json');
        $source = $_POST['source'] ?? ''; // 'url' atau 'local'
        $path = $_POST['path'] ?? '';

        if (empty($path)) {
            echo json_encode(['status' => 'error', 'msg' => 'Path tidak boleh kosong.']);
            exit;
        }

        $tmpFile = '/data/adb/php8/update_verify_' . uniqid() . '.zip';

        // Download dari URL jika source=url
        if ($source === 'url') {
            if (!filter_var($path, FILTER_VALIDATE_URL)) {
                echo json_encode(['status' => 'error', 'msg' => 'URL tidak valid.']);
                exit;
            }
            $path = escapeshellarg($path);
            $cmd = "curl -k -L -A \"Mozilla/5.0\" -o \"$tmpFile\" $path 2>&1";
            $dlResult = shell_exec($cmd);
            if (!file_exists($tmpFile) || filesize($tmpFile) < 1000) {
                @unlink($tmpFile);
                echo json_encode(['status' => 'error', 'msg' => 'Download gagal atau file terlalu kecil.']);
                exit;
            }
            $zipPath = $tmpFile;
        } else {
            // Path lokal - tidak pakai realpath karena bisa gagal di Android
            $zipPath = $path;
            if (!file_exists($zipPath)) {
                echo json_encode(['status' => 'error', 'msg' => 'File tidak ditemukan: ' . basename($path)]);
                exit;
            }
        }

        // Verifikasi ZIP integrity
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath);
        if ($openResult !== true) {
            @unlink($tmpFile);
            echo json_encode(['status' => 'error', 'msg' => 'File bukan ZIP valid atau corrupted.']);
            exit;
        }

        // Cek struktur update.zip yang diharapkan
        // Struktur user:
        // update.zip
        // ├── files/
        // ├── scripts/
        // ├── modules/
        // │   └── php8-webserver/
        // │       └── module.prop  <- versi di sini
        // ├── readme.md
        // └── install.sh
        $foundFiles = [];
        $foundDirs = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $foundFiles[] = $name;
            // Deteksi direktori
            if (preg_match('/^([^\/]+)\/$/', $name, $m)) {
                $foundDirs[$m[1]] = true;
            }
        }

        // Cek apakah ada: minimal 1 folder (files/scripts/modules)
        $hasFilesDir = isset($foundDirs['files']) || in_array('files/', $foundFiles);
        $hasScriptsDir = isset($foundDirs['scripts']) || in_array('scripts/', $foundFiles);
        $hasModulesDir = isset($foundDirs['modules']) || in_array('modules/', $foundFiles);
        $hasInstallSh = in_array('install.sh', $foundFiles);

        $hasValidStructure = ($hasFilesDir || $hasScriptsDir || $hasModulesDir || $hasInstallSh);

        if (!$hasValidStructure) {
            $zip->close();
            @unlink($tmpFile);
            echo json_encode(['status' => 'error', 'msg' => 'File bukan update yang valid. Minimal harus ada folder (files/, scripts/, modules/) atau install.sh.']);
            exit;
        }

        // Ekstrak sementara untuk cek versi dari module.prop
        $verifyDir = '/data/adb/php8/update_verify_' . uniqid();
        mkdir($verifyDir, 0755, true);
        $zip->extractTo($verifyDir);
        $zip->close();

        // Cari module.prop di dalam update.zip
        $updateVer = null;
        $modulePropFiles = [
            $verifyDir . '/modules/php8-webserver/module.prop',
            $verifyDir . '/module.prop',
        ];
        foreach ($modulePropFiles as $mpPath) {
            if (file_exists($mpPath)) {
                $content = file_get_contents($mpPath);
                if (preg_match('/^version=(.+)$/m', $content, $m)) {
                    $updateVer = trim($m[1]);
                }
                break;
            }
        }

        // Cleanup
        shell_exec("rm -rf " . escapeshellarg($verifyDir));
        @unlink($tmpFile);

        // Bandingkan versi
        $canInstall = false;
        $verMsg = 'Unknown';
        if ($updateVer) {
            $verMsg = $updateVer;
            $canInstall = version_compare($updateVer, CURRENT_VERSION, '>');
        } else {
            // Jika tidak ada versi, tetap izinkan (full update)
            $canInstall = true;
            $verMsg = 'Full Package';
        }

        // Cleanup
        @unlink($tmpFile);

        echo json_encode([
            'status' => 'ok',
            'valid' => $canInstall,
            'ver' => $verMsg,
            'msg' => $canInstall
                ? 'Update valid dan bisa diinstall.'
                : 'Versi update (' . $verMsg . ') tidak lebih baru dari versi saat ini (' . CURRENT_VERSION . ').'
        ]);
        exit;
    }

    // Install via SSE stream (support URL dan lokal)
    if ($act === 'update_local_stream') {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        if(function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) ob_end_flush();
        ob_implicit_flush(1);

        $path = $_GET['path'] ?? '';

        if (empty($path)) {
            echo "data: " . json_encode(['msg' => 'Path tidak boleh kosong.', 'pct' => null]) . "\n\n";
            flush();
            echo "data: end\n\n";
            flush();
            exit;
        }

        $script = '/data/adb/php8/scripts/process_update.sh';
        $cmd = "sh " . escapeshellarg($script) . " " . escapeshellarg($path) . " local 2>&1";

        $proc = popen($cmd, 'r');
        if ($proc) {
            $hasError = false;
            while (!feof($proc)) {
                $line = fgets($proc);
                if ($line === false) break;
                $cleanLine = trim($line);
                $pct = null;
                if (preg_match('/(\d{1,3})%/', $cleanLine, $matches)) {
                    $pct = intval($matches[1]);
                }
                if (strpos($cleanLine, 'ERROR') === 0 || strpos($cleanLine, 'FAILED') !== false) {
                    $hasError = true;
                }
                echo "data: " . json_encode(['msg' => $cleanLine, 'pct' => $pct, 'log' => '/sdcard/ota_update.log']) . "\n\n";
                flush();

                // Jika sudah selesai (SUKSES atau ERROR), tunggu sebentar lalu kirim end
                if ($cleanLine === 'SUKSES' || $cleanLine === 'ERROR' || strpos($cleanLine, 'SUKSES') === 0) {
                    usleep(500000); // 0.5 detik
                    break;
                }
            }
            pclose($proc);

            // Kirim info log file
            echo "data: " . json_encode([
                'msg' => 'Log tersimpan di /sdcard/ota_update.log',
                'pct' => $hasError ? null : 100,
                'log' => '/sdcard/ota_update.log',
                'error' => $hasError
            ]) . "\n\n";
            flush();
        }
        echo "data: end\n\n";
        flush();
        exit;
    }

    if ($act === 'update_stream') {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        if(function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) ob_end_flush();
        ob_implicit_flush(1);
        $url = $_GET['url'] ?? '';
        $type = $_GET['type'] ?? 'url';
        $script = '/data/adb/php8/scripts/process_update.sh';
        $cmd = "sh " . escapeshellarg($script) . " " . escapeshellarg($url) . " " . escapeshellarg($type) . " 2>&1";
        $proc = popen($cmd, 'r');
        if ($proc) {
            $hasError = false;
            while (!feof($proc)) {
                $line = fgets($proc);
                if ($line === false) break;
                $cleanLine = trim($line);
                $pct = null;
                if (preg_match('/(\d{1,3})%/', $cleanLine, $matches)) $pct = intval($matches[1]);
                if (strpos($cleanLine, 'ERROR') === 0 || strpos($cleanLine, 'FAILED') !== false) {
                    $hasError = true;
                }
                echo "data: " . json_encode(['msg' => $cleanLine, 'pct' => $pct, 'log' => '/sdcard/ota_update.log']) . "\n\n";
                flush();

                // Jika sudah selesai (SUKSES atau ERROR), tunggu sebentar lalu kirim end
                if ($cleanLine === 'SUKSES' || $cleanLine === 'ERROR' || strpos($cleanLine, 'SUKSES') === 0) {
                    usleep(500000); // 0.5 detik
                    break;
                }
            }
            pclose($proc);

            // Kirim info log file
            echo "data: " . json_encode([
                'msg' => 'Log tersimpan di /sdcard/ota_update.log',
                'pct' => $hasError ? null : 100,
                'log' => '/sdcard/ota_update.log',
                'error' => $hasError
            ]) . "\n\n";
            flush();
        }
        echo "data: end\n\n";
        flush();
        exit;
    }

    // ===================== //
    // CLEANUP FILE API      //
    // ===================== //
    if ($act === 'cleanup_file') {
        header('Content-Type: application/json');
        $path = $_POST['path'] ?? '';
        if (empty($path)) {
            echo json_encode(['status' => 'error', 'msg' => 'Path tidak boleh kosong.']);
            exit;
        }
        // Validasi path harus di /sdcard
        if (strpos($path, '/sdcard') !== 0 && strpos($path, '/data/adb/php8') !== 0) {
            echo json_encode(['status' => 'error', 'msg' => 'Path tidak valid.']);
            exit;
        }
        // Cek apakah file adalah file upload sementara (manual_update_ atau update_verify_)
        if (strpos(basename($path), 'manual_update_') === 0 || strpos(basename($path), 'update_verify_') === 0) {
            if (file_exists($path)) {
                if (@unlink($path)) {
                    echo json_encode(['status' => 'ok', 'msg' => 'File berhasil dihapus.']);
                } else {
                    echo json_encode(['status' => 'error', 'msg' => 'Gagal hapus file.']);
                }
            } else {
                echo json_encode(['status' => 'ok', 'msg' => 'File sudah tidak ada.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'File tidak diizinkan dihapus.']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Updater</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
:root {
    --primary-dark: #8B5A2B;
    --primary-light: #D4956A;
    --cons: rgba(30, 18, 10, 0.4);
    --radius: 16px;
}
@media (prefers-color-scheme: dark) {
    :root {
        --cons: rgba(0, 0, 0, 0.4);
    }
}
* { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: transparent !important;
    color: var(--text-main);
    padding: 16px;
    min-height: 100vh;
}
.main-container {
    max-width: 100%;
    margin: 0 auto;
}
.main-card {
    background: var(--card-bg);
    backdrop-filter: var(--blur-val);
    -webkit-backdrop-filter: var(--blur-val);
    border-radius: var(--radius);
    padding: 20px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

/* Header */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.h-left { display: flex; align-items: center; gap: 12px; }
.h-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
}
.h-title { font-size: 1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
.h-sub { font-size: 0.6rem; color: var(--text-sub); font-weight: 600; margin-top: 2px; }
.h-right { display: flex; align-items: center; gap: 8px; }
.status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; display: flex; align-items: center; gap: 6px; }
.status-badge.checking { background: var(--primary); color: white; }
.status-badge.available { background: var(--suc); color: white; }
.status-badge.up-to-date { background: rgba(0,0,0,0.1); color: var(--text-sub); }
.status-badge.error { background: var(--dang); color: white; }

/* Mode Toggle */
.mode-toggle { display: flex; gap: 6px; padding: 4px; background: rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 16px; }
.mode-btn { flex: 1; padding: 10px; border: none; border-radius: 10px; background: transparent; color: var(--text-sub); font-weight: 700; font-size: 0.7rem; text-transform: uppercase; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 6px; }
.mode-btn.active { background: var(--primary); color: white; }
.mode-btn .icon { font-size: 1rem; }
@keyframes pulse { 0%, 100% { opacity: 0.6; } 50% { opacity: 1; } }

/* Version Row */
.version-row { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.ver-box { flex: 1; background: rgba(0,0,0,0.05); padding: 12px; border-radius: 12px; text-align: center; }
.ver-label { font-size: 0.55rem; color: var(--text-sub); text-transform: uppercase; font-weight: 700; }
.ver-value { font-size: 1.1rem; font-weight: 800; font-family: monospace; margin-top: 4px; }
.ver-value.new { color: var(--primary); }
.ver-arrow { display: flex; align-items: center; font-size: 1.2rem; color: var(--text-sub); }

/* Log Box */
.log-box { background: var(--cons); color: #FDF5E6; padding: 12px; border-radius: 10px; font-family: monospace; font-size: 0.7rem; height: 80px; overflow-y: auto; display: flex; flex-direction: column-reverse; line-height: 1.5; margin-bottom: 14px; }
.log-line { padding-left: 8px; border-left: 2px solid transparent; margin-bottom: 3px; }
.log-line.g { color: var(--suc); border-left-color: var(--suc); }
.log-line.r { color: var(--dang); border-left-color: var(--dang); }
.log-line.b { color: var(--primary-light); border-left-color: var(--primary); }

/* Progress */
.progress-section { display: none; margin-bottom: 12px; }
.progress-section.active { display: block; }
.progress-header { display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--text-sub); font-weight: 700; margin-bottom: 6px; text-transform: uppercase; }
.progress-track { width: 100%; height: 10px; background: rgba(0,0,0,0.1); border-radius: 20px; overflow: hidden; border: 1px solid var(--border); }
.progress-bar { height: 100%; background: var(--primary); width: 0%; transition: width 0.4s ease; border-radius: 20px; }

/* Buttons */
.btn { width: 100%; padding: 14px; border: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 10px; }
.btn:active { transform: scale(0.98); }
.btn-primary { background: var(--primary); color: white; }
.btn-success { background: var(--suc); color: white; }
.btn-secondary { background: transparent; color: var(--text-sub); border: 2px solid var(--border); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Loader */
.loader-wrap { display: flex; justify-content: center; align-items: center; gap: 10px; padding: 16px; }
.spinner { width: 18px; height: 18px; border: 2px solid rgba(0,0,0,0.1); border-top: 2px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.loader-text { font-size: 0.75rem; font-weight: 700; color: var(--text-sub); text-transform: uppercase; }

/* Manual Section */
.manual-section { display: none; }
.manual-section.show { display: block; }
.steps-indicator { display: flex; gap: 6px; margin-bottom: 14px; }
.step-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.step-circle { width: 28px; height: 28px; border-radius: 50%; background: var(--border); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.7rem; transition: 0.3s; }
.step-circle.done { background: var(--suc); color: white; }
.step-circle.active { background: var(--primary); color: white; animation: pulse 1.5s infinite; }
.step-label { font-size: 0.5rem; color: var(--text-sub); text-transform: uppercase; font-weight: 700; text-align: center; }

/* Input */
.input-group { margin-bottom: 10px; }
.input-label { font-size: 0.55rem; color: var(--text-sub); text-transform: uppercase; font-weight: 700; margin-bottom: 6px; display: block; }
.input-field { width: 100%; padding: 12px 14px; border: 2px solid var(--border); border-radius: 10px; background: rgba(0,0,0,0.05); color: var(--text-main); font-size: 0.8rem; transition: 0.3s; }
.input-field:focus { border-color: var(--primary); outline: none; }
.input-field::placeholder { color: var(--text-sub); }

/* Drop Zone */
.drop-zone { border: 2px dashed var(--border); border-radius: 12px; padding: 20px 16px; text-align: center; transition: 0.3s; cursor: pointer; background: rgba(0,0,0,0.03); margin-bottom: 12px; }
.drop-zone:hover, .drop-zone.dragover { border-color: var(--primary); background: rgba(184, 115, 51, 0.08); }
.drop-zone.dragover { transform: scale(1.01); }
.drop-icon { font-size: 1.8rem; margin-bottom: 6px; }
.drop-title { font-size: 0.8rem; font-weight: 700; margin-bottom: 4px; }
.drop-subtitle { font-size: 0.65rem; color: var(--text-sub); }
.drop-hint { font-size: 0.55rem; color: var(--text-sub); text-transform: uppercase; font-weight: 700; margin-top: 6px; }
.file-input { display: none; }

/* Selected File */
.selected-file { background: rgba(184, 115, 51, 0.1); border: 2px solid var(--primary); border-radius: 10px; padding: 10px; margin-bottom: 12px; display: none; align-items: center; gap: 10px; }
.selected-file.show { display: flex; }
.selected-file .file-icon { font-size: 1.3rem; }
.selected-file .file-info { flex: 1; }
.selected-file .file-name { font-size: 0.75rem; font-weight: 700; word-break: break-all; }
.selected-file .file-meta { font-size: 0.6rem; color: var(--text-sub); margin-top: 2px; }
.file-remove { width: 26px; height: 26px; border-radius: 50%; background: var(--dang); color: white; border: none; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; }

/* Upload Progress */
.upload-progress { display: none; align-items: center; gap: 10px; padding: 12px 0; }
.upload-progress.show { display: flex; }
.upload-progress .spinner { width: 16px; height: 16px; border-width: 2px; }
.upload-progress span { font-size: 0.75rem; font-weight: 700; color: var(--text-sub); }

/* Verify Result */
.verify-result { padding: 12px; border-radius: 10px; margin-bottom: 12px; font-size: 0.75rem; font-weight: 700; display: none; }
.verify-result.success { display: block; background: rgba(50, 215, 75, 0.15); color: var(--suc); border: 1px solid var(--suc); }
.verify-result.error { display: block; background: rgba(255, 59, 48, 0.15); color: var(--dang); border: 1px solid var(--dang); }
.divider { height: 1px; background: var(--border); margin: 12px 0; }



@media (max-width: 400px) { body { padding: 10px; } .main-card { padding: 16px; } .h-title { font-size: 0.9rem; } }
</style>
</head>
<body>
<div class="main-container">
    <div class="main-card">
        <!-- Header -->
        <div class="card-header">
            <div class="h-left">
                <div class="h-icon">📦</div>
                <div class="header-text">
                    <div class="h-title">Firmware Update</div>
                    <div class="h-sub">OTA System Updater</div>
                </div>
            </div>
            <div class="h-right">
                <div class="status-badge checking" id="status-badge">
                    <span id="status-icon">⏳</span>
                    <span id="status-text">Cek</span>
                </div>
            </div>
        </div>

        <!-- Mode Toggle -->
        <div class="mode-toggle">
            <button class="mode-btn active" id="mode-auto" onclick="switchMode('auto')">
                <span class="icon">📡</span>
                <span>Auto</span>
            </button>
            <button class="mode-btn" id="mode-manual" onclick="switchMode('manual')">
                <span class="icon">📂</span>
                <span>Manual</span>
            </button>
        </div>

        <!-- Version Row -->
        <div class="version-row">
            <div class="ver-box">
                <div class="ver-label">Versi Saat Ini</div>
                <div class="ver-value">v<?= defined('CURRENT_VERSION') ? CURRENT_VERSION : '0.0.0' ?></div>
            </div>
            <div class="ver-arrow">→</div>
            <div class="ver-box">
                <div class="ver-label">Versi Terbaru</div>
                <div class="ver-value new" id="ver-new">---</div>
            </div>
        </div>

        <!-- Log Box -->
        <div class="log-box" id="log-box"></div>

        <!-- Progress Section -->
        <div class="progress-section" id="progress-section">
            <div class="progress-info">
                <span id="progress-text">Menyiapkan...</span>
                <span id="progress-pct">0%</span>
            </div>
            <div class="progress-track">
                <div class="progress-bar" id="progress-bar"></div>
            </div>
        </div>

        <!-- Loader -->
        <div class="loader-wrap" id="loader-wrap">
            <div class="spinner"></div>
            <span class="loader-text">Memeriksa update...</span>
        </div>

        <!-- Auto Mode Content -->
        <div id="auto-content">
            <button class="btn btn-primary" id="btn-install-auto" onclick="startUpdate()" style="display:none">
                ⬇️ Install Update
            </button>
            <button class="btn btn-secondary" id="btn-cancel" onclick="cancelUpdate()" style="display:none">
                ✕ Batalkan
            </button>
        </div>

        <!-- Manual Mode Content -->
        <div class="manual-section" id="manual-section">
            <!-- Steps -->
            <div class="steps-indicator">
                <div class="step-item">
                    <div class="step-circle active" id="sn1">1</div>
                    <span class="step-label">Pilih</span>
                </div>
                <div class="step-item">
                    <div class="step-circle" id="sn2">2</div>
                    <span class="step-label">Verifikasi</span>
                </div>
                <div class="step-item">
                    <div class="step-circle" id="sn3">3</div>
                    <span class="step-label">Install</span>
                </div>
            </div>

            <!-- URL Input -->
            <div class="input-group">
                <label class="input-label">Link Update ZIP</label>
                <input type="url" class="input-field" id="update-url" placeholder="https://example.com/update.zip">
            </div>

            <div style="text-align:center;margin:8px 0;">
                <span style="font-size:0.6rem;color:var(--text-sub);">atau</span>
            </div>

            <!-- Drop Zone -->
            <div class="drop-zone" id="drop-zone">
                <div class="drop-icon">📂</div>
                <div class="drop-title">Drag & Drop file .zip di sini</div>
                <div class="drop-subtitle">atau klik untuk pilih file</div>
                <div class="drop-hint">Mendukung: .zip</div>
                <input type="file" class="file-input" id="file-input" accept=".zip">
            </div>

            <!-- Upload Progress -->
            <div class="upload-progress" id="upload-progress">
                <div class="spinner"></div>
                <span>Mengupload file...</span>
            </div>

            <!-- Selected File -->
            <div class="selected-file" id="selected-file">
                <div class="file-icon">📦</div>
                <div class="file-info">
                    <div class="file-name" id="file-name"></div>
                    <div class="file-meta" id="file-meta"></div>
                </div>
                <button class="file-remove" onclick="removeSelectedFile()">×</button>
            </div>

            <!-- Local Path -->
            <div class="input-group">
                <label class="input-label">Path File Lokal</label>
                <input type="text" class="input-field" id="local-path" placeholder="/data/adb/php8/update.zip">
            </div>

            <div class="divider"></div>

            <!-- Verify Result -->
            <div class="verify-result" id="verify-result"></div>

            <!-- Verify Button -->
            <button class="btn btn-primary" id="btn-verify" onclick="verifyManual()">🔍 Verifikasi</button>
        </div>
    </div>
</div>

<script>
let upUrl = ''; let es = null; let isFinished = false; let isCancelled = false;
let verifiedData = null; let currentMode = 'auto';
let selectedFileData = null; let uploadedPath = null;

function log(t, type='') {
    const box = document.getElementById('log-box');
    const d = document.createElement('div');
    d.className = 'log-line ' + (type==='suc'?'g':(type==='err'?'r':'b'));
    d.innerText = t; box.prepend(d);
}
function check() {
    const fd = new FormData(); fd.append('api', 'check');
    fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
        document.getElementById('loader-wrap').style.display = 'none';
        if(d.status === 'ok') {
            document.getElementById('ver-new').innerText = 'v' + d.ver;
            document.getElementById('status-badge').className = 'status-badge ' + (d.avail ? 'available' : 'up-to-date');
            document.getElementById('status-icon').innerText = d.avail ? '✓' : '✓';
            document.getElementById('status-text').innerText = d.avail ? 'Update' : 'Up-to-date';
            if(d.avail) {
                document.getElementById('btn-install-auto').style.display = 'flex';
                upUrl = d.url; log("Update tersedia: " + d.log, 'suc');
            } else {
                log("Sistem sudah versi terbaru.", 'suc');
            }
        } else {
            document.getElementById('status-badge').className = 'status-badge error';
            document.getElementById('status-icon').innerText = '✗';
            document.getElementById('status-text').innerText = 'Error';
            document.getElementById('ver-new').innerText = 'Gagal';
            log("Update check gagal: " + d.msg, 'err');
        }
    }).catch(() => {
        document.getElementById('loader-wrap').style.display = 'none';
        document.getElementById('status-badge').className = 'status-badge error';
        document.getElementById('status-icon').innerText = '✗';
        document.getElementById('status-text').innerText = 'Offline';
        log("Tidak dapat terhubung ke server.", 'err');
    });
}
function cancelUpdate() {
    if(!confirm('Batalkan instalasi?')) return;
    isCancelled = true;
    if(es) es.close();
    log("Instalasi dibatalkan.", 'err');
    document.getElementById('btn-cancel').style.display = 'none';
    document.getElementById('progress-section').classList.remove('active');
    document.getElementById('status-badge').className = 'status-badge error';
    setTimeout(() => { document.getElementById('btn-install-auto').style.display = 'flex'; }, 1500);
}
function startUpdate() {
    if (!upUrl || upUrl.trim() === '') { log("URL paket tidak valid.", 'err'); return; }
    if(!confirm('Mulai instalasi?')) return;
    if(es) es.close();
    isFinished = false; isCancelled = false;
    document.getElementById('btn-install-auto').style.display = 'none';
    document.getElementById('btn-cancel').style.display = 'flex';
    document.getElementById('progress-section').classList.add('active');
    document.getElementById('status-badge').className = 'status-badge checking';
    document.getElementById('log-box').innerHTML = '';
    log("Memulai instalasi...");
    const bar = document.getElementById('progress-bar'), pctTxt = document.getElementById('progress-pct'), statusTxt = document.getElementById('progress-text');
    es = new EventSource('?api=update_stream&url=' + encodeURIComponent(upUrl));
    es.onmessage = function(e) {
        if (isCancelled) return;
        if(e.data === 'end') {
            isFinished = true; es.close(); bar.style.width = '100%'; pctTxt.innerText = '100%';
            statusTxt.innerText = 'Berhasil'; document.getElementById('status-badge').className = 'status-badge available';
            document.getElementById('status-icon').innerText = '✓';
            document.getElementById('status-text').innerText = 'Done';
            document.getElementById('btn-cancel').style.display = 'none';
            log("Instalasi selesai. Memuat ulang...", 'suc');
            setTimeout(() => location.reload(), 3000); return;
        }
        try {
            const data = JSON.parse(e.data);
            if(data.pct !== null) { bar.style.width = data.pct + '%'; pctTxt.innerText = data.pct + '%'; statusTxt.innerText = 'Menginstal...'; }
            else if(data.msg) log(data.msg);
        } catch(err) {}
    };
    es.onerror = function() {
        if (isFinished || isCancelled) return;
        es.close(); document.getElementById('status-badge').className = 'status-badge error';
        document.getElementById('btn-cancel').style.display = 'none';
        statusTxt.innerText = 'Gagal';
        log("Instalasi gagal.", 'err');
        setTimeout(() => { document.getElementById('progress-section').classList.remove('active'); document.getElementById('btn-install-auto').style.display = 'flex'; }, 4000);
    };
}
window.onload = check;

// Mode Toggle
function switchMode(mode) {
    currentMode = mode;
    document.getElementById('mode-auto').classList.toggle('active', mode === 'auto');
    document.getElementById('mode-manual').classList.toggle('active', mode === 'manual');
    document.getElementById('auto-content').style.display = mode === 'auto' ? 'block' : 'none';
    document.getElementById('manual-section').classList.toggle('show', mode === 'manual');
    if (mode === 'manual') {
        document.getElementById('manual-section').scrollIntoView({ behavior: 'smooth' });
    }
}

// Drag & Drop Setup
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    if (dropZone && fileInput) {
        dropZone.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function(e) { if (this.files && this.files[0]) handleFileSelect(this.files[0]); });
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() { dropZone.classList.add('dragover'); }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() { dropZone.classList.remove('dragover'); }, false);
        });
        dropZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            if (dt.files && dt.files.length) handleFileSelect(dt.files[0]);
        }, false);
    }
});
function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

// Handle file selection - UPLOAD to server
async function handleFileSelect(file) {
    if (!file.name.toLowerCase().endsWith('.zip')) {
        showResult('error', '⚠️ Hanya file .zip yang didukung!');
        return;
    }
    document.getElementById('upload-progress').classList.add('show');
    const origDropTitle = document.querySelector('.drop-title').textContent;
    document.querySelector('.drop-title').textContent = 'Mengupload...';
    document.querySelector('.drop-title').style.color = 'var(--primary)';

    const formData = new FormData();
    formData.append('api', 'upload_file');
    formData.append('file', file);

    try {
        const res = await fetch('', { method: 'POST', body: formData });
        const data = await res.json();

        document.getElementById('upload-progress').classList.remove('show');
        document.querySelector('.drop-title').textContent = origDropTitle;
        document.querySelector('.drop-title').style.color = '';

        if (data.status === 'ok') {
            selectedFileData = file;
            uploadedPath = data.path;
            document.getElementById('file-name').textContent = file.name;
            document.getElementById('file-meta').textContent = formatFileSize(file.size) + ' | Tersimpan: ' + uploadedPath;
            document.getElementById('selected-file').classList.add('show');
            document.getElementById('local-path').value = uploadedPath;
            setStepDone(1);
        } else {
            showResult('error', '⚠️ Upload gagal: ' + data.msg);
        }
    } catch(err) {
        document.getElementById('upload-progress').classList.remove('show');
        document.querySelector('.drop-title').textContent = origDropTitle;
        document.querySelector('.drop-title').style.color = '';
        showResult('error', '⚠️ Gagal upload file.');
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function removeSelectedFile() {
    selectedFileData = null;
    uploadedPath = null;
    document.getElementById('selected-file').classList.remove('show');
    document.getElementById('file-input').value = '';
    document.getElementById('local-path').value = '';
    setStepActive(1);
}

function showResult(type, message) {
    const resultBox = document.getElementById('verify-result');
    resultBox.innerHTML = message;
    resultBox.className = 'verify-result ' + type;
    resultBox.style.display = 'block';
}

// Verify update (combined URL and local)
async function verifyManual() {
    const resultBox = document.getElementById('verify-result');
    const btn = document.getElementById('btn-verify');
    resultBox.style.display = 'none';
    verifiedData = null;

    let path = document.getElementById('update-url').value.trim();
    let source = 'url';

    if (!path) {
        if (uploadedPath) {
            path = uploadedPath;
            source = 'local';
        } else {
            path = document.getElementById('local-path').value.trim();
            source = 'local';
        }
    }

    if (!path) {
        showResult('error', '⚠️ Masukkan URL atau pilih file!');
        return;
    }

    if (source === 'url' && !path.startsWith('http')) {
        showResult('error', '⚠️ URL harus mulai dengan http:// atau https://');
        return;
    }

    if (source === 'local' && !path.startsWith('/')) {
        showResult('error', '⚠️ Path harus mulai dengan /');
        return;
    }

    const origText = btn.innerText;
    btn.innerText = '⏳ Memverifikasi...';
    btn.disabled = true;
    setStepActive(2);

    const fd = new FormData();
    fd.append('api', 'verify_update');
    fd.append('source', source);
    fd.append('path', path);

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        btn.innerText = origText;
        btn.disabled = false;

        if (data.status === 'ok') {
            showResult('success', '<strong>📦 Versi: ' + data.ver + '</strong><br>' + data.msg);
            if (data.valid) {
                verifiedData = { path: path, source: source, ver: data.ver };
                setStepDone(2);
                btn.innerText = '▶ Install Update';
                btn.className = 'btn btn-success';
                btn.onclick = installManual;
            }
        } else {
            showResult('error', '⚠️ ' + data.msg);
        }
    } catch (err) {
        btn.innerText = origText;
        btn.disabled = false;
        showResult('error', '⚠️ Gagal terhubung ke server.');
    }
}

// Install manual update
async function installManual() {
    if (!verifiedData) { alert('⚠️ Verifikasi update terlebih dahulu!'); return; }

    const source = verifiedData.source;
    let url = '';
    if (source === 'url') {
        url = '?api=update_stream&url=' + encodeURIComponent(verifiedData.path) + '&type=url';
    } else {
        url = '?api=update_local_stream&path=' + encodeURIComponent(verifiedData.path);
    }

    setStepDone(3);
    document.getElementById('btn-verify').style.display = 'none';
    document.getElementById('progress-section').classList.add('active');
    document.getElementById('log-box').innerHTML = '';
    log('Memulai instalasi manual...');
    const bar = document.getElementById('progress-bar'), pctTxt = document.getElementById('progress-pct'), statusTxt = document.getElementById('progress-text');
    bar.style.width = '0%'; pctTxt.innerText = '0%'; statusTxt.innerText = 'Menyiapkan...';

    let isInstalling = true;
    let installSuccess = false;
    const eventSource = new EventSource(url);

    eventSource.onmessage = function(e) {
        if (!isInstalling) return;
        if (e.data === 'end') {
            eventSource.close();
            isInstalling = false;
            setTimeout(() => { document.getElementById('progress-section').classList.remove('active'); }, 4000); document.getElementById('btn-verify').style.display = 'flex';

            // Cleanup uploaded file if exists
            if (verifiedData && verifiedData.source === 'local' && uploadedPath) {
                cleanupUploadedFile(uploadedPath);
            }

            if (installSuccess) {
                log('✅ Update berhasil diinstall!', 'suc');
                setTimeout(() => location.reload(), 500);
            } else {
                log('❌ Instalasi gagal. Cek log', 'err');
            }
            return;
        }
        try {
            const data = JSON.parse(e.data);
            if (data.pct !== null) {
                bar.style.width = data.pct + '%';
                pctTxt.innerText = data.pct + '%';
                statusTxt.innerText = 'Menginstal...';
                
            }
            else if (data.msg) { log(data.msg); }
            if (data.msg === 'SUKSES' || data.finished === true || (typeof data.msg === 'string' && data.msg.startsWith('SUKSES'))) {
                installSuccess = true;
                document.getElementById('prog-icon').innerText = '✅';
                statusTxt.innerText = 'Selesai!';
                bar.style.width = '100%';
                pctTxt.innerText = '100%';
            }
            if (data.error) { installSuccess = false; document.getElementById('prog-icon').innerText = '❌'; statusTxt.innerText = 'Gagal'; }
        } catch(err) {}
    };

    eventSource.onerror = function() {
        if (!isInstalling) return;
        eventSource.close();
        isInstalling = false;
        setTimeout(() => { document.getElementById('progress-section').classList.remove('active'); }, 4000); document.getElementById('btn-verify').style.display = 'flex';
        alert('⚠️ Koneksi terputus.');
    };
}

function showProgressOverlay() {
    document.getElementById('progress-overlay').classList.add('show');
    bar.style.width = '0%';
    pctTxt.innerText = '0%';
    statusTxt.innerText = 'Memulai...';
    document.getElementById('prog-icon').innerText = '⏳';
    setProgressReset(1); setProgressReset(2); setProgressReset(3);
}
function hideProgressOverlay() { document.getElementById('progress-overlay').classList.remove('show'); }
function setProgressDone(n) { const e = document.getElementById('ps'+n); e.className = 'progress-step-icon'; e.style.background='var(--suc)'; e.style.color='white'; e.innerText='✓'; }
function setProgressActive(n) { const e = document.getElementById('ps'+n); e.className = 'progress-step-icon'; e.style.background='var(--primary)'; e.style.color='white'; e.style.animation='pulse 1.5s infinite'; }
function setProgressReset(n) { const e = document.getElementById('ps'+n); e.className = 'progress-step-icon'; e.style.background=''; e.style.color=''; e.style.animation=''; e.innerText=n; }

function setStepActive(num) {
    for (let i = 1; i <= 3; i++) {
        const el = document.getElementById('sn' + i);
        if (i < num) { el.className = 'step-circle done'; el.innerText = '✓'; }
        else if (i === num) { el.className = 'step-circle active'; el.innerText = i; }
        else { el.className = 'step-circle'; el.innerText = i; }
    }
}
function setStepDone(num) { const el = document.getElementById('sn' + num); el.className = 'step-circle done'; el.innerText = '✓'; }

// Cleanup uploaded file
async function cleanupUploadedFile(path) {
    if (!path) return;
    try {
        const fd = new FormData();
        fd.append('api', 'cleanup_file');
        fd.append('path', path);
        await fetch('', { method: 'POST', body: fd });
    } catch(err) {
        console.log('Cleanup failed:', err);
    }
}
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>