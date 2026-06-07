<?php
$message = "";
require_once '/data/adb/php8/files/www/auth/auth_functions.php'; 
$configFile = '/data/adb/php8/files/config/onboot.cfg';

// Read current config
$config = [];
if (file_exists($configFile)) {
    $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $config[trim($key)] = trim($val);
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionKey = $_POST['key'] ?? '';
    $actionVal = $_POST['val'] ?? '';
    
    if (!empty($actionKey) && isset($config[$actionKey])) {
        $config[$actionKey] = ($actionVal === '1') ? '1' : '0';
        
        // Save back to file
        $newContent = "";
        foreach ($config as $k => $v) {
            $newContent .= "$k=$v\n";
        }
        file_put_contents($configFile, $newContent);
        $message = "Toggled " . htmlspecialchars($actionKey) . " to " . ($config[$actionKey] === '1' ? 'ON' : 'OFF');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Startup Manager</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>Startup Manager</h1>
        <div class="sub-t">OnBoot Config Toggles</div>
    </div>
    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (empty($config)): ?>
        <div style="text-align:center; padding:50px 20px; color:var(--text-sub); font-weight:700; border: 2px dashed var(--border); border-radius:24px; text-transform:uppercase; font-size:0.8rem;">
            No configurations found in onboot.cfg
        </div>
    <?php else: foreach ($config as $key => $val): 
        $isActive = ($val === '1');
        $displayKey = str_replace('_', ' ', $key);
    ?>
        <div class="svc-item">
            <div class="svc-info">
                <div>
                    <span class="svc-name"><?= htmlspecialchars($displayKey) ?></span>
                    <span class="badge <?= $isActive ? 'b-run' : 'b-stop' ?>"><?= $isActive ? 'ENABLED' : 'DISABLED' ?></span>
                </div>
                <span class="svc-pid">Config Key: <?= htmlspecialchars($key) ?></span>
            </div>
            <form method="POST" id="form-<?= $key ?>">
                <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                <input type="hidden" name="val" value="0">
                <label class="switch">
                    <input type="checkbox" name="val" value="1" <?= $isActive ? 'checked' : '' ?> onchange="document.getElementById('form-<?= $key ?>').submit()">
                    <span class="slider"></span>
                </label>
            </form>
        </div>
    <?php endforeach; endif; ?>
    <button onclick="location.reload()" class="refresh">Refresh Status</button>
<script src="/assets/js/main.js"></script>
</body>
</html>
