<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
require_once '/data/adb/php8/files/www/utils.php';

if (!is_pro_user()) {
    render_pro_lock_screen('IP Hunter & Airplane Engine');
}

$script_path  = '/data/adb/php8/scripts/airplane/modpes';
$pid_file     = '/data/adb/php8/scripts/airplane/connection.pid';
$log_file     = '/data/adb/php8/scripts/airplane/connection.log';
$onboot_cfg   = '/data/adb/php8/files/config/onboot.cfg';

function exec_root($cmd) {
    $cmd_escaped = str_replace("'", "'\\''", $cmd);
    return trim(shell_exec("su -c '$cmd_escaped' 2>&1"));
}

function read_config($sp) {
    global $onboot_cfg;
    $c = ['url'=>'https://www.gstatic.com/generate_204', 'to'=>7, 'ip'=>'', 'onboot'=>false];
    if (file_exists($sp)) {
        $cnt = file_get_contents($sp);
        if (preg_match("/url=['\"](.*?)['\"]/", $cnt, $m)) $c['url'] = $m[1];
        if (preg_match("/to=(\d+)/", $cnt, $m)) $c['to'] = $m[1];
        if (preg_match("/ip=['\"](.*?)['\"]/", $cnt, $m)) $c['ip'] = $m[1];
    }
    $boot_cnt = exec_root("cat $onboot_cfg");
    if (strpos($boot_cnt, "airplane='1'") !== false || strpos($boot_cnt, "airplane=1") !== false || strpos($boot_cnt, 'airplane="1"') !== false) {
        $c['onboot'] = true;
    }
    return $c;
}

function write_config($sp, $url, $to, $ip, $boot) {
    global $onboot_cfg, $log_file;
    $u = escapeshellcmd($url);
    $i = escapeshellcmd($ip);
    $val = $boot ? '1' : '0';
    exec_root("sed -i \"s|^url=.*|url='$u'|g\" $sp");
    exec_root("sed -i \"s|^to=.*|to=$to|g\" $sp");
    exec_root("sed -i \"s|^ip=.*|ip='$i'|g\" $sp");
    exec_root("mkdir -p /data/adb/php8/files/config");
    $check = shell_exec("grep '^airplane=' $onboot_cfg 2>/dev/null");

    if (empty(trim($check))) {
        exec_root("echo 'airplane=$val' >> $onboot_cfg");
    } else {
        exec_root("sed -i 's|^airplane=.*|airplane=$val|' $onboot_cfg");
    }

    exec_root("chmod 666 $onboot_cfg");

    $status_txt = $boot ? "Enabled" : "Disabled";
    exec_root("echo '['$(date '+%H:%M:%S')'] Auto Boot $status_txt' >> $log_file");
    
    return true;
}

function is_running($pf) {
    if (!file_exists($pf)) return false;
    $pid = trim(file_get_contents($pf));
    return !empty($pid) && file_exists("/proc/$pid");
}

function start_script($sp) {
    exec_root("chmod +x $sp");
    exec("su -c 'sh $sp start' > /dev/null 2>&1 &");
    sleep(1);
}

function stop_script($pf) {
    if (file_exists($pf)) {
        $pid = trim(file_get_contents($pf));
        if (!empty($pid)) { exec_root("kill -15 $pid"); sleep(1); exec_root("kill -9 $pid"); }
        unlink($pf);
    }
}

function get_log($lf) {
    if (!file_exists($lf)) return "Log history is empty.";
    $l = file_get_contents($lf);
    $l = preg_replace_callback('/\[(.*?)\] Active Connection, HTTP=\(204\), latency=\(([\d.]+)(ms)\)/', function($m) {
        $r = min(floatval($m[2])/500, 1.0);
        $c = "rgb(" . min(255, round($r*255)) . "," . max(0, round(255-($r*255))) . ",0)";
        return "[$m[1]] <span style='color:#32d74b;font-weight:800'>Connected</span>, lat=(<span style='color:$c;font-weight:800'>$m[2]</span>$m[3])";
    }, $l);
    $l = preg_replace_callback('/\[(.*?)\] Connection Lost(.*?)/', function($m) {
        return "[$m[1]] <span style='color:#ff3b30;font-weight:800'>Lost</span>$m[2]";
    }, $l);
    return nl2br($l);
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $current_conf = read_config($script_path);
    echo json_encode([
        'status' => is_running($pid_file)?'running':'stopped', 
        'log' => get_log($log_file),
        'onboot' => $current_conf['onboot']
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'configure') {
        $u = $_POST['url'] ?? ''; $t = intval($_POST['timeout'] ?? 7); $i = $_POST['ip'] ?? ''; $b = isset($_POST['onboot']); 
        write_config($script_path, $u, $t, $i, $b);
        echo json_encode(['status'=>'success', 'msg'=>'Config Saved!']);
        exit;
    } elseif ($a === 'toggle_onboot') {
        $val = (isset($_POST['onboot']) && $_POST['onboot'] === 'true') ? '1' : '0';
        exec_root("mkdir -p /data/adb/php8/files/config");
        if (!file_exists($onboot_cfg)) {
            exec_root("touch $onboot_cfg");
        }
        $check = shell_exec("grep -c '^airplane=' $onboot_cfg 2>/dev/null");
        if (trim($check) > 0) {
            exec_root("sed -i 's|^airplane=.*|airplane=$val|' $onboot_cfg");
        } else {
            exec_root("echo 'airplane=$val' >> $onboot_cfg");
        }
        exec_root("chmod 666 $onboot_cfg");
        $status_txt = ($val === '1') ? "Enabled" : "Disabled";
        exec_root("echo '['$(date '+%H:%M:%S')'] Auto Boot $status_txt' >> $log_file");
        echo json_encode(['status' => 'success', 'msg' => 'Auto Boot ' . $status_txt]);
        exit;
    } elseif ($a === 'start') { 
        exec_root("echo '['$(date '+%H:%M:%S')'] Engine Triggered' >> $log_file");
        start_script($script_path); 
    } elseif ($a === 'stop') { 
        exec_root("echo '['$(date '+%H:%M:%S')'] Engine Terminated' >> $log_file");
        stop_script($pid_file); 
    } elseif ($a === 'clear_log') {
        file_put_contents($log_file, '');
        echo "ok";
        exit;
    }
}

$cfg = read_config($script_path);
$run = is_running($pid_file);
$log = get_log($log_file);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>IP Hunter</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root {
            --suc: #32d74b; --dang: #ff3b30; --term: rgba(30, 18, 10, 0.4);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --term: rgba(0, 0, 0, 0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; background: transparent !important; color: var(--text-main); padding: 20px; max-width: 1200px; width: 100%; margin: 0 auto; -webkit-font-smoothing: antialiased; }
        header { text-align: center; margin-bottom: 25px; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); padding-bottom: 20px; }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .sub { font-size: 0.75rem; color: var(--text-sub); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; padding: 25px; box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 25px;
            position: relative; overflow: hidden;
        }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .ti { font-size: 0.95rem; font-weight: 800; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-main); }
        .bdg { padding: 6px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; border: 1px solid var(--border); }
        .run { background: rgba(50, 215, 75, 0.15); color: var(--suc); }
        .stp { background: rgba(0, 0, 0, 0.05); color: var(--text-sub); }
        .grp { margin-bottom: 18px; }
        label { display: block; font-size: 0.75rem; font-weight: 800; margin-bottom: 8px; color: var(--text-sub); text-transform: uppercase; }
        input[type=text], input[type=number] { 
            width: 100%; padding: 14px; border-radius: 14px; border: 1px solid var(--border); 
            background: rgba(255, 255, 255, 0.05); color: var(--text-main); transition: 0.3s; 
            font-family: 'SF Mono', monospace; font-weight: 600; font-size: 0.9rem;
        }
        input:focus { border-color: var(--primary); background: rgba(255,255,255,0.1); }
        .tgl { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-top: 1px dashed rgba(122, 92, 67, 0.2); margin-top: 15px; cursor: pointer; }
        .sw { position: relative; display: inline-block; width: 50px; height: 28px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.1); transition: .4s; border-radius: 34px; border: 1px solid var(--border); }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .sl { background-color: var(--primary); }
        input:checked + .sl:before { transform: translateX(22px); }
        .btns { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .btn { 
            padding: 16px; border: 1px solid var(--border); border-radius: 16px; font-weight: 800; 
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; 
            transition: 0.3s; font-size: 0.85rem; width: 100%; text-transform: uppercase; letter-spacing: 1px;
            color: #fff;
        }
        .btn:active { transform: scale(0.97); }
        .bp { background: var(--primary); box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); border: none; }
        .bd { background: rgba(255, 59, 48, 0.15); color: var(--dang); border-color: rgba(255, 59, 48, 0.3); }
        .bs { background: var(--primary); margin-top: 20px; border: none; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); }
        .act-link { font-size: 0.7rem; font-weight: 800; color: var(--text-sub); text-decoration: none; padding: 6px 12px; border-radius: 12px; border: 1px solid var(--border); transition: 0.3s; }
        .act-link:hover { background: rgba(255, 59, 48, 0.15); color: var(--dang); border-color: rgba(255, 59, 48, 0.3); }
        .term { 
            background: var(--term); color: #FDF5E6; border-radius: 18px; padding: 18px; height: 280px; 
            overflow-y: auto; font-family: 'SF Mono', monospace; font-size: 0.75rem; border: 1px solid var(--border); 
            white-space: pre-wrap; line-height: 1.5;
        }
        .icon { width: 22px; height: 22px; fill: currentColor; }
        #toast { visibility: hidden; min-width: 250px; background: var(--primary); color: #fff; text-align: center; border-radius: 50px; padding: 14px; position: fixed; z-index: 100; bottom: 30px; left: 50%; transform: translateX(-50%); box-shadow: 0 10px 20px rgba(0,0,0,0.2); font-weight: 800; font-size: 0.85rem; opacity: 0; transition: 0.3s; backdrop-filter: blur(10px); }
        #toast.show { visibility: visible; opacity: 1; bottom: 45px; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>
            <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg> 
            Airplane Engine
        </h1>
        <p class="sub">Connection Stabilizer & IP Hunter</p>
    </header>

    <div class="card">
        <div class="head">
            <div class="ti">Daemon Status</div>
            <div class="bdg <?php echo $run?'run':'stp'; ?>" id="sb"><span id="st"><?php echo $run?'ACTIVE':'OFFLINE'; ?></span></div>
        </div>
        <div class="btns">
            <form method="post" style="display:contents" class="act-form">
                <input type="hidden" name="action" value="start">
                <button type="submit" id="b-on" class="btn bp" <?php echo $run?'disabled':'';?>>Enable</button>
            </form>
            <form method="post" style="display:contents" class="act-form">
                <input type="hidden" name="action" value="stop">
                <button type="submit" id="b-off" class="btn bd" <?php echo !$run?'disabled':'';?>>Disable</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="ti" style="margin-bottom:20px; justify-content:center;">Hunter Configuration</div>
        <form method="post" id="cfg-form">
            <input type="hidden" name="action" value="configure">
            <div class="grp">
                <label>Ping Target URL</label>
                <input type="text" name="url" value="<?php echo htmlspecialchars($cfg['url']); ?>" required>
            </div>
            <div class="grp">
                <label>Target IP Range (e.g. 10.0-10.10)</label>
                <input type="text" name="ip" value="<?php echo htmlspecialchars($cfg['ip']); ?>" placeholder="Leave blank for any">
            </div>
            <div class="grp">
                <label>Ping Timeout (Seconds)</label>
                <input type="number" name="timeout" value="<?php echo htmlspecialchars($cfg['to']); ?>" min="1" required>
            </div>
            <label class="tgl">
                <span style="font-weight:800; font-size:0.85rem; color:var(--text-main); text-transform:uppercase;">Auto-Start on Boot</span>
                <div class="sw">
                    <input type="checkbox" name="onboot" id="boot_toggle" <?php echo $cfg['onboot']?'checked':'';?>>
                    <span class="sl"></span>
                </div>
            </label>
            <button type="submit" class="btn bs">Save Configuration</button>
        </form>
    </div>

    <div class="card">
        <div class="head" style="margin-bottom:20px">
            <div class="ti">System Console</div>
            <a href="#" onclick="clr(event)" class="act-link">CLEAR</a>
        </div>
        <div class="term" id="logs"><?= $log ?></div>
    </div>
    <div id="toast">Saved!</div>
</div>

<script>
function up() {
    $.get('?ajax=1', function(d) {
        if(d.status === 'running') {
            $('#sb').removeClass('stp').addClass('run').text('ACTIVE');
            $('#b-on').prop('disabled',true); $('#b-off').prop('disabled',false);
        } else {
            $('#sb').removeClass('run').addClass('stp').text('OFFLINE');
            $('#b-on').prop('disabled',false); $('#b-off').prop('disabled',true);
        }
        var l = $('#logs');
        var b = l[0].scrollHeight - l.scrollTop() <= l.outerHeight() + 50;
        l.html(d.log);
        if(b) l.scrollTop(l[0].scrollHeight);
    });
}
function toast(m) {
    $('#toast').text(m).addClass('show');
    setTimeout(()=>$('#toast').removeClass('show'), 3000);
}
function clr(e) {
    e.preventDefault();
    if(confirm('Clear console history?')) $.post('', {action: 'clear_log'}, function() { up(); });
}
$(document).ready(function() {
    setInterval(up, 2000);
    $('#boot_toggle').change(function() {
        var isChecked = $(this).is(':checked');
        $.post('', { action: 'toggle_onboot', onboot: isChecked }, function() {
            toast(isChecked ? 'Boot enabled' : 'Boot disabled'); up(); 
        });
    });
    $('#cfg-form').submit(function(e) {
        e.preventDefault();
        var b = $(this).find('button'), t = b.html();
        b.prop('disabled',true).html('SAVING...');
        $.ajax({
            type: 'POST', url: '', data: $(this).serialize(),
            success: function(r) { toast('Config saved!'); },
            error: function() { alert('Write error'); },
            complete: function() { b.prop('disabled',false).html(t); }
        });
    });
    $('.act-form').submit(function(e) { e.preventDefault(); $.post('', $(this).serialize(), function() { up(); }); });
});
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>