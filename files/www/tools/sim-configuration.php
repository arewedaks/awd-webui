<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$isAjax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

function executeCommand($command) {
    return shell_exec($command . " 2>&1");
}

$simMap = [];
$simInfoRaw = executeCommand('su -c "content query --uri content://telephony/siminfo --projection _id,sim_id,display_name,mcc_string,mnc_string"');

if ($simInfoRaw) {
    $lines = explode("\n", trim($simInfoRaw));
    foreach ($lines as $line) {
        $subId = null; $simId = null; $name = 'Unknown'; $mcc = ''; $mnc = '';
        if (preg_match('/_id=(\d+)/', $line, $m)) $subId = $m[1];
        if (preg_match('/sim_id=(\d+)/', $line, $m)) $simId = intval($m[1]);

        if (preg_match('/display_name=([^,]+)/', $line, $m)) $name = trim($m[1]);
        if (preg_match('/mcc_string=(\d+)/', $line, $m)) $mcc = $m[1];
        if (preg_match('/mnc_string=(\d+)/', $line, $m)) $mnc = $m[1];
        $numeric = ($mcc !== '' && $mnc !== '') ? $mcc . $mnc : null;
        if ($subId !== null && $simId !== null) {
            $simMap[$simId] = ['subId' => $subId, 'name' => $name, 'numeric' => $numeric];
        }
    }
}

$ui_sim_tab = isset($_REQUEST['ui_sim_id']) ? intval($_REQUEST['ui_sim_id']) : 1; 
$target_slot = $ui_sim_tab - 1; 
$target_subId = isset($simMap[$target_slot]['subId']) ? $simMap[$target_slot]['subId'] : null;
$target_numeric = isset($simMap[$target_slot]['numeric']) ? $simMap[$target_slot]['numeric'] : null;

$uri_carriers = "content://telephony/carriers";
$uri_prefer = $target_subId ? "content://telephony/carriers/preferapn/subId/$target_subId" : "content://telephony/carriers/preferapn";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionTaken = false;
    if (isset($_POST['switch_data_subid']) && !empty($_POST['switch_data_subid'])) {
        $subId = intval($_POST['switch_data_subid']);
        // Use our SoftApHelper BroadcastReceiver to switch default data & toggle connection (Zero Overhead)
        executeCommand("su -c \"am broadcast -a com.awd.modemtools.SET_DATA_SIM --ei subId $subId\"");
        $actionTaken = true;
    }
    if (isset($_POST['set_apn']) && isset($_POST['apn_id'])) {
        $id = escapeshellarg($_POST['apn_id']);
        executeCommand("su -c \"content update --uri $uri_prefer --bind apn_id:i:$id\"");
        $actionTaken = true;
    }
    if (isset($_POST['delete_apn']) && isset($_POST['apn_id'])) {
        $id = escapeshellarg($_POST['apn_id']);
        executeCommand("su -c \"content delete --uri $uri_carriers --where \\\"_id=$id\\\"\"");
        $actionTaken = true;
    }
    if (isset($_POST['add_apn'])) {
        $esc = function($v){ return escapeshellarg($v); };
        $binds = "name:s:{$esc($_POST['name'])} --bind apn:s:{$esc($_POST['apn'])} " .
                 "--bind proxy:s:{$esc($_POST['proxy']??'')} --bind port:s:{$esc($_POST['port']??'')} " .
                 "--bind user:s:{$esc($_POST['user']??'')} --bind password:s:{$esc($_POST['password']??'')} " .
                 "--bind server:s:{$esc($_POST['server']??'')} --bind mmsc:s:{$esc(str_replace('://','\\://',$_POST['mmsc']??''))} " .
                 "--bind mmsproxy:s:{$esc($_POST['mmsproxy']??'')} --bind mmsport:s:{$esc($_POST['mmsport']??'')} " .
                 "--bind authtype:s:{$esc($_POST['authtype']??'-1')} --bind type:s:{$esc($_POST['type']??'default,supl')} " .
                 "--bind protocol:s:{$esc($_POST['protocol']??'IPv4')} --bind roaming_protocol:s:{$esc($_POST['roamingprotocol']??'IPv4')} " .
                 "--bind current:i:1";
        if ($target_numeric) $binds .= " --bind numeric:s:{$esc($target_numeric)}";
        if ($target_subId) $binds .= " --bind sub_id:i:$target_subId";
        executeCommand("su -c \"content insert --uri $uri_carriers --bind $binds\"");
        $actionTaken = true;
    }
    if (isset($_POST['edit_apn'])) {
        $id = escapeshellarg($_POST['id']);
        $esc = function($v){ return escapeshellarg($v); };
        $binds = "name:s:{$esc($_POST['name'])} --bind apn:s:{$esc($_POST['apn'])} " .
                 "--bind proxy:s:{$esc($_POST['proxy']??'')} --bind port:s:{$esc($_POST['port']??'')} " .
                 "--bind user:s:{$esc($_POST['user']??'')} --bind password:s:{$esc($_POST['password']??'')} " .
                 "--bind server:s:{$esc($_POST['server']??'')} --bind mmsc:s:{$esc(str_replace('://','\\://',$_POST['mmsc']??''))} " .
                 "--bind mmsproxy:s:{$esc($_POST['mmsproxy']??'')} --bind mmsport:s:{$esc($_POST['mmsport']??'')} " .
                 "--bind authtype:s:{$esc($_POST['authtype']??'-1')} --bind type:s:{$esc($_POST['type']??'')} " .
                 "--bind protocol:s:{$esc($_POST['protocol']??'IPv4')} --bind roaming_protocol:s:{$esc($_POST['roamingprotocol']??'IPv4')}";
        executeCommand("su -c \"content update --uri $uri_carriers --where \\\"_id=$id\\\" --bind $binds\"");
        $actionTaken = true;
    }
    if (isset($_POST['reset_apn'])) {
        $where = "current=1";
        if ($target_numeric) $where .= " AND numeric='$target_numeric'";
        if ($target_subId) $where .= " AND sub_id=$target_subId";
        executeCommand("su -c \"content delete --uri $uri_carriers --where \\\"$where\\\"\"");
        executeCommand('su -c "killall com.android.phone"');
        sleep(2);
        $actionTaken = true;
    }
    if ($actionTaken && !$isAjax) {
        header("Location: ?ui_sim_id=" . $ui_sim_tab); 
        exit();
    }
}

$currentApnId = null;
$prefOut = executeCommand("su -c \"content query --uri $uri_prefer\"");
if (preg_match('/_id=(\d+)/', $prefOut, $m)) $currentApnId = $m[1];
elseif (preg_match('/_id\s*:\s*(\d+)/', $prefOut, $m)) $currentApnId = $m[1];

$apnList = [];
$whereClause = "type!='ims'";
if ($target_numeric) {
    if ($target_subId) $whereClause .= " AND (numeric='$target_numeric' OR sub_id=$target_subId)";
    else $whereClause .= " AND numeric='$target_numeric'";
}
$output = executeCommand("su -c \"content query --uri $uri_carriers --where \\\"$whereClause\\\" --projection _id,name,apn,numeric,type\"");
if ($output) {
    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (preg_match('/_id=(\d+)/', $line, $idM) && preg_match('/name=([^,]*)/', $line, $nM)) {
            $apnM = preg_match('/apn=([^,]*)/', $line, $m) ? $m[1] : '';
            $apnList[] = ['id' => $idM[1], 'name' => trim($nM[1]), 'apn' => trim($apnM)];
        }
    }
}

$editApnData = [];
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $out = executeCommand("su -c \"content query --uri $uri_carriers --where \\\"_id=$id\\\"\"");
    if ($out) {
        $parts = preg_split('/,\s+/', trim($out));
        foreach($parts as $p) {
            $kv = explode('=', $p, 2);
            if(count($kv)==2) $editApnData[trim($kv[0])] = trim($kv[1]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Network Manager</title>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; max-width: 1200px; margin: 0 auto; padding-bottom: 100px; -webkit-font-smoothing: antialiased; width: 100%;
        }
        h3 { font-weight: 800; margin-bottom: 15px; font-size: 1.1rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; padding: 20px; margin-bottom: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); 
            position: relative; overflow: hidden;
        }
        .card-header { padding: 15px 20px; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); font-weight: 800; display: flex; justify-content: space-between; align-items: center; color: var(--text-main); text-transform: uppercase; font-size: 0.9rem; }
        .sim-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .sim-box { 
            background: rgba(255, 255, 255, 0.05); border: 2px solid transparent; border-radius: 18px; 
            padding: 15px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: 0.3s;
        }
        .sim-box.active { border-color: var(--primary); background: var(--accent); }
        .sim-box.disabled { opacity: 0.3; pointer-events: none; }
        .sim-icon { font-size: 24px; color: var(--text-sub); }
        .sim-box.active .sim-icon { color: var(--primary); }
        .sim-info { display: flex; flex-direction: column; }
        .sim-name { font-weight: 800; font-size: 0.9rem; }
        .sim-num { font-size: 0.7rem; color: var(--text-sub); font-weight: 600; }
        .apn-item { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 16px 20px; border-bottom: 1px dashed rgba(122, 92, 67, 0.1); 
            background: transparent; transition: 0.2s; 
        }
        .apn-item:last-child { border-bottom: none; }
        .apn-item.active { background: rgba(255, 255, 255, 0.05); }
        .radio-wrapper { display: flex; align-items: center; gap: 15px; flex: 1; cursor: pointer; }
        .custom-radio { 
            width: 22px; height: 22px; border-radius: 50%; border: 2px solid var(--border); 
            display: flex; align-items: center; justify-content: center; transition: 0.3s; 
        }
        .apn-item.active .custom-radio { border-color: var(--primary); }
        .apn-item.active .custom-radio::after { content: ''; width: 12px; height: 12px; background: var(--primary); border-radius: 50%; }
        .apn-text div:first-child { font-weight: 800; font-size: 0.95rem; }
        .apn-text div:last-child { font-size: 0.75rem; color: var(--text-sub); font-weight: 600; }
        .apn-actions { display: flex; gap: 8px; }
        .icon-btn { 
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; 
            border-radius: 12px; border: none; background: rgba(255, 255, 255, 0.05); color: var(--text-sub); 
            cursor: pointer; font-size: 1.2rem; transition: 0.3s; border: 1px solid var(--border);
        }
        .icon-btn:hover { background: var(--accent); color: var(--primary); }
        .icon-btn.danger:hover { background: rgba(255, 59, 48, 0.15); color: #ff3b30; border-color: rgba(255, 59, 48, 0.3); }
        .form-group { margin-bottom: 18px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        label { display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-sub); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        input, select { 
            width: 100%; padding: 14px; border-radius: 14px; border: 1px solid var(--border); 
            background: rgba(255, 255, 255, 0.05); color: var(--text-main); font-size: 0.95rem; transition: 0.3s; font-weight: 600;
        }
        input:focus, select:focus { border-color: var(--primary); background: rgba(255, 255, 255, 0.1); }
        .btn-row { display: flex; gap: 12px; margin-top: 25px; }
        .btn { 
            flex: 1; padding: 16px; border-radius: 16px; border: none; font-weight: 800; 
            font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s;
        }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); }
        .btn-sec { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); color: var(--text-main); }
        .btn:active { transform: scale(0.97); }
        .fab { position: fixed; bottom: 30px; right: 25px; width: 60px; height: 60px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; box-shadow: 0 10px 20px rgba(184, 115, 51, 0.3); cursor: pointer; transition: 0.3s; z-index: 50; }
        .fab-reset { position: fixed; bottom: 105px; right: 32px; width: 46px; height: 46px; background: var(--card-bg); backdrop-filter: var(--blur-val); color: var(--text-main); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); cursor: pointer; z-index: 49; }
        .loader { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 100; }
        .spinner { width: 45px; height: 45px; border: 4px solid var(--primary); border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div id="app">
    <?php if (!isset($_GET['action'])): ?>
    <div class="card">
        <h3>Network SIM</h3>
        <form method="POST" id="simForm">
            <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
            <input type="hidden" name="switch_data_subid" id="switch_data_subid" value="">
            <div class="sim-grid">
                <?php foreach([1,2] as $n): 
                    $idx = $n-1; $isActive = ($ui_sim_tab == $n); $hasSim = isset($simMap[$idx]); 
                ?>
                <div class="sim-box <?= $isActive ? 'active' : '' ?> <?= !$hasSim ? 'disabled' : '' ?>" onclick="<?= $hasSim ? "selectSim($n, " . ($simMap[$idx]['subId'] ?? 'null') . ")" : '' ?>">
                    <iconify-icon icon="ic:round-sim-card" class="sim-icon"></iconify-icon>
                    <div class="sim-info">
                        <div class="sim-name"><?= $hasSim ? htmlspecialchars($simMap[$idx]['name']) : 'Slot '.$n ?></div>
                        <div class="sim-num"><?= $hasSim ? ($simMap[$idx]['numeric']??'Ready') : 'Empty' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
    <div class="card" style="padding:0">
        <div class="card-header">
            <span>Configured APN</span>
            <span style="font-size:0.7rem; background:var(--accent); color:var(--primary); padding:4px 12px; border-radius:10px; border:1px solid var(--border)">
                <?= htmlspecialchars($simMap[$target_slot]['name'] ?? 'SIM '.$ui_sim_tab) ?>
            </span>
        </div>
        <?php if(empty($apnList)): ?>
            <div style="padding:50px 20px; text-align:center; color:var(--text-sub); font-weight:600;">
                <iconify-icon icon="tabler:database-off" style="font-size:40px; opacity:0.3; margin-bottom:10px"></iconify-icon>
                <div>No APN Data Available</div>
            </div>
        <?php else: foreach($apnList as $apn): $act = ($apn['id'] == $currentApnId); ?>
            <div class="apn-item <?= $act ? 'active' : '' ?>">
                <form method="POST" class="radio-wrapper" onclick="this.submit()">
                    <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
                    <input type="hidden" name="set_apn" value="1">
                    <input type="hidden" name="apn_id" value="<?= $apn['id'] ?>">
                    <div class="custom-radio"></div>
                    <div class="apn-text">
                        <div><?= htmlspecialchars($apn['name']) ?></div>
                        <div><?= htmlspecialchars($apn['apn']) ?></div>
                    </div>
                </form>
                <div class="apn-actions">
                    <div onclick="editApn(<?= $apn['id'] ?>)" class="icon-btn"><iconify-icon icon="tabler:pencil"></iconify-icon></div>
                    <form method="POST" onsubmit="return confirm('Delete this APN?');" style="display:inline">
                        <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
                        <input type="hidden" name="delete_apn" value="1">
                        <input type="hidden" name="apn_id" value="<?= $apn['id'] ?>">
                        <button class="icon-btn danger"><iconify-icon icon="tabler:trash"></iconify-icon></button>
                    </form>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <div onclick="addApn()" class="fab"><iconify-icon icon="tabler:plus"></iconify-icon></div>
    <form method="POST" onsubmit="return confirm('Reset APN to factory defaults?')" style="display:inline">
        <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
        <button name="reset_apn" class="fab-reset"><iconify-icon icon="tabler:refresh"></iconify-icon></button>
    </form>
    <?php else: 
        $action = $_GET['action']; $title = ($action == 'edit') ? 'Edit Config' : 'New Config'; $d = ($action == 'edit') ? $editApnData : [];
    ?>
    <div class="card">
        <h3><?= $title ?></h3>
        <form method="POST" onsubmit="showL()">
            <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
            <?php if($action=='edit'): ?><input type="hidden" name="id" value="<?= $_GET['id'] ?>"><?php endif; ?>
            <div class="form-group"><label>Profile Name</label><input type="text" name="name" value="<?= $d['name']??'' ?>" required></div>
            <div class="form-group"><label>Access Point (APN)</label><input type="text" name="apn" value="<?= $d['apn']??'' ?>" required></div>
            <div class="form-row">
                <div class="form-group"><label>Proxy</label><input type="text" name="proxy" value="<?= $d['proxy']??'' ?>"></div>
                <div class="form-group"><label>Port</label><input type="text" name="port" value="<?= $d['port']??'' ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Username</label><input type="text" name="user" value="<?= $d['user']??'' ?>"></div>
                <div class="form-group"><label>Password</label><input type="text" name="password" value="<?= $d['password']??'' ?>"></div>
            </div>
            <div class="form-group"><label>Authentication</label>
                <select name="authtype">
                    <option value="-1">None</option>
                    <option value="1" <?= ($d['authtype']??'')=='1'?'selected':'' ?>>PAP</option>
                    <option value="2" <?= ($d['authtype']??'')=='2'?'selected':'' ?>>CHAP</option>
                    <option value="3" <?= ($d['authtype']??'')=='3'?'selected':'' ?>>PAP/CHAP</option>
                </select>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-sec" onclick="window.location.href='?ui_sim_id=<?= $ui_sim_tab ?>'">Cancel</button>
                <button type="submit" name="<?= $action ?>_apn" class="btn btn-primary">Save Config</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
<div class="loader" id="ldr"><div class="spinner"></div></div>
<script>
function showL() { document.getElementById('ldr').style.display='flex'; }
function selectSim(n, subId) { 
    showL(); 
    const f = document.getElementById('simForm'); 
    f.querySelector('input[name="ui_sim_id"]').value = n; 
    if (subId !== null) f.querySelector('#switch_data_subid').value = subId;
    f.submit(); 
}
function addApn() { window.location.href = "?action=add&ui_sim_id=" + <?= $ui_sim_tab ?>; }
function editApn(id) { window.location.href = "?action=edit&id=" + id + "&ui_sim_id=" + <?= $ui_sim_tab ?>; }
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>