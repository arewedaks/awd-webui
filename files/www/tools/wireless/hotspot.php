<?php
$message = "";
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
define('BACKEND_SCRIPT', '/data/adb/php8/scripts/hotspot');
define('LOG_FILE', '/data/local/tmp/wifi_log.txt');

$autoHotspotScript = '/data/adb/php8/scripts/onboot/auto_hotspot.sh';
$cfgFile = '/data/adb/php8/files/config/onboot.cfg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_boot') {
        header('Content-Type: application/json');
        $enable = ($_POST['state'] === 'true') ? 1 : 0;
        if (!file_exists($cfgFile)) file_put_contents($cfgFile, '');
        $content = file_get_contents($cfgFile);
        if (strpos($content, 'auto_hotspot=') !== false) {
            $newContent = preg_replace('/auto_hotspot=\d/', "auto_hotspot=$enable", $content);
        } else {
            $newContent = rtrim($content) . "\nauto_hotspot=$enable\n";
        }
        if (file_put_contents($cfgFile, $newContent) !== false) {
            chmod($cfgFile, 0666);
            echo json_encode(['status' => 'success', 'message' => $enable ? 'Auto Start Enabled' : 'Auto Start Disabled']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Write Error']);
        }
        exit;
    }

    if (isset($_POST['save_awd_ip'])) {
        $wifi_ip = $_POST['wifi_ip'] ?? '';
        $usb_ip = $_POST['usb_ip'] ?? '';
        $bt_ip = $_POST['bt_ip'] ?? '';
        $eth_ip = $_POST['eth_ip'] ?? '';
        
        if (!empty($wifi_ip)) shell_exec("su -c \"setprop persist.awdap.wifi_ip " . escapeshellarg($wifi_ip) . "\"");
        if (!empty($usb_ip)) shell_exec("su -c \"setprop persist.awdap.usb_ip " . escapeshellarg($usb_ip) . "\"");
        if (!empty($bt_ip)) shell_exec("su -c \"setprop persist.awdap.bt_ip " . escapeshellarg($bt_ip) . "\"");
        if (!empty($eth_ip)) shell_exec("su -c \"setprop persist.awdap.eth_ip " . escapeshellarg($eth_ip) . "\"");
        
        $message = "IP Configuration applied.";
    }

    if (isset($_POST['save_awd_wifi'])) {
        $ssid = $_POST['ssid'];
        $pass = $_POST['password'];

        if (strlen($pass) < 8) {
            $message = "Failed: Min 8 chars.";
        } else {
            shell_exec("su -c \"setprop persist.awdap.ssid " . escapeshellarg($ssid) . "\"");
            shell_exec("su -c \"setprop persist.awdap.pass " . escapeshellarg($pass) . "\"");
            shell_exec("su -c \"" . BACKEND_SCRIPT . " " . escapeshellarg($ssid) . " " . escapeshellarg($pass) . "\"");
            $message = "AWD Modem Identity applied.";
        }
    }
    if (isset($_POST['save'])) {
        $ssid = $_POST['ssid'];
        $pass = $_POST['password'];
        if (strlen($pass) < 8) {
            $message = "Failed: Min 8 chars.";
        } else {
            shell_exec("su -c \"" . BACKEND_SCRIPT . " " . escapeshellarg($ssid) . " " . escapeshellarg($pass) . "\"");
            $message = "Config applied.";
        }
    }
    if (isset($_POST['restart'])) shell_exec("su -c reboot");
    if (isset($_POST['clear_log'])) {
        shell_exec("su -c \"echo '' > " . LOG_FILE . "\"");
        $message = "Log cleared.";
    }
}

$cfgContent = file_exists($cfgFile) ? file_get_contents($cfgFile) : '';
$is_enabled = (preg_match('/auto_hotspot=1/', $cfgContent) === 1);

function getCurrentConfig() {
    $propSsid = trim(shell_exec("su -c \"getprop persist.awdap.ssid\""));
    $propPass = trim(shell_exec("su -c \"getprop persist.awdap.pass\""));

    $paths = [
        '/data/misc/apexdata/com.android.wifi/WifiConfigStoreSoftAp.xml',
        '/data/misc/wifi/WifiConfigStoreSoftAp.xml',
        '/data/misc/wifi/WifiConfigStore.xml',
        '/data/misc/wifi/softap.conf'
    ];
    $content = "";
    foreach ($paths as $p) {
        $check = shell_exec("su -c \"ls $p 2>/dev/null\"");
        if (!empty(trim($check))) { 
            $content = shell_exec("su -c \"cat $p\""); 
            break; 
        }
    }
    
    $ssid = '';
    $pass = '';

    // Prioritaskan parameter dari module LSPosed (AWD)
    if (!empty($propSsid)) {
        $ssid = $propSsid;
    } else {
        // Parsing XML (A11+) atau conf (A10-)
        if (preg_match('/^ssid=(.*)$/m', $content, $m)) {
            $ssid = trim($m[1]);
        } elseif (preg_match('/<string name="SSID">(.*?)<\/string>/', $content, $s) || preg_match('/<string name="WifiSsid">&quot;(.*?)&quot;<\/string>/', $content, $s)) {
            $ssid = str_replace('&quot;', '', $s[1] ?? '');
        }
    }

    if (!empty($propPass)) {
        $pass = $propPass;
    } else {
        // Parsing XML (A11+) atau conf (A10-)
        if (preg_match('/^wpa_passphrase=(.*)$/m', $content, $m)) {
            $pass = trim($m[1]);
        } elseif (preg_match('/<string name="Passphrase">(.*?)<\/string>/', $content, $p) || preg_match('/<string name="PreSharedKey">&quot;(.*?)&quot;<\/string>/', $content, $p)) {
            $pass = str_replace('&quot;', '', $p[1] ?? '');
        }
    }
    
    return ['ssid' => $ssid, 'pass' => $pass];
}

function getConnectedDevicesDetail() {
    $output = shell_exec('ip neigh');
    $devices = [];
    if ($output) {
        foreach (explode("\n", trim($output)) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 4) {
                $ip = $parts[0]; $mac = 'N/A'; $status = end($parts);
                foreach ($parts as $part) {
                    if (preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $part)) { $mac = $part; break; }
                }
                if ($mac !== 'N/A') $devices[] = ['ip' => $ip, 'mac' => strtoupper($mac), 'status' => strtoupper($status)];
            }
        }
    }
    return $devices;
}

$current = getCurrentConfig();
$deviceList = getConnectedDevicesDetail();
$log_content = shell_exec("su -c \"cat " . LOG_FILE . "\"") ?: "Log empty.";

$awd_wifi_ip = trim(shell_exec("su -c \"getprop persist.awdap.wifi_ip\""));
if (empty($awd_wifi_ip)) $awd_wifi_ip = "192.168.8.1";
$awd_usb_ip = trim(shell_exec("su -c \"getprop persist.awdap.usb_ip\""));
if (empty($awd_usb_ip)) $awd_usb_ip = "192.168.42.1";
$awd_bt_ip = trim(shell_exec("su -c \"getprop persist.awdap.bt_ip\""));
if (empty($awd_bt_ip)) $awd_bt_ip = "192.168.44.1";
$awd_eth_ip = trim(shell_exec("su -c \"getprop persist.awdap.eth_ip\""));
if (empty($awd_eth_ip)) $awd_eth_ip = "192.168.45.1";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hotspot Manager Pro</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; outline: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; max-width: 1100px; margin: 0 auto; -webkit-font-smoothing: antialiased;
        }
        header { text-align: center; margin-bottom: 25px; }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .sub-header { font-size: 0.8rem; color: var(--text-sub); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .layout-wrapper { display: flex; gap: 20px; align-items: flex-start; }
        .col-left  { flex: 1; min-width: 0; }
        .col-right { flex: 1; min-width: 0; }
        @media (max-width: 768px) {
            body { max-width: 600px; }
            .layout-wrapper { flex-direction: column; }
            .col-left, .col-right { width: 100%; flex: none; }
        }
        .tabs { display: flex; gap: 8px; margin-bottom: 25px; background: var(--card-bg); backdrop-filter: var(--blur-val); padding: 6px; border-radius: 50px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .tab { flex: 1; background: transparent; border: none; color: var(--text-sub); padding: 10px; border-radius: 25px; cursor: pointer; font-weight: 700; font-size: 0.85rem; transition: 0.3s; }
        .tab.active { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.3); }
        .view { display: none; }
        .view.active { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .card { background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val); padding: 24px; border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 20px; position: relative; overflow: hidden; }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .grp { margin-bottom: 18px; }
        label { display: block; margin-bottom: 10px; font-size: 0.75rem; font-weight: 800; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; }
        input[type="text"], input[type="number"], input[type="password"] { width: 100%; padding: 14px; border-radius: 14px; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: var(--text-main); font-size: 1rem; font-weight: 600; transition: 0.3s; }
        input:focus { border-color: var(--primary); background: rgba(255,255,255,0.1); }
        .sw-row { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; }
        .sw { position: relative; width: 50px; height: 28px; display: inline-block; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.1); transition: .4s; border-radius: 34px; border: 1px solid var(--border); }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: #fff; transition: .4s; border-radius: 50%; }
        input:checked + .sl { background-color: var(--primary); }
        input:checked + .sl:before { transform: translateX(22px); }
        .btn { width: 100%; padding: 16px; border: 1px solid var(--border); border-radius: 16px; font-weight: 800; cursor: pointer; margin-top: 12px; transition: 0.3s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .bp { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); border: none; }
        .bo { background: rgba(255, 255, 255, 0.05); color: var(--text-main); }
        .bd { background: rgba(255, 59, 48, 0.1); color: #ff3b30; border-color: rgba(255, 59, 48, 0.2); }
        .btn:active { transform: scale(0.97); }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 14px 8px; border-bottom: 1px dashed rgba(122, 92, 67, 0.15); text-align: left; }
        th { color: var(--text-sub); font-weight: 800; text-transform: uppercase; font-size: 0.7rem; }
        .log { background: rgba(30, 18, 10, 0.4); color: #FDF5E6; padding: 18px; border-radius: 16px; font-family: 'SF Mono', monospace; font-size: 0.75rem; white-space: pre-wrap; height: 280px; overflow-y: auto; border: 1px solid var(--border); margin-bottom: 20px; }
        .alert { background: var(--accent); color: var(--primary); padding: 14px; border-radius: 14px; margin-bottom: 20px; text-align: center; font-weight: 800; border: 1px solid var(--primary); font-size: 0.85rem; }
        #toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: var(--primary); color: #fff; padding: 12px 25px; border-radius: 30px; font-size: 0.85rem; font-weight: 700; opacity: 0; transition: 0.3s; z-index: 100; backdrop-filter: blur(10px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        #toast.show { opacity: 1; bottom: 45px; }
        .boot-status { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.5px; }
        .boot-on  { background: rgba(50,215,75,0.15);  color: #32d74b; border: 1px solid rgba(50,215,75,0.3); }
        .boot-off { background: rgba(180,180,180,0.1); color: var(--text-sub); border: 1px solid var(--border); }
    </style>
</head>
<body>
    <header>
        <h1>Hotspot Manager</h1>
        <div class="sub-header">Autumn Tethering Engine</div>
    </header>

    <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="tabs">
        <button class="tab active" onclick="sw('set')" id="b-set">Settings</button>
        <button class="tab" onclick="sw('dev')" id="b-dev">Clients</button>
        <button class="tab" onclick="sw('log')" id="b-log">System Log</button>
    </div>

    <div id="v-set" class="view active">
        <div class="layout-wrapper">
            <div class="col-left">
                <div class="card">
                    <div class="sw-row">
                        <div>
                            <div style="font-weight:800; font-size:0.9rem; text-transform:uppercase; margin-bottom:4px;">Auto-Boot Manager</div>
                            <span class="boot-status <?= $is_enabled ? 'boot-on' : 'boot-off' ?>" id="bootBadge"><?= $is_enabled ? 'ENABLED' : 'DISABLED' ?></span>
                        </div>
                        <label class="sw">
                            <input type="checkbox" id="bt" <?= $is_enabled ? 'checked' : '' ?>>
                            <span class="sl"></span>
                        </label>
                    </div>
                    <form method="POST" style="margin-top:20px">
                        <div style="font-weight:800; font-size:0.8rem; text-transform:uppercase; margin-bottom:15px; color:var(--primary);">AWD IP Gateways</div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:12px;">
                            <div><label>WiFi</label><input type="text" name="wifi_ip" value="<?= htmlspecialchars($awd_wifi_ip) ?>" style="padding:10px; font-size:0.9rem;"></div>
                            <div><label>USB</label><input type="text" name="usb_ip" value="<?= htmlspecialchars($awd_usb_ip) ?>" style="padding:10px; font-size:0.9rem;"></div>
                            <div><label>Bluetooth</label><input type="text" name="bt_ip" value="<?= htmlspecialchars($awd_bt_ip) ?>" style="padding:10px; font-size:0.9rem;"></div>
                            <div><label>Ethernet</label><input type="text" name="eth_ip" value="<?= htmlspecialchars($awd_eth_ip) ?>" style="padding:10px; font-size:0.9rem;"></div>
                        </div>
                        <div style="font-size:0.65rem; color:var(--text-sub); margin-bottom:10px;">Format: 192.168.x.1 (Subnet /24 ditambahkan otomatis).</div>
                        <button type="submit" name="save_awd_ip" class="btn bp" style="padding:12px; margin-top:0;">Update IPs</button>
                    </form>
                </div>
            </div>
            <div class="col-right">
                <div class="card">
                    <form method="POST">
                        <div style="font-weight:800; font-size:0.8rem; text-transform:uppercase; margin-bottom:15px; color:var(--primary);">Hotspot Identity</div>
                        <div class="grp"><label>Hotspot SSID</label><input type="text" name="ssid" value="<?= htmlspecialchars($current['ssid']) ?>" required></div>
                        <div class="grp"><label>Security Password</label><input type="text" name="password" value="<?= htmlspecialchars($current['pass']) ?>" required></div>
                        <button type="submit" name="save_awd_wifi" class="btn bp">Update Identity</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Full system reboot?')" style="margin-top:0">
                        <button type="submit" name="restart" class="btn bo">Reboot System</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="v-dev" class="view">
        <div class="card">
            <table>
                <thead><tr><th>IP Address</th><th>MAC Address</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (empty($deviceList)): ?>
                        <tr><td colspan="3" style="text-align:center; padding:40px; color:var(--text-sub); font-weight:700;">No connected devices</td></tr>
                    <?php else: foreach ($deviceList as $d): ?>
                        <tr>
                            <td style="font-family:'SF Mono',monospace; font-weight:600;"><?= htmlspecialchars($d['ip']) ?></td>
                            <td style="font-family:'SF Mono',monospace; font-size:0.75rem;"><?= htmlspecialchars($d['mac']) ?></td>
                            <td style="color:var(--primary); font-weight:800; font-size:0.7rem;"><?= htmlspecialchars($d['status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <button onclick="location.reload()" class="btn bo" style="margin-top:20px">Refresh List</button>
        </div>
    </div>

    <div id="v-log" class="view">
        <div class="card">
            <div class="log"><?= htmlspecialchars($log_content) ?></div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <button onclick="location.reload()" class="btn bo">Reload</button>
                <form method="POST" style="margin:0"><button type="submit" name="clear_log" class="btn bd">Clear</button></form>
            </div>
        </div>
    </div>

    <div id="toast">Saved!</div>

    <script>
        const t = document.getElementById("toast");
        function msg(m) { t.innerText = m; t.className = "show"; setTimeout(() => t.className = "", 3000); }

        function sw(v) {
            document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(e => e.classList.remove('active'));
            document.getElementById('v-'+v).classList.add('active');
            document.getElementById('b-'+v).classList.add('active');
        }

        document.getElementById('bt').addEventListener('change', function() {
            const s = this.checked;
            const fd = new FormData();
            fd.append('action', 'toggle_boot');
            fd.append('state', s ? 'true' : 'false');
            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    msg(d.message);
                    if (d.status === 'error') {
                        this.checked = !s;
                    } else {
                        const badge = document.getElementById('bootBadge');
                        badge.textContent = s ? 'ENABLED' : 'DISABLED';
                        badge.className = 'boot-status ' + (s ? 'boot-on' : 'boot-off');
                    }
                })
                .catch(() => { msg("System Error"); this.checked = !s; });
        });


    </script>
<script src="/assets/js/main.js"></script>
</body>
</html>