<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$targetScript = '/data/adb/post-fs-data.d/module_fix.sh';
$modulesDir   = '/data/adb/modules';
function runCmd($cmd) { return shell_exec('su -c ' . escapeshellarg($cmd)); }
function readFileRoot($path) { return shell_exec('su -c ' . escapeshellarg("cat $path")); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $blacklisted = isset($_POST['modules']) ? $_POST['modules'] : [];
    $newBlacklistStr = "\n" . implode("\n", $blacklisted) . "\n";
    $scriptExists = trim(runCmd("[ -f " . escapeshellarg($targetScript) . " ] && echo 1 || echo 0"));
    if ($scriptExists !== '1') {
        $defaultContent = "#!/system/bin/sh\nBLACKLIST_MODULES=\"\"\nMODULES_DIR=\"/data/adb/modules\"\nif [ -d \"\$MODULES_DIR\" ]; then\n for MODULE_PATH in \"\$MODULES_DIR\"/*; do\n if [ -d \"\$MODULE_PATH\" ]; then\n MODULE_NAME=\$(basename \"\$MODULE_PATH\")\n DISABLE_FILE=\"\$MODULE_PATH/disable\"\n case \"\$BLACKLIST_MODULES\" in *\"\$MODULE_NAME\"*) continue ;; esac\n if [ -f \"\$DISABLE_FILE\" ]; then rm -f \"\$DISABLE_FILE\"; fi\n fi\n done\nfi";
        $tmpInit = tempnam(sys_get_temp_dir(), 'init_mod');
        file_put_contents($tmpInit, $defaultContent);
        runCmd("cat " . escapeshellarg($tmpInit) . " > " . escapeshellarg($targetScript));
        runCmd("chmod 755 " . escapeshellarg($targetScript));
        unlink($tmpInit);
    }
    $content = readFileRoot($targetScript);
    if ($content !== null && $content !== '') {
        $newContent = preg_replace('/BLACKLIST_MODULES="([^"]*)"/s', 'BLACKLIST_MODULES="' . $newBlacklistStr . '"', $content);
        $tmpFile = tempnam(sys_get_temp_dir(), 'bl_edit');
        file_put_contents($tmpFile, $newContent);
        runCmd("cat " . escapeshellarg($tmpFile) . " > " . escapeshellarg($targetScript));
        runCmd("chmod 755 " . escapeshellarg($targetScript));
        unlink($tmpFile);
        $message = "Blacklist Updated!"; $msgType = "success";
    } else { $message = "Failed to write script!"; $msgType = "error"; }
}

$installedModules = [];
$modList = runCmd("ls " . escapeshellarg($modulesDir) . " 2>/dev/null");
if (!empty(trim($modList))) {
    foreach (explode("\n", trim($modList)) as $dir) {
        $dir = trim($dir);
        if ($dir === '') continue;
        $fullPath = "$modulesDir/$dir";
        $isDir = trim(runCmd("[ -d " . escapeshellarg($fullPath) . " ] && echo 1 || echo 0"));
        if ($isDir !== '1') continue;
        $propFile = "$fullPath/module.prop";
        $name = $dir;
        $propExists = trim(runCmd("[ -f " . escapeshellarg($propFile) . " ] && echo 1 || echo 0"));
        if ($propExists === '1') {
            $propContent = readFileRoot($propFile);
            if ($propContent) {
                foreach (explode("\n", $propContent) as $line) {
                    $line = trim($line);
                    if (strpos($line, 'name=') === 0) { $name = trim(substr($line, 5)); break; }
                }
            }
        }
        $installedModules[$dir] = $name;
    }
}
$currentBlacklist = [];
$scriptContent = readFileRoot($targetScript);
if ($scriptContent && preg_match('/BLACKLIST_MODULES="([^"]*)"/s', $scriptContent, $matches)) {
    $currentBlacklist = preg_split('/\s+/', trim($matches[1]), -1, PREG_SPLIT_NO_EMPTY);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Module Fix</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --dang: #ff3b30; --suc: #32d74b;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; max-width: 600px; margin: 0 auto; -webkit-font-smoothing: antialiased;
        }
        header { text-align: center; margin-bottom: 25px; }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .sub { font-size: 0.75rem; color: var(--text-sub); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; padding: 24px; box-shadow: var(--shadow); border: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .list { display: flex; flex-direction: column; gap: 12px; max-height: 60vh; overflow-y: auto; padding-right: 5px; }
        .item { 
            display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; 
            background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); border-radius: 16px; 
            cursor: pointer; transition: 0.3s;
        }
        .item:hover { background: var(--accent); border-color: var(--primary); }
        .info { flex: 1; margin-right: 15px; }
        .name { font-weight: 800; font-size: 0.95rem; display: block; margin-bottom: 4px; }
        .id { font-size: 0.75rem; color: var(--text-sub); font-family: 'SF Mono', monospace; font-weight: 600; }
        input[type="checkbox"] { width: 22px; height: 22px; accent-color: var(--primary); cursor: pointer; }
        .btn { 
            width: 100%; padding: 16px; border: none; border-radius: 16px; background: var(--primary); 
            color: white; font-weight: 800; font-size: 0.85rem; cursor: pointer; margin-top: 25px; 
            transition: 0.3s; text-transform: uppercase; letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2);
        }
        .btn:active { transform: scale(0.97); }
        .alert { padding: 14px; border-radius: 16px; margin-bottom: 20px; text-align: center; font-weight: 800; border: 1px solid; font-size: 0.85rem; }
        .suc { background: rgba(50, 215, 75, 0.15); color: var(--suc); border-color: rgba(50, 215, 75, 0.3); }
        .err { background: rgba(255, 59, 48, 0.15); color: var(--dang); border-color: rgba(255, 59, 48, 0.3); }
        .note { text-align: center; font-size: 0.75rem; color: var(--text-sub); margin-top: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7; }
    </style>
</head>
<body>
    <header>
        <h1>Module Fix</h1>
        <div class="sub">Module Blacklist Manager</div>
    </header>
    <?php if (isset($message)): ?>
        <div class="alert <?= ($msgType === 'success') ? 'suc' : 'err' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST" class="card">
        <div class="list">
            <?php if (empty($installedModules)): ?>
                <div style="text-align:center; padding:30px; color:var(--text-sub);">No modules found.</div>
            <?php else: foreach ($installedModules as $id => $name):
                $chk = in_array($id, $currentBlacklist) ? 'checked' : ''; ?>
                <label class="item">
                    <div class="info">
                        <span class="name"><?= htmlspecialchars($name) ?></span>
                        <span class="id"><?= htmlspecialchars($id) ?></span>
                    </div>
                    <input type="checkbox" name="modules[]" value="<?= htmlspecialchars($id) ?>" <?= $chk ?>>
                </label>
            <?php endforeach; endif; ?>
        </div>
        <button type="submit" name="save" class="btn">Update Blacklist</button>
    </form>
    <div class="note">Checked modules will be disabled on reboot.</div>
</body>
</html>