<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
if (file_exists('/data/adb/php8/version.php')) require_once '/data/adb/php8/version.php';
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
        $script = '/data/adb/php8/scripts/process_update.sh';
        $proc = popen("su -c sh \"$script\" \"$url\" 2>&1", 'r');
        if ($proc) {
            while (!feof($proc)) {
                $line = fgets($proc);
                if ($line) {
                    $cleanLine = trim($line); $pct = null;
                    if (preg_match('/(\d{1,3})%/', $cleanLine, $matches)) $pct = intval($matches[1]);
                    echo "data: " . json_encode(['msg' => $cleanLine, 'pct' => $pct]) . "\n\n";
                    flush();
                }
            }
            pclose($proc);
        }
        echo "data: end\n\n"; flush(); exit;
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
        .ldr { display: flex; justify-content: center; align-items: center; gap: 12px; padding: 15px; }
        .sp { width: 22px; height: 22px; border: 3px solid rgba(0,0,0,0.1); border-top: 3px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .lt { font-size: 0.85rem; font-weight: 700; color: var(--text-sub); text-transform: uppercase; }
        .pg-wrap { display: none; width: 100%; margin-top: 15px; }
        .pg-head { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-sub); margin-bottom: 8px; font-weight: 800; text-transform: uppercase; }
        .pg-track { width: 100%; height: 12px; background: rgba(0,0,0,0.1); border-radius: 20px; overflow: hidden; border: 1px solid var(--border); }
        .pg-bar { height: 100%; background: var(--primary); width: 0%; transition: width 0.3s ease; border-radius: 20px; }
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
        <div class="pg-wrap" id="pg-box">
            <div class="pg-head"><span id="pg-txt">Preparing...</span><span id="pg-pct">0%</span></div>
            <div class="pg-track"><div class="pg-bar" id="pg-in"></div></div>
        </div>
    </div>
</div>
<script>
let upUrl = ''; let es = null; let isFinished = false; 
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
                upUrl = d.url; log("NEW UPDATE FOUND:\n" + d.log, 'suc');
            } else {
                document.getElementById('st-dot').className = 'dot on';
                log("System core is up to date.", 'suc');
            }
        } else {
            document.getElementById('st-dot').className = 'dot off';
            document.getElementById('ver-new').innerText = 'Error';
            log("Tele-Engine: " + d.msg, 'err');
        }
    }).catch(() => {
        document.getElementById('ldr').style.display = 'none';
        document.getElementById('st-dot').className = 'dot off';
        log("Connection failure.", 'err');
    });
}
function startUpdate() {
    if(!confirm('Begin firmware installation?')) return;
    if(es) es.close();
    isFinished = false; 
    document.getElementById('btn-up').style.display = 'none';
    document.getElementById('pg-box').style.display = 'block';
    document.getElementById('st-dot').className = 'dot wait';
    document.getElementById('log-box').innerHTML = ''; 
    const bar = document.getElementById('pg-in'), pctTxt = document.getElementById('pg-pct'), statusTxt = document.getElementById('pg-txt');
    es = new EventSource('?api=update_stream&url=' + encodeURIComponent(upUrl));
    es.onmessage = function(e) {
        if(e.data === 'end') {
            isFinished = true; es.close(); bar.style.width = '100%'; pctTxt.innerText = '100%';
            statusTxt.innerText = 'Success'; document.getElementById('st-dot').className = 'dot on';
            log("Installation complete. Restarting UI...", 'suc');
            setTimeout(() => location.reload(), 3000); return;
        }
        try {
            const data = JSON.parse(e.data);
            if(data.msg && data.pct === null) log(data.msg);
            if(data.pct !== null) {
                bar.style.width = data.pct + '%'; pctTxt.innerText = data.pct + '%';
                statusTxt.innerText = 'Downloading ' + data.pct + '%';
            }
        } catch(err) {}
    };
    es.onerror = function() {
        if (isFinished) return;
        es.close(); document.getElementById('st-dot').className = 'dot off';
        statusTxt.innerText = 'Failed'; log("Deployment error.", 'err');
        setTimeout(() => { document.getElementById('pg-box').style.display = 'none'; document.getElementById('btn-up').style.display = 'block'; }, 4000);
    };
}
window.onload = check;
</script>
</body>
</html>