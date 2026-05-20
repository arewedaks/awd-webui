<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$coreScript = '/data/adb/php8/scripts/onboot/limiter_mac.sh';
$ruleFile   = __DIR__ . '/rules.txt';
$dbFile     = __DIR__ . '/limits_mac.json';
$cfgFile    = '/data/adb/php8/files/config/onboot.cfg';

$data = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
if (!isset($data['limits'])) $data['limits'] = [];
if (!isset($data['global'])) $data['global'] = ['down_rate' => '0mbit', 'down_display' => 'Unlimited', 'up_rate' => '0mbit', 'up_display' => 'Unlimited'];

function applyChanges() {
    global $data, $ruleFile, $coreScript;
    $content = "GLOBAL|" . $data['global']['down_rate'] . "|" . $data['global']['up_rate'] . "\n";
    foreach ($data['limits'] as $mac => $info) { $content .= "$mac|" . $info['down_rate'] . "|" . $info['up_rate'] . "\n"; }
    file_put_contents($ruleFile, $content);
    shell_exec("sh \"$coreScript\" refresh > /dev/null 2>&1 &");
}

function cmd($c) { return shell_exec("su -c \"$c\" 2>&1"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    function makeRate($v, $u) { return ($v == 0) ? '0mbit' : (($u === 'mbps') ? $v.'mbit' : $v.'kbit'); }
    function makeDisplay($v, $u) { return ($v == 0) ? 'Unlimited' : "$v $u"; }

    if ($action === 'set_global') {
        $ds = intval($_POST['down_speed']); $us = intval($_POST['up_speed']);
        $data['global'] = ['down_rate' => makeRate($ds, $_POST['down_unit']), 'down_display' => makeDisplay($ds, $_POST['down_unit']), 'up_rate' => makeRate($us, $_POST['up_unit']), 'up_display' => makeDisplay($us, $_POST['up_unit'])];
        file_put_contents($dbFile, json_encode($data));
        applyChanges();
    }
    if ($action === 'apply_limit') {
        $mac = $_POST['mac']; $ds = intval($_POST['down_speed_user']); $us = intval($_POST['up_speed_user']);
        $data['limits'][$mac] = ['down_rate' => makeRate($ds, $_POST['down_unit_user']), 'down_display' => makeDisplay($ds, $_POST['down_unit_user']), 'up_rate' => makeRate($us, $_POST['up_unit_user']), 'up_display' => makeDisplay($us, $_POST['up_unit_user'])];
        file_put_contents($dbFile, json_encode($data));
        applyChanges();
    }
    if ($action === 'remove_limit') { unset($data['limits'][$_POST['mac']]); file_put_contents($dbFile, json_encode($data)); applyChanges(); }
    if ($action === 'reset_all') { 
        unlink($dbFile); 
        if (file_exists($cfgFile)) {
            $content = file_get_contents($cfgFile);
            if (strpos($content, 'limiter_mac=1') !== false) {
                file_put_contents($cfgFile, str_replace('limiter_mac=1', 'limiter_mac=0', $content));
            }
        }
        shell_exec("sh \"$coreScript\" reset"); 
    }
    if ($action === 'toggle_boot') {
        $enable = ($_POST['enable'] === '1') ? '1' : '0';
        if (!file_exists($cfgFile)) {
            file_put_contents($cfgFile, "limiter_mac=0\n");
        }
        $content = file_get_contents($cfgFile);
        if (strpos($content, 'limiter_mac=1') !== false) {
            $newContent = str_replace('limiter_mac=1', "limiter_mac=$enable", $content);
        } elseif (strpos($content, 'limiter_mac=0') !== false) {
            $newContent = str_replace('limiter_mac=0', "limiter_mac=$enable", $content);
        } else {
            $newContent = trim($content) . "\nlimiter_mac=$enable\n";
        }
        file_put_contents($cfgFile, $newContent);
        chmod($cfgFile, 0666);
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

$clients = []; $foundMACs = [];
$g_disp = "<span style='color:#B87333'>↓ {$data['global']['down_display']}</span> <span style='color:#7A5C43'>↑ {$data['global']['up_display']}</span>";

function addClient($ip, $mac, &$clients, &$foundMACs, $data, $gd) {
    if (!filter_var($mac, FILTER_VALIDATE_MAC) || $mac === '00:00:00:00:00:00') return;
    $mac = strtolower($mac);
    if (!isset($foundMACs[$mac])) {
        $isSpecific = isset($data['limits'][$mac]);
        $disp = $gd;
        if ($isSpecific) { $i = $data['limits'][$mac]; $disp = "<span style='color:#B87333'>↓ {$i['down_display']}</span> <span style='color:#7A5C43'>↑ {$i['up_display']}</span>"; }
        $clients[] = ['ip' => $ip, 'mac' => $mac, 'status' => $isSpecific?'Custom':'Global', 'limit' => $disp];
        $foundMACs[$mac] = true;
    }
}

$arp = cmd("cat /proc/net/arp");
foreach (explode("\n", $arp) as $l) { $c = preg_split('/\s+/', trim($l)); if (count($c) >= 6) addClient($c[0], $c[3], $clients, $foundMACs, $data, $g_disp); }
$neigh = cmd("ip neigh show");
foreach (explode("\n", $neigh) as $l) { if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s+.*lladdr\s+([a-fA-F0-9:]+)/', $l, $m)) addClient($m[1], $m[2], $clients, $foundMACs, $data, $g_disp); }
foreach ($data['limits'] as $mac => $info) { if (!isset($foundMACs[$mac])) { $disp = "<span style='color:#B87333'>↓ {$info['down_display']}</span> <span style='color:#7A5C43'>↑ {$info['up_display']}</span>"; $clients[] = ['ip' => 'Offline', 'mac' => $mac, 'status' => 'Custom', 'limit' => $disp]; $foundMACs[$mac] = true; } }
$cfgContent = file_exists($cfgFile) ? file_get_contents($cfgFile) : '';
$isBootEnabled = (strpos($cfgContent, 'limiter_mac=1') !== false);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bandwidth Manager</title>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; max-width: 800px; margin: 0 auto; -webkit-font-smoothing: antialiased;
        }
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        h2 { font-size: 1.3rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; border: 1px solid var(--border); margin-bottom: 20px; overflow: hidden; box-shadow: var(--shadow);
        }
        .card-h { padding: 15px 20px; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); display: flex; justify-content: space-between; align-items: center; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; }
        .card-b { padding: 20px; }
        .inp-g { display: flex; gap: 10px; margin-bottom: 10px; }
        input, select { 
            width: 100%; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); 
            color: var(--text-main); padding: 12px; border-radius: 14px; font-weight: 600; transition: 0.3s;
        }
        input:focus { border-color: var(--primary); background: rgba(255, 255, 255, 0.1); }
        .btn { width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: 16px; font-weight: 800; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); }
        .btn:active { transform: scale(0.97); }
        .btn-icon { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); color: var(--text-sub); width: 36px; height: 36px; border-radius: 12px; cursor: pointer; display: grid; place-items: center; transition: 0.3s; }
        .btn-icon:hover { background: var(--accent); color: var(--primary); }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 14px 20px; border-bottom: 1px dashed rgba(122, 92, 67, 0.1); text-align: left; font-size: 0.85rem; }
        th { color: var(--text-sub); font-weight: 700; text-transform: uppercase; font-size: 0.7rem; }
        .sw { position: relative; width: 44px; height: 24px; display: inline-block; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.1); border-radius: 30px; transition: .4s; border: 1px solid var(--border); }
        .sl:before { content: ""; height: 18px; width: 18px; left: 2px; bottom: 2px; background: white; border-radius: 50%; position: absolute; transition: .4s; }
        input:checked + .sl { background: var(--primary); }
        input:checked + .sl:before { transform: translateX(20px); }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(10px); z-index: 99; align-items: center; justify-content: center; padding: 20px; }
        .modal-c { background: var(--card-bg); width: 100%; max-width: 400px; padding: 30px; border-radius: 28px; border: 1px solid var(--border); box-shadow: var(--shadow); }
    </style>
</head>
<body>
<div class="container">
    <div class="head">
        <h2><iconify-icon icon="tabler:adjustments-horizontal" style="color:var(--primary); vertical-align:middle;"></iconify-icon> Traffic Control</h2>
    </div>

    <div class="card">
        <div class="card-h">
            <span>Global Policy</span>
            <form method="POST" style="display:flex; align-items:center; gap:12px">
                <input type="hidden" name="action" value="toggle_boot">
                <span style="font-size:0.7rem; font-weight:800; color:var(--text-sub)">AUTO-START</span>
                <label class="sw">
                    <input type="hidden" name="enable" value="0">
                    <input type="checkbox" name="enable" value="1" onchange="this.form.submit()" <?php echo $isBootEnabled?'checked':''; ?>>
                    <span class="sl"></span>
                </label>
            </form>
        </div>
        <div class="card-b">
            <form method="POST">
                <input type="hidden" name="action" value="set_global">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px">
                    <div>
                        <label style="display:block; font-size:0.7rem; font-weight:800; color:var(--text-sub); margin-bottom:8px; text-transform:uppercase;">Download</label>
                        <div class="inp-g">
                            <input type="number" name="down_speed" placeholder="0" required>
                            <select name="down_unit" style="width:75px; flex:none"><option value="mbps">MB</option><option value="kbps">KB</option></select>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.7rem; font-weight:800; color:var(--text-sub); margin-bottom:8px; text-transform:uppercase;">Upload</label>
                        <div class="inp-g">
                            <input type="number" name="up_speed" placeholder="0" required>
                            <select name="up_unit" style="width:75px; flex:none"><option value="mbps">MB</option><option value="kbps">KB</option></select>
                        </div>
                    </div>
                </div>
                <button class="btn">Update Global Limit</button>
            </form>
            <div style="margin-top:20px; font-size:0.85rem; text-align:center; font-weight:700;">
                Current Active Policy: <?php echo $g_disp; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">
            <span>Network Clients (<?php echo count($clients); ?>)</span>
            <form method="POST" onsubmit="return confirm('Clear all database and rules?')">
                <input type="hidden" name="action" value="reset_all">
                <button class="btn-icon" style="color:#ff3b30"><iconify-icon icon="tabler:trash-x"></iconify-icon></button>
            </form>
        </div>
        <div style="overflow-x:auto">
            <table>
                <?php foreach($clients as $c): ?>
                <tr>
                    <td>
                        <div style="font-weight:800; color:var(--text-main)"><?php echo $c['ip']; ?></div>
                        <div style="font-family:monospace; color:var(--text-sub); font-size:0.75rem; font-weight:600;"><?php echo strtoupper($c['mac']); ?></div>
                    </td>
                    <td style="font-weight:700"><?php echo $c['limit']; ?></td>
                    <td style="text-align:right">
                        <?php if($c['status']=='Custom'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="remove_limit">
                                <input type="hidden" name="mac" value="<?php echo $c['mac']; ?>">
                                <button class="btn-icon" style="color:#ff3b30"><iconify-icon icon="tabler:circle-x"></iconify-icon></button>
                            </form>
                        <?php else: ?>
                            <button class="btn-icon" onclick="openMod('<?php echo $c['mac']; ?>')"><iconify-icon icon="tabler:settings-automation"></iconify-icon></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<div id="mod" class="modal">
    <div class="modal-c">
        <h3 style="margin-bottom:10px; font-weight:800; text-transform:uppercase; font-size:1rem;">Device Limit</h3>
        <p id="tMac" style="color:var(--primary); font-family:monospace; margin-bottom:20px; font-weight:700; font-size:0.8rem;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="apply_limit">
            <input type="hidden" name="mac" id="iMac">
            <label style="display:block; font-size:0.7rem; font-weight:800; color:var(--text-sub); margin-bottom:8px; text-transform:uppercase;">Download Speed</label>
            <div class="inp-g">
                <input type="number" name="down_speed_user" id="d_val" placeholder="0" required>
                <select name="down_unit_user" style="width:80px; flex:none"><option value="mbps">MB</option><option value="kbps">KB</option></select>
            </div>
            <label style="display:block; font-size:0.7rem; font-weight:800; color:var(--text-sub); margin-bottom:8px; text-transform:uppercase; margin-top:15px;">Upload Speed</label>
            <div class="inp-g">
                <input type="number" name="up_speed_user" id="u_val" placeholder="0" required>
                <select name="up_unit_user" style="width:80px; flex:none"><option value="mbps">MB</option><option value="kbps">KB</option></select>
            </div>
            <div style="display:flex; gap:12px; margin-top:25px;">
                <button type="button" class="btn" style="background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text-main);" onclick="document.getElementById('mod').style.display='none'">Cancel</button>
                <button type="submit" class="btn">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
function openMod(mac) {
    document.getElementById('tMac').innerText = mac.toUpperCase();
    document.getElementById('iMac').value = mac;
    document.getElementById('d_val').value = '';
    document.getElementById('u_val').value = '';
    document.getElementById('mod').style.display = 'flex';
}
</script>
</body>
</html>