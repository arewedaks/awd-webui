<?php
$message = "";
require_once '/data/adb/php8/files/www/auth/auth_functions.php'; 
$targetDir = '/data/adb/php8/scrips/onboot';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scriptPath = $_POST['script_path'] ?? '';
    $pids = $_POST['pids'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (!empty($scriptPath)) {
        if ($action === 'kill') {
            if (!empty($pids)) {
                $pidList = explode(',', $pids);
                foreach ($pidList as $pid) { shell_exec("su -c \"kill -9 " . trim($pid) . "\""); }
                $message = "Killed PIDs: $pids";
            } else {
                $name = basename($scriptPath);
                shell_exec("su -c \"pkill -f '$name'\"");
                $message = "Force Kill sent.";
            }
            sleep(1);
        }
        if ($action === 'start') {
            shell_exec("su -c \"nohup sh " . escapeshellarg($scriptPath) . " > /dev/null 2>&1 &\"");
            sleep(2);
            $message = "Service started.";
        }
    }
}

$services = [];
$raw_processes = shell_exec("su -c \"ps -ef\"");
if (empty($raw_processes) || strlen($raw_processes) < 50) { $raw_processes = shell_exec("su -c \"ps -A\""); }
$raw_files = shell_exec("su -c \"ls $targetDir/*.sh 2>/dev/null\"");

if (!empty(trim($raw_files))) {
    $files = explode("\n", trim($raw_files));
    $processLines = explode("\n", $raw_processes);
    foreach ($files as $file) {
        if (empty(trim($file))) continue;
        $fileName = basename($file);
        $foundPids = [];
        foreach ($processLines as $line) {
            if (strpos($line, 'grep') !== false || strpos($line, 'monitor.php') !== false) continue;
            if (strpos($line, $fileName) !== false) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) > 1) {
                    $pidCandidate = $parts[1]; 
                    if (is_numeric($pidCandidate)) { $foundPids[] = $pidCandidate; }
                }
            }
        }
        $isRunning = !empty($foundPids);
        $services[] = [
            'path' => $file,
            'name' => $fileName,
            'running' => $isRunning,
            'pid' => $isRunning ? implode(",", array_unique($foundPids)) : '-'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Service Auditor</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --suc: #32d74b; --dang: #ff3b30;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; max-width: 600px; margin: 0 auto; -webkit-font-smoothing: antialiased;
        }
        .header { text-align: center; margin-bottom: 25px; }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .sub-t { font-size: 0.75rem; color: var(--text-sub); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .alert { background: var(--accent); color: var(--primary); padding: 12px; border-radius: 14px; margin-bottom: 20px; text-align: center; font-weight: 800; border: 1px solid var(--primary); font-size: 0.85rem; animation: fadeIn 0.4s; }
        .svc-item { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            padding: 18px 22px; border-radius: 24px; border: 1px solid var(--border); margin-bottom: 15px; 
            display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow);
            position: relative; overflow: hidden; transition: 0.3s;
        }
        .svc-item::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .svc-info { display: flex; flex-direction: column; flex: 1; margin-right: 15px; }
        .svc-name { font-weight: 800; font-size: 1rem; color: var(--text-main); word-break: break-all; }
        .svc-pid { font-size: 0.75rem; color: var(--text-sub); margin-top: 6px; font-family: 'SF Mono', monospace; font-weight: 600; }
        .badge { font-size: 0.65rem; padding: 4px 10px; border-radius: 20px; margin-left: 8px; text-transform: uppercase; font-weight: 900; letter-spacing: 0.5px; border: 1px solid var(--border); }
        .b-run { background: rgba(50, 215, 75, 0.15); color: var(--suc); }
        .b-stop { background: rgba(0, 0, 0, 0.05); color: var(--text-sub); }
        .btn { padding: 12px 18px; min-width: 90px; border: 1px solid var(--border); border-radius: 14px; font-weight: 800; cursor: pointer; text-transform: uppercase; font-size: 0.75rem; transition: 0.3s; letter-spacing: 0.5px; }
        .btn-kill { background: rgba(255, 59, 48, 0.15); color: var(--dang); border-color: rgba(255, 59, 48, 0.3); }
        .btn-kill:active { transform: scale(0.96); background: var(--dang); color: white; }
        .btn-start { background: var(--primary); color: white; border: none; box-shadow: 0 4px 12px rgba(184, 115, 51, 0.2); }
        .btn-start:active { transform: scale(0.96); }
        .refresh { 
            width: 100%; padding: 16px; background: rgba(255, 255, 255, 0.05); color: var(--text-main); 
            border: 1px solid var(--border); border-radius: 18px; margin-top: 20px; 
            font-weight: 800; font-size: 0.85rem; cursor: pointer; text-transform: uppercase; 
            letter-spacing: 1px; transition: 0.3s; backdrop-filter: var(--blur-val);
        }
        .refresh:active { transform: scale(0.98); background: var(--accent); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Service Auditor</h1>
        <div class="sub-t">Daemon & Boot Manager</div>
    </div>
    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (empty($services)): ?>
        <div style="text-align:center; padding:50px 20px; color:var(--text-sub); font-weight:700; border: 2px dashed var(--border); border-radius:24px; text-transform:uppercase; font-size:0.8rem;">
            No .sh scripts detected in service.d
        </div>
    <?php else: foreach ($services as $svc): ?>
        <div class="svc-item">
            <div class="svc-info">
                <div>
                    <span class="svc-name"><?= htmlspecialchars($svc['name']) ?></span>
                    <span class="badge <?= $svc['running'] ? 'b-run' : 'b-stop' ?>"><?= $svc['running'] ? 'RUNNING' : 'STOPPED' ?></span>
                </div>
                <span class="svc-pid">Instance PID: <?= $svc['pid'] ?></span>
            </div>
            <form method="POST">
                <input type="hidden" name="script_path" value="<?= htmlspecialchars($svc['path']) ?>">
                <input type="hidden" name="pids" value="<?= htmlspecialchars($svc['pid']) ?>">
                <?php if ($svc['running']): ?>
                    <button type="submit" name="action" value="kill" class="btn btn-kill" onclick="return confirm('Terminate service <?= $svc['name'] ?>?')">Kill</button>
                <?php else: ?>
                    <button type="submit" name="action" value="start" class="btn btn-start">Start</button>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; endif; ?>
    <button onclick="location.reload()" class="refresh">Refresh Engine</button>
</body>
</html>