<?php
$message = "";
require_once '/data/adb/php8/files/www/auth/auth_functions.php'; 
$targetDir = '/data/adb/php8/scripts/onboot';

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
    <link rel="stylesheet" href="/assets/css/style.css">
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
<script src="/assets/js/main.js"></script>
</body>
</html>