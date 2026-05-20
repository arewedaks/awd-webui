<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

function exec_root($cmd) {
    $cmd_escaped = str_replace("'", "'\\''", $cmd);
    return trim(shell_exec("su -c '$cmd_escaped' 2>&1"));
}

$message = ""; $msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_airplane') {
        $mode = $_POST['mode'];
        if ($mode == '1') {
            $radios = [];
            if (isset($_POST['cell'])) $radios[] = 'cell';
            if (isset($_POST['bluetooth'])) $radios[] = 'bluetooth';
            $radios_str = implode(',', $radios);
            exec_root("settings put global airplane_mode_radios \"$radios_str\"");
            exec_root("settings put global airplane_mode_on 1");
            exec_root("am broadcast -a android.intent.action.AIRPLANE_MODE --ez state true --user 0");
            $net = $_POST['net_pref'] ?? 'hotspot';
            if ($net === 'wifi') { exec_root("cmd connectivity stop-tethering"); exec_root("svc wifi enable"); }
            else { exec_root("svc wifi disable"); exec_root("cmd connectivity start-tethering wifi"); }
            $message = "Airplane Mode ON"; $msg_type = "success";
        } else {
            exec_root("settings put global airplane_mode_on 0");
            exec_root("am broadcast -a android.intent.action.AIRPLANE_MODE --ez state false --user 0");
            $message = "Airplane Mode OFF"; $msg_type = "warning";
        }
    } elseif ($action === 'update_radios') {
        $wifi = isset($_POST['wifi_control']) ? 'enable' : 'disable';
        $bt = isset($_POST['bluetooth_control']) ? 'enable' : 'disable';
        exec_root("svc wifi $wifi"); exec_root("svc bluetooth $bt");
        $message = "Radios Updated"; $msg_type = "success";
    }
}

$device_model = exec_root("getprop ro.product.model");
$airplane_status = exec_root("settings get global airplane_mode_on");
$wifi_on = exec_root("settings get global wifi_on");
$bt_on = exec_root("settings get global bluetooth_on");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Airplane Control</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border-glass: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border-glass: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; background: transparent !important; color: var(--text-main); padding: 20px; max-width: 500px; margin: 0 auto; -webkit-font-smoothing: antialiased; }
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        h1 { font-size: 1.2rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .sub { font-size: 0.75rem; color: var(--text-sub); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; border: 1px solid var(--border-glass); }
        .on { background: rgba(255, 59, 48, 0.15); color: #ff3b30; }
        .off { background: rgba(52, 199, 89, 0.15); color: #32d74b; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            padding: 24px; border-radius: 24px; border: 1px solid var(--border-glass); box-shadow: var(--shadow); margin-bottom: 20px; position: relative; overflow: hidden;
        }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .c-title { font-weight: 800; margin-bottom: 15px; font-size: 0.85rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px; }
        .opt-box { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; background: rgba(0,0,0,0.05); border: 1px solid var(--border-glass); border-radius: 16px; cursor: pointer; }
        .opt-lbl { font-size: 0.8rem; font-weight: 800; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; }
        .sw { position: relative; width: 44px; height: 24px; display: inline-block; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.1); transition: .4s; border-radius: 34px; border: 1px solid var(--border-glass); }
        .sl:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: #fff; transition: .4s; border-radius: 50%; }
        input:checked + .sl { background-color: var(--primary); }
        input:checked + .sl:before { transform: translateX(20px); }
        .rad-grp { display: flex; gap: 10px; margin-bottom: 20px; }
        .rad-lbl { flex: 1; text-align: center; padding: 12px; background: rgba(0,0,0,0.05); border-radius: 14px; cursor: pointer; font-size: 0.8rem; font-weight: 800; color: var(--text-sub); transition: 0.3s; text-transform: uppercase; border: 1px solid var(--border-glass); }
        input[type="radio"]:checked + .rad-lbl { background: var(--primary); color: white; border-color: var(--primary); }
        .btn { width: 100%; padding: 16px; border-radius: 16px; font-weight: 800; cursor: pointer; border: none; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; color: white; font-size: 0.85rem; }
        .btn-p { background: var(--primary); }
        .btn-o { background: rgba(0,0,0,0.1); color: var(--text-main); }
        .alert { padding: 12px; border-radius: 12px; margin-bottom: 20px; font-size: 0.8rem; font-weight: 800; text-align: center; border: 1px solid var(--primary); background: var(--accent); color: var(--primary); }
    </style>
</head>
<body>
    <div class="head">
        <div><h1>Airplane</h1><div class="sub"><?= $device_model ?></div></div>
        <div class="badge <?= ($airplane_status == 1) ? 'on' : 'off' ?>"><?= ($airplane_status == 1) ? 'ACTIVE' : 'INACTIVE' ?></div>
    </div>
    <?php if($message): ?><div class="alert"><?= $message ?></div><?php endif; ?>
    <div class="card">
        <div class="c-title">Flight Control</div>
        <form method="POST">
            <input type="hidden" name="action" value="toggle_airplane">
            <div class="grid">
                <label class="opt-box"><span class="opt-lbl">Keep Cellular</span><div class="sw"><input type="checkbox" name="cell" checked><span class="sl"></span></div></label>
                <label class="opt-box"><span class="opt-lbl">Keep BT</span><div class="sw"><input type="checkbox" name="bluetooth" checked><span class="sl"></span></div></label>
            </div>
            <div class="rad-grp">
                <label style="flex:1"><input type="radio" name="net_pref" value="hotspot" checked style="display:none"><div class="rad-lbl">Hotspot Mode</div></label>
                <label style="flex:1"><input type="radio" name="net_pref" value="wifi" style="display:none"><div class="rad-lbl">Wi-Fi Mode</div></label>
            </div>
            <div class="grid">
                <button type="submit" name="mode" value="1" class="btn btn-p">Takeoff (ON)</button>
                <button type="submit" name="mode" value="0" class="btn btn-o">Land (OFF)</button>
            </div>
        </form>
    </div>
    <div class="card">
        <div class="c-title">Radio Toggles</div>
        <form method="POST">
            <input type="hidden" name="action" value="update_radios">
            <div class="grid">
                <label class="opt-box"><span class="opt-lbl">Wi-Fi</span><div class="sw"><input type="checkbox" name="wifi_control" <?= ($wifi_on == 1) ? 'checked' : '' ?>><span class="sl"></span></div></label>
                <label class="opt-box"><span class="opt-lbl">BT</span><div class="sw"><input type="checkbox" name="bluetooth_control" <?= ($bt_on == 1) ? 'checked' : '' ?>><span class="sl"></span></div></label>
            </div>
            <button type="submit" class="btn btn-p">Update Radios</button>
        </form>
    </div>
</body>
</html>