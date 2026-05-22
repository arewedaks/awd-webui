<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
if (file_exists('/data/adb/php8/files/www/auth/version.php')) require_once '/data/adb/php8/files/www/auth/version.php';
else define('CURRENT_VERSION', '0.0.0');

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

        $tmpFile = '/sdcard/update_verify_' . uniqid() . '.zip';

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
        // ├── version.php     <- di root
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

        // Cek apakah ada: version.php di root + minimal 1 folder (files/scripts/modules)
        $hasVersionPhp = in_array('version.php', $foundFiles);
        $hasFilesDir = isset($foundDirs['files']) || in_array('files/', $foundFiles);
        $hasScriptsDir = isset($foundDirs['scripts']) || in_array('scripts/', $foundFiles);
        $hasModulesDir = isset($foundDirs['modules']) || in_array('modules/', $foundFiles);
        $hasInstallSh = in_array('install.sh', $foundFiles);

        $hasValidStructure = ($hasFilesDir || $hasScriptsDir || $hasModulesDir || $hasInstallSh);

        $zip->close();

        if (!$hasValidStructure) {
            @unlink($tmpFile);
            echo json_encode(['status' => 'error', 'msg' => 'File bukan update yang valid. Minimal harus ada folder (files/, scripts/, modules/) atau install.sh.']);
            exit;
        }

        // Ekstrak sementara untuk cek versi
        $verifyDir = '/sdcard/update_verify_' . uniqid();
        mkdir($verifyDir, 0755, true);
        $zip = new ZipArchive();
        $zip->open($zipPath);
        $zip->extractTo($verifyDir);
        $zip->close();

        // Cari version.php di root
        $updateVer = null;
        $versionPath = $verifyDir . '/version.php';
        if (file_exists($versionPath)) {
            $content = file_get_contents($versionPath);
            if (preg_match("/define\s*\(\s*['\"]CURRENT_VERSION['\"]\s*,\s*['\"](\d+\.\d+(?:\.\d+)?)['\"]/", $content, $m)) {
                $updateVer = $m[1];
            }
        }

        // Bandingkan versi
        $canInstall = false;
        $verMsg = 'Unknown';
        if ($updateVer) {
            $verMsg = $updateVer;
            $canInstall = version_compare($updateVer, CURRENT_VERSION, '>');
        } else {
            // Jika tidak ada version.php, tetap izinkan (mungkin full update)
            $canInstall = true;
            $verMsg = 'Full Package';
        }

        // Cleanup
        shell_exec("rm -rf " . escapeshellarg($verifyDir));
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
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Updater</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --suc: #32d74b; --dang: #ff3b30; --cons: rgba(30, 18, 10, 0.4);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
                --cons: rgba(0, 0, 0, 0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; -webkit-font-smoothing: antialiased;
        }
        .con { 
            width: 100%; max-width: 500px; background: var(--card-bg); backdrop-filter: var(--blur-val); 
            -webkit-backdrop-filter: var(--blur-val); border-radius: 28px; padding: 30px; 
            box-shadow: var(--shadow); border: 1px solid var(--border); position: relative;
        }
        .con::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 28px; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); padding-bottom: 20px; margin-bottom: 20px; }
        .ti { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .sub { font-size: 0.75rem; color: var(--text-sub); font-weight: 700; text-transform: uppercase; margin-top: 4px; }
        .dot { width: 12px; height: 12px; border-radius: 50%; background: var(--text-sub); transition: 0.4s; border: 2px solid var(--border); }
        .dot.on { background: var(--suc); box-shadow: 0 0 12px var(--suc); }
        .dot.off { background: var(--dang); box-shadow: 0 0 12px var(--dang); }
        .dot.wait { background: var(--primary); animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 0.5; transform: scale(1); } 50% { opacity: 1; transform: scale(1.2); } }
        .info { display: flex; justify-content: space-between; margin-bottom: 20px; background: rgba(0,0,0,0.05); padding: 18px; border-radius: 16px; border: 1px solid var(--border); }
        .ibl { font-size: 0.65rem; color: var(--text-sub); text-transform: uppercase; font-weight: 800; margin-bottom: 6px; display: block; letter-spacing: 0.5px; }
        .ivl { font-size: 1.1rem; font-weight: 800; font-family: 'SF Mono', monospace; }
        .new { color: var(--primary); }
        .term { 
            background: var(--cons); color: #FDF5E6; padding: 18px; border-radius: 16px; 
            font-family: 'SF Mono', monospace; font-size: 0.75rem; border: 1px solid var(--border); 
            margin-bottom: 20px; max-height: 220px; overflow-y: auto; min-height: 80px; 
            display: flex; flex-direction: column-reverse; line-height: 1.5;
        }
        .log-line { margin-bottom: 4px; white-space: pre-wrap; word-break: break-all; border-left: 2px solid transparent; padding-left: 8px; }
        .tc-g { color: var(--suc); border-left-color: var(--suc); font-weight: 700; } 
        .tc-r { color: var(--dang); border-left-color: var(--dang); } 
        .tc-b { color: var(--primary); border-left-color: var(--primary); }
        .btn { 
            width: 100%; padding: 16px; border: none; border-radius: 16px; background: var(--primary); 
            color: white; font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: 0.3s; 
            text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.25); display: none; 
        }
        .btn:active { transform: scale(0.97); }
        .btn-cancel {
            background: transparent;
            color: var(--text-sub);
            border: 2px solid var(--border);
            margin-top: 10px;
            box-shadow: none;
        }
        .btn-cancel:active { background: rgba(0,0,0,0.05); }
        .ldr { display: flex; justify-content: center; align-items: center; gap: 12px; padding: 15px; }
        .sp { width: 22px; height: 22px; border: 3px solid rgba(0,0,0,0.1); border-top: 3px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .lt { font-size: 0.85rem; font-weight: 700; color: var(--text-sub); text-transform: uppercase; }
        .pg-wrap { display: none; width: 100%; margin-top: 15px; }
        .pg-head { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-sub); margin-bottom: 8px; font-weight: 800; text-transform: uppercase; }
        .pg-track { width: 100%; height: 12px; background: rgba(0,0,0,0.1); border-radius: 20px; overflow: hidden; border: 1px solid var(--border); }
        .pg-bar { height: 100%; background: var(--primary); width: 0%; transition: width 0.3s ease; border-radius: 20px; }

        /* Manual Update Section */
        .manual-section {
            margin-top: 20px;
            border-top: 1px dashed rgba(122, 92, 67, 0.2);
            padding-top: 20px;
        }
        .manual-title {
            font-size: 0.7rem;
            color: var(--text-sub);
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .manual-title::before {
            content: '';
            width: 16px;
            height: 16px;
            background: var(--primary);
            border-radius: 4px;
            display: inline-flex;
        }
        .tab-group {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        .tab-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid var(--border);
            background: transparent;
            color: var(--text-sub);
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            border-radius: 12px;
            cursor: pointer;
            transition: 0.3s;
        }
        .tab-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .input-group {
            margin-bottom: 12px;
        }
        .input-label {
            font-size: 0.65rem;
            color: var(--text-sub);
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: block;
        }
        .input-field {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: rgba(0,0,0,0.05);
            color: var(--text-main);
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.3s;
        }
        .input-field:focus {
            border-color: var(--primary);
            outline: none;
        }
        .input-field::placeholder {
            color: var(--text-sub);
            font-weight: 400;
        }
        .btn-manual {
        }
        .selected-file-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-main);
            word-break: break-all;
        }
        .btn-manual {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 14px;
            padding: 14px 20px;
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            width: 100%;
            transition: 0.3s;
        }
        .btn-manual:hover {
            opacity: 0.9;
        }
        .btn-manual:active {
            transform: scale(0.97);
        }
        .btn-manual:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .verify-result {
            padding: 12px;
            border-radius: 12px;
            margin-top: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            display: none;
        }
        .verify-result.succ {
            display: block;
            background: rgba(50, 215, 75, 0.15);
            color: var(--suc);
            border: 1px solid var(--suc);
        }
        .verify-result.err {
            display: block;
            background: rgba(255, 59, 48, 0.15);
            color: var(--dang);
            border: 1px solid var(--dang);
        }
        .install-step {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            font-size: 0.75rem;
            color: var(--text-sub);
        }
        .step-num {
            width: 24px;
            height: 24px;
            background: var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.7rem;
            color: var(--text-main);
        }
        .step-done { background: var(--suc); color: white; }
        .step-active { background: var(--primary); color: white; animation: pulse 1.5s infinite; }
        .progress-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            display: none;
        }
        .progress-overlay.show { display: flex; }
        .progress-modal {
            background: var(--card-bg);
            backdrop-filter: var(--blur-val);
            -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px;
            padding: 30px;
            width: 90%;
            max-width: 350px;
            text-align: center;
            border: 1px solid var(--border);
        }
        .progress-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .progress-text {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 15px;
        }
        .progress-bar-wrap {
            width: 100%;
            height: 14px;
            background: rgba(0,0,0,0.1);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 10px;
        }
        .progress-bar-inner {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.3s;
            border-radius: 20px;
        }
        .progress-pct {
            font-size: 1.2rem;
            font-weight: 800;
            font-family: 'SF Mono', monospace;
            color: var(--primary);
        }
        .progress-steps {
            margin-top: 20px;
        }
        @media (max-width: 400px) {
            .con { padding: 20px; }
            .ti { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<div class="con">
    <div class="head">
        <div><div class="ti">Firmware</div><div class="sub">OTA Update Center</div></div>
        <div class="dot wait" id="st-dot"></div>
    </div>
    <div class="info">
        <div><span class="ibl">Current Version</span><span class="ivl">v<?= defined('CURRENT_VERSION') ? CURRENT_VERSION : '0.0.0' ?></span></div>
        <div style="text-align:right"><span class="ibl">Latest Available</span><span class="ivl" id="ver-new">---</span></div>
    </div>
    <div class="term" id="log-box"></div>
    <div id="area-act">
        <div class="ldr" id="ldr"><div class="sp"></div><span class="lt">Checking for updates...</span></div>
        <button class="btn" id="btn-up" onclick="startUpdate()">Install Package</button>
        <button class="btn btn-cancel" id="btn-cn" onclick="cancelUpdate()" style="display:none">Cancel Installation</button>
        <div class="pg-wrap" id="pg-box">
            <div class="pg-head"><span id="pg-txt">Preparing...</span><span id="pg-pct">0%</span></div>
            <div class="pg-track"><div class="pg-bar" id="pg-in"></div></div>
        </div>
    </div>

    <!-- Manual Update Section -->
    <div class="manual-section">
        <div class="manual-title">Manual Update</div>

        <!-- Steps Indicator -->
        <div class="progress-steps" id="manual-steps">
            <div class="install-step" id="step1">
                <div class="step-num" id="sn1">1</div>
                <span>Pilih file atau URL update.zip</span>
            </div>
            <div class="install-step" id="step2">
                <div class="step-num" id="sn2">2</div>
                <span>Verifikasi package</span>
            </div>
            <div class="install-step" id="step3">
                <div class="step-num" id="sn3">3</div>
                <span>Install update</span>
            </div>
        </div>

        <!-- Tab Selector -->
        <div class="tab-group">
            <button class="tab-btn active" id="tab-url" onclick="switchTab('url')">Dari URL</button>
            <button class="tab-btn" id="tab-local" onclick="switchTab('local')">File Lokal</button>
        </div>

        <!-- URL Tab -->
        <div class="tab-content active" id="content-url">
            <div class="input-group">
                <label class="input-label">Link update.zip</label>
                <input type="url" class="input-field" id="update-url" placeholder="https://example.com/update.zip">
            </div>
            <button class="btn-manual" id="btn-verify-url" onclick="verifyManual('url')">
                Verifikasi Update
            </button>
        </div>

        <!-- Local File Tab -->
        <div class="tab-content" id="content-local">
            <div class="input-group">
                <label class="input-label">Path file update.zip</label>
                <input type="text" class="input-field" id="local-path" placeholder="/sdcard/update.zip">
                <div style="font-size:0.6rem;color:var(--text-sub);margin-top:4px;">Contoh: /sdcard/update.zip, /storage/emulated/0/Download/update.zip</div>
            </div>
            <button class="btn-manual" id="btn-verify-local" onclick="verifyManual('local')">
                Verifikasi Update
            </button>
        </div>

        <!-- Verify Result -->
        <div class="verify-result" id="verify-result"></div>

        <!-- Install Button (shown after successful verification) -->
        <div id="manual-install-area" style="display:none; margin-top: 12px;">
            <button class="btn-manual" id="btn-install-manual" onclick="installManual()">
                ▶ Install Update Sekarang
            </button>
        </div>
    </div>
</div>

<!-- Progress Overlay -->
<div class="progress-overlay" id="progress-overlay">
    <div class="progress-modal">
        <div class="progress-icon" id="prog-icon">⏳</div>
        <div class="progress-text" id="prog-text">Menginstal update...</div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-inner" id="prog-bar"></div>
        </div>
        <div class="progress-pct" id="prog-pct">0%</div>
        <div class="progress-steps" style="margin-top:15px" id="prog-steps">
            <div class="install-step"><div class="step-num step-done" id="ps1">✓</div><span>Download/Load file</span></div>
            <div class="install-step"><div class="step-num" id="ps2">2</div><span>Ekstrak file</span></div>
            <div class="install-step"><div class="step-num" id="ps3">3</div><span>Install</span></div>
        </div>
    </div>
</div>
<script>
let upUrl = ''; let es = null; let isFinished = false; let isCancelled = false;
function log(t, type='') {
    const box = document.getElementById('log-box');
    const d = document.createElement('div');
    d.className = 'log-line ' + (type==='suc'?'tc-g':(type==='err'?'tc-r':'tc-b'));
    d.innerText = t; box.prepend(d);
}
function check() {
    const fd = new FormData(); fd.append('api', 'check');
    fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
        document.getElementById('ldr').style.display = 'none';
        if(d.status === 'ok') {
            document.getElementById('ver-new').innerText = 'v' + d.ver;
            if(d.avail) {
                document.getElementById('ver-new').classList.add('new');
                document.getElementById('st-dot').className = 'dot on';
                document.getElementById('btn-up').style.display = 'block';
                upUrl = d.url; log("Update tersedia:\n" + d.log, 'suc');
            } else {
                document.getElementById('st-dot').className = 'dot on';
                log("Sistem sudah versi terbaru.", 'suc');
            }
        } else {
            document.getElementById('st-dot').className = 'dot off';
            document.getElementById('ver-new').innerText = 'Gagal';
            log("Update check gagal: " + d.msg, 'err');
        }
    }).catch(() => {
        document.getElementById('ldr').style.display = 'none';
        document.getElementById('st-dot').className = 'dot off';
        log("Tidak dapat terhubung ke server.", 'err');
    });
}
function cancelUpdate() {
    if(!confirm('Batalkan proses instalasi?')) return;
    isCancelled = true;
    if(es) es.close();
    log("Instalasi dibatalkan.", 'err');
    document.getElementById('btn-cn').style.display = 'none';
    document.getElementById('pg-box').style.display = 'none';
    document.getElementById('st-dot').className = 'dot off';
    setTimeout(() => {
        document.getElementById('btn-up').style.display = 'block';
    }, 1500);
}
function startUpdate() {
    if (!upUrl || upUrl.trim() === '') {
        log("URL paket tidak valid.", 'err');
        return;
    }
    if(!confirm('Mulai instalasi pembaruan?')) return;
    if(es) es.close();
    isFinished = false; isCancelled = false;
    document.getElementById('btn-up').style.display = 'none';
    document.getElementById('btn-cn').style.display = 'block';
    document.getElementById('pg-box').style.display = 'block';
    document.getElementById('st-dot').className = 'dot wait';
    document.getElementById('log-box').innerHTML = '';
    log("Memulai proses instalasi...");
    const bar = document.getElementById('pg-in'), pctTxt = document.getElementById('pg-pct'), statusTxt = document.getElementById('pg-txt');
    es = new EventSource('?api=update_stream&url=' + encodeURIComponent(upUrl));
    es.onmessage = function(e) {
        if (isCancelled) return;
        if(e.data === 'end') {
            isFinished = true; es.close(); bar.style.width = '100%'; pctTxt.innerText = '100%';
            statusTxt.innerText = 'Berhasil'; document.getElementById('st-dot').className = 'dot on';
            document.getElementById('btn-cn').style.display = 'none';
            log("Instalasi selesai. Memuat ulang...", 'suc');
            setTimeout(() => location.reload(), 3000); return;
        }
        try {
            const data = JSON.parse(e.data);
            if(data.msg && data.pct === null) log(data.msg);
            if(data.pct !== null) {
                bar.style.width = data.pct + '%'; pctTxt.innerText = data.pct + '%';
                statusTxt.innerText = 'Mengunduh ' + data.pct + '%';
            }
        } catch(err) {}
    };
    es.onerror = function() {
        if (isFinished || isCancelled) return;
        es.close(); document.getElementById('st-dot').className = 'dot off';
        document.getElementById('btn-cn').style.display = 'none';
        statusTxt.innerText = 'Gagal';
        log("Instalasi gagal. Silakan coba lagi.", 'err');
        setTimeout(() => {
            document.getElementById('pg-box').style.display = 'none';
            document.getElementById('btn-up').style.display = 'block';
        }, 4000);
    };
}
window.onload = check;

// ========================================= //
// MANUAL UPDATE FUNCTIONS                   //
// ========================================= //

let verifiedData = null;
let currentTab = 'url';

// Tab switching
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('content-' + tab).classList.add('active');
    resetManualState();
}

// Verify update
async function verifyManual(source) {
    const resultBox = document.getElementById('verify-result');
    const installArea = document.getElementById('manual-install-area');

    resultBox.style.display = 'none';
    resultBox.className = 'verify-result';
    installArea.style.display = 'none';
    verifiedData = null;

    let path = '';
    let btn;

    if (source === 'url') {
        path = document.getElementById('update-url').value.trim();
        btn = document.getElementById('btn-verify-url');
        if (!path) {
            resultBox.innerText = 'URL tidak boleh kosong!';
            resultBox.className = 'verify-result err';
            resultBox.style.display = 'block';
            return;
        }
        if (!path.startsWith('http')) {
            resultBox.innerText = 'URL harus dimulai dengan http:// atau https://';
            resultBox.className = 'verify-result err';
            resultBox.style.display = 'block';
            return;
        }
    } else {
        // Ambil path dari input field
        path = document.getElementById('local-path').value.trim();
        btn = document.getElementById('btn-verify-local');
        if (!path) {
            resultBox.innerText = 'Masukkan path file update.zip!';
            resultBox.className = 'verify-result err';
            resultBox.style.display = 'block';
            return;
        }
        // Validasi path
        if (!path.startsWith('/')) {
            resultBox.innerText = 'Path harus dimulai dengan / (slash)';
            resultBox.className = 'verify-result err';
            resultBox.style.display = 'block';
            return;
        }
        if (!path.endsWith('.zip')) {
            resultBox.innerText = 'File harus berekstensi .zip';
            resultBox.className = 'verify-result err';
            resultBox.style.display = 'block';
            return;
        }
    }

    const origText = btn.innerText;
    btn.innerText = 'Memverifikasi...';
    btn.disabled = true;

    // Update step indicators
    setStepActive(2);

    const fd = new FormData();
    fd.append('source', source);
    fd.append('path', path);

    try {
        const res = await fetch('?api=verify_update', { method: 'POST', body: fd });
        const data = await res.json();

        btn.innerText = origText;
        btn.disabled = false;

        if (data.status === 'ok') {
            resultBox.innerHTML = `<strong>Versi: ${data.ver}</strong><br>${data.msg}`;
            if (data.valid) {
                resultBox.className = 'verify-result succ';
                installArea.style.display = 'block';
                verifiedData = { path: path, source: source, ver: data.ver };
                setStepDone(2);
            } else {
                resultBox.className = 'verify-result err';
            }
        } else {
            resultBox.innerText = data.msg;
            resultBox.className = 'verify-result err';
        }
    } catch (err) {
        btn.innerText = origText;
        btn.disabled = false;
        resultBox.innerText = 'Gagal terhubung ke server.';
        resultBox.className = 'verify-result err';
    }

    resultBox.style.display = 'block';
}

// Install manual update
async function installManual() {
    if (!verifiedData) {
        alert('Harap verifikasi update terlebih dahulu!');
        return;
    }

    const source = verifiedData.source;
    let url = '';

    if (source === 'url') {
        url = '?api=update_stream&url=' + encodeURIComponent(verifiedData.path) + '&type=url';
    } else {
        // Local file - use the local update stream endpoint
        url = '?api=update_local_stream&path=' + encodeURIComponent(verifiedData.path);
    }

    showProgressOverlay();

    let isInstalling = true;
    let installSuccess = false;
    const es = new EventSource(url);

    es.onmessage = function(e) {
        if (!isInstalling) return;

        if (e.data === 'end') {
            es.close();
            isInstalling = false;
            hideProgressOverlay();

            if (installSuccess) {
                alert('Update berhasil diinstall!\n\nLog tersimpan di: /sdcard/ota_update.log');
                setTimeout(() => location.reload(), 500);
            } else {
                alert('Instalasi gagal.\n\nLog error tersedia di: /sdcard/ota_update.log\n\nBuka Terminal dan ketik:\ncat /sdcard/ota_update.log');
            }
            return;
        }

        try {
            const data = JSON.parse(e.data);

            // Update progress bar
            if (data.pct !== null) {
                document.getElementById('prog-bar').style.width = data.pct + '%';
                document.getElementById('prog-pct').innerText = data.pct + '%';
                document.getElementById('prog-text').innerText = 'Menginstal ' + data.pct + '%';

                // Update step indicators
                if (data.pct >= 90) {
                    document.getElementById('ps1').className = 'step-num step-done';
                    document.getElementById('ps1').innerText = '✓';
                    document.getElementById('ps2').className = 'step-num step-done';
                    document.getElementById('ps2').innerText = '✓';
                    document.getElementById('ps3').className = 'step-num step-active';
                    document.getElementById('ps3').innerText = '3';
                } else if (data.pct >= 10) {
                    document.getElementById('ps1').className = 'step-num step-done';
                    document.getElementById('ps1').innerText = '✓';
                    document.getElementById('ps2').className = 'step-num step-active';
                    document.getElementById('ps2').innerText = '2';
                }
            }

            // Track success
            if (data.msg === 'SUKSES' || data.finished === true) {
                installSuccess = true;
                document.getElementById('prog-text').innerText = 'Selesai!';
                document.getElementById('prog-icon').innerText = '✓';
                document.getElementById('prog-bar').style.width = '100%';
                document.getElementById('prog-pct').innerText = '100%';
            }

            // Track error
            if (data.error || (data.msg && data.msg.indexOf('ERROR') === 0)) {
                installSuccess = false;
                document.getElementById('prog-text').innerText = 'Gagal';
                document.getElementById('prog-icon').innerText = '✗';
            }

            // Tampilkan link log
            if (data.log) {
                console.log('Log file: ' + data.log);
            }
        } catch(err) {}
    };

    es.onerror = function() {
        if (!isInstalling) return;
        es.close();
        isInstalling = false;
        hideProgressOverlay();
        alert('Koneksi terputus.\n\nLog tersedia di: /sdcard/ota_update.log');
    };
}

// Progress overlay
function showProgressOverlay() {
    document.getElementById('progress-overlay').classList.add('show');
    document.getElementById('prog-bar').style.width = '0%';
    document.getElementById('prog-pct').innerText = '0%';
    document.getElementById('prog-text').innerText = 'Memulai instalasi...';
    document.getElementById('prog-icon').innerText = '⏳';
    // Reset steps
    document.getElementById('ps1').className = 'step-num step-done';
    document.getElementById('ps1').innerText = '✓';
    document.getElementById('ps2').className = 'step-num';
    document.getElementById('ps2').innerText = '2';
    document.getElementById('ps3').className = 'step-num';
    document.getElementById('ps3').innerText = '3';
}

function hideProgressOverlay() {
    document.getElementById('progress-overlay').classList.remove('show');
}

// Step indicators
function setStepActive(num) {
    for (let i = 1; i <= 3; i++) {
        const el = document.getElementById('sn' + i);
        if (i < num) {
            el.className = 'step-num step-done';
            el.innerText = '✓';
        } else if (i === num) {
            el.className = 'step-num step-active';
            el.innerText = i;
        } else {
            el.className = 'step-num';
            el.innerText = i;
        }
    }
}

function setStepDone(num) {
    const el = document.getElementById('sn' + num);
    el.className = 'step-num step-done';
    el.innerText = '✓';
}

function resetManualState() {
    const resultBox = document.getElementById('verify-result');
    const installArea = document.getElementById('manual-install-area');
    resultBox.style.display = 'none';
    resultBox.className = 'verify-result';
    installArea.style.display = 'none';
    verifiedData = null;
    setStepActive(1);
}
</script>
</body>
</html>