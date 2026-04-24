<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

if (isset($_GET['install_stream']) && isset($_GET['file'])) {
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    while (ob_get_level() > 0) ob_end_flush();
    ob_implicit_flush(1);

    $file = '/data/local/tmp/' . basename($_GET['file']);
    
    if (!file_exists($file) || !str_ends_with($file, '.zip')) {
        echo "Error: Invalid file.";
        exit;
    }

    echo "\n";
    echo "Installing: " . htmlspecialchars(basename($file)) . "...\n";
    flush();

    $cmd = "magisk --install-module " . escapeshellarg($file) . " 2>&1";
    $proc = popen($cmd, 'r');
    
    if ($proc) {
        while (!feof($proc)) {
            $line = fgets($proc);
            if ($line !== false) {
                echo htmlspecialchars($line); 
                echo "<script>try{parent.scrollLog()}catch(e){}</script>"; 
                flush(); 
            }
        }
        pclose($proc);
    }
    
    unlink($file); 
    echo "\nDone. Please Reboot.\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zipfile'])) {
    $uploadDir = '/data/local/tmp/';
    $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['zipfile']['name']));
    $uploadFile = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['zipfile']['tmp_name'], $uploadFile)) {
        echo json_encode(['status' => 'success', 'file' => $fileName]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
    }
    exit;
}

function runCommand($cmd) {
    return shell_exec($cmd . ' 2>&1') ?? 'No output';
}

function getModules() {
    $modulesDir = '/data/adb/modules';
    $modules = [];
    if (is_dir($modulesDir)) {
        foreach (scandir($modulesDir) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            $modulePath = "$modulesDir/$dir";
            if (!is_dir($modulePath)) continue;
            
            $propFile = "$modulePath/module.prop";
            $props = [];
            if (file_exists($propFile)) {
                $content = @file_get_contents($propFile);
                if ($content) {
                    foreach (explode("\n", $content) as $line) {
                        $parts = explode('=', trim($line), 2);
                        if (count($parts) === 2) $props[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }
            $modules[] = [
                'id' => $dir,
                'name' => $props['name'] ?? $dir,
                'version' => $props['version'] ?? '?',
                'author' => $props['author'] ?? '?',
                'desc' => $props['description'] ?? '',
                'enabled' => !file_exists("$modulePath/disable"),
                'remove' => file_exists("$modulePath/remove")
            ];
        }
    }
    return $modules;
}

if (isset($_GET['action']) && isset($_GET['module'])) {
    $mod = escapeshellarg($_GET['module']);
    $path = "/data/adb/modules/" . $_GET['module'];
    switch ($_GET['action']) {
        case 'enable': @unlink("$path/disable"); break;
        case 'disable': @touch("$path/disable"); break;
        case 'remove': runCommand("touch \"$path/remove\""); break;
    }
    header("Location: ?tab=modules");
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'reboot') {
    runCommand("reboot");
    exit;
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'modules';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Magisk Manager</title>
    <style>
        /* --- CSS VARIABLES (TRANSPARENT GLASSMORPHISM) --- */
        :root {
            /* LIGHT MODE */
            --card-bg: rgba(255, 248, 240, 0.15); /* Sangat transparan */
            --blur: blur(5px);
            --text-main: #3E2A1C;
            --text-sub: #7A5C43;
            --border: rgba(255, 255, 255, 0.5);
            --border-dashed: rgba(122, 92, 67, 0.2);
            
            --inp-bg: rgba(62, 42, 28, 0.08); 
            
            --primary: #B87333; 
            --primary-bg: rgba(184, 115, 51, 0.15);
            
            --danger: #ff3b30; 
            --danger-bg: rgba(255, 59, 48, 0.15);
            
            --warning: #fb8c00; 
            --warning-bg: rgba(251, 140, 0, 0.15);
            
            --success: #34c759; 
            --success-bg: rgba(52, 199, 89, 0.15);
            
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --radius: 20px;
            --inner-radius: 12px;
            
            /* Glass Console Logs */
            --log-bg: rgba(30, 18, 10, 0.4); 
            --log-text: #FDF5E6; 
            --log-head: rgba(0, 0, 0, 0.2);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                /* DARK MODE */
                --card-bg: rgba(10, 5, 2, 0.2); /* Sangat transparan */
                --blur: blur(5px);
                --text-main: #FDF5E6;
                --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.15);
                --border-dashed: rgba(253, 245, 230, 0.15);
                
                --inp-bg: rgba(253, 245, 230, 0.08); 
                
                --primary: #C19A6B; 
                --primary-bg: rgba(193, 154, 107, 0.2);
                
                --danger: #ff453a; 
                --danger-bg: rgba(255, 69, 58, 0.2);
                
                --warning: #ff9800; 
                --warning-bg: rgba(255, 152, 0, 0.2);
                
                --success: #32d74b; 
                --success-bg: rgba(50, 215, 75, 0.2);
                
                --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
                
                --log-bg: rgba(0, 0, 0, 0.4); 
                --log-text: #C19A6B; 
                --log-head: rgba(255, 255, 255, 0.05);
            }
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
            background: transparent; /* TRANSPARAN TOTAL */
            color: var(--text-main); 
            padding: 16px; 
            max-width: 900px; 
            margin: 0 auto; 
            padding-bottom: 80px; 
            -webkit-font-smoothing: antialiased;
        }
        
        /* --- GLASSMORPHISM CARD --- */
        .card { 
            background: var(--card-bg); 
            backdrop-filter: var(--blur); 
            -webkit-backdrop-filter: var(--blur);
            border-radius: var(--radius); 
            box-shadow: var(--shadow); 
            border: 1px solid var(--border); 
            overflow: hidden; 
            margin-bottom: 16px; 
            padding: 24px; 
            position: relative;
        }
        .card::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            border-radius: var(--radius); box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none;
        }

        .title { font-weight: 700; font-size: 1.1rem; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px dashed var(--border-dashed); padding-bottom: 12px; color: var(--text-main); position: relative; z-index: 2; }
        .title-left { display: flex; align-items: center; gap: 10px; }

        /* --- BUTTONS --- */
        .btn { border: 1px solid transparent; border-radius: var(--inner-radius); padding: 10px 16px; font-weight: 600; cursor: pointer; transition: 0.2s ease; font-size: 0.85rem; display: inline-flex; justify-content: center; align-items: center; gap: 8px; text-decoration: none; backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); }
        .btn-sm { padding: 6px 14px; font-size: 0.75rem; border-radius: 8px; }
        
        .btn-p { background: rgba(184, 115, 51, 0.85); color: #fff; border: 1px solid var(--border); box-shadow: inset 0 1px 1px rgba(255,255,255,0.2); }
        .btn-p:hover { background: rgba(184, 115, 51, 1); transform: translateY(-2px); }
        
        .btn-d { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(255, 59, 48, 0.3); }
        .btn-d:hover { background: rgba(255, 59, 48, 0.2); transform: translateY(-2px); }
        
        .btn-w { background: var(--warning-bg); color: var(--warning); border: 1px solid rgba(251, 140, 0, 0.3); }
        .btn-w:hover { background: rgba(251, 140, 0, 0.2); transform: translateY(-2px); }
        
        .btn-s { background: var(--success-bg); color: var(--success); border: 1px solid rgba(52, 199, 89, 0.3); }
        .btn-s:hover { background: rgba(52, 199, 89, 0.2); transform: translateY(-2px); }
        
        .icon { width: 22px; height: 22px; fill: currentColor; }

        /* --- TABS --- */
        .tabs { 
            display: flex; gap: 10px; margin-bottom: 20px; 
            background: var(--card-bg); 
            backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur);
            padding: 6px; 
            border-radius: var(--radius); 
            border: 1px solid var(--border); 
            box-shadow: var(--shadow); 
        }
        .tab { flex: 1; text-align: center; padding: 12px; border-radius: 14px; font-weight: 600; text-decoration: none; color: var(--text-sub); transition: 0.2s ease; border: 1px solid transparent; }
        .tab.active { background: var(--primary-bg); color: var(--primary); border-color: rgba(184, 115, 51, 0.3); box-shadow: inset 0 1px 2px rgba(255,255,255,0.1); }

        /* --- MODULE LIST --- */
        .mod-item { border-bottom: 1px dashed var(--border-dashed); padding: 20px 0; position: relative; z-index: 2; transition: 0.2s ease; }
        .mod-item:hover { transform: translateX(4px); }
        .mod-item:last-child { border-bottom: none; padding-bottom: 0; }
        .mod-head { display: flex; justify-content: space-between; margin-bottom: 6px; align-items: center; }
        .mod-name { font-weight: 700; font-size: 1.05rem; color: var(--text-main); }
        .mod-ver { font-size: 0.75rem; color: var(--text-sub); background: var(--inp-bg); padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border); font-family: 'SF Mono', monospace; }
        .mod-desc { font-size: 0.85rem; color: var(--text-sub); margin-bottom: 12px; line-height: 1.5; display: block; }
        .mod-auth { font-size: 0.75rem; color: var(--primary); margin-bottom: 10px; display: block; font-weight: 600; letter-spacing: 0.5px; }
        .mod-acts { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }

        /* --- UPLOAD BOX --- */
        .upload-box { 
            border: 2px dashed var(--border); 
            border-radius: var(--radius); 
            padding: 40px 20px; 
            cursor: pointer; 
            transition: 0.2s ease; 
            background: var(--inp-bg); 
            display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; min-height: 160px; 
            position: relative; z-index: 2;
        }
        .upload-box:hover, .upload-box.drag-over { border-color: var(--primary); background: var(--primary-bg); transform: scale(1.02); }
        input[type="file"] { display: none; }
        
        /* --- PROGRESS BAR --- */
        .progress-area { display: none; margin-top: 25px; position: relative; z-index: 2; }
        .progress-track { width: 100%; background: var(--inp-bg); height: 10px; border-radius: 10px; overflow: hidden; border: 1px solid var(--border); }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.1s linear; box-shadow: inset 0 1px 2px rgba(255,255,255,0.3); }
        .progress-text { font-size: 0.85rem; color: var(--text-main); text-align: center; margin-top: 10px; font-weight: 600; display: flex; justify-content: space-between; }

        /* --- CONSOLE LOG GLASS --- */
        .console-wrap { display: none; margin-top: 20px; border-radius: var(--inner-radius); border: 1px solid var(--border); overflow: hidden; position: relative; z-index: 2; box-shadow: var(--shadow); }
        .console-head { background: var(--log-head); padding: 10px 16px; color: var(--text-sub); font-size: 0.75rem; font-weight: 700; border-bottom: 1px solid var(--border); letter-spacing: 1px; text-transform: uppercase; backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); }
        #logFrame { width:100%; height:300px; border:none; background: var(--log-bg); backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); }
        
        .log-box { 
            background-color: var(--log-bg); 
            backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur);
            color: var(--log-text); 
            border: 1px solid var(--border); 
            border-radius: var(--inner-radius); 
            height: 500px; overflow-y: auto; 
            font-family: 'SF Mono', monospace; font-size: 0.85rem; padding: 15px; white-space: pre-wrap; 
            position: relative; z-index: 2; line-height: 1.5;
        }
        .log-err { color: #ff6b6b; } .log-warn { color: #ffd93d; }
    </style>
</head>
<body>

    <div class="tabs">
        <a href="?tab=modules" class="tab <?= $activeTab == 'modules' ? 'active' : '' ?>">Modules</a>
        <a href="?tab=logs" class="tab <?= $activeTab == 'logs' ? 'active' : '' ?>">Logs</a>
    </div>

    <?php if ($activeTab == 'modules'): ?>
        
        <div class="card">
            <div class="title">
                <div class="title-left">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg> Install Module
                </div>
                <a href="?action=reboot" class="btn btn-sm btn-d" onclick="return confirm('Reboot device now?')">
                    <svg class="icon" style="width:16px;height:16px" viewBox="0 0 24 24"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A1,1 0 0,0 11,7V11H7A1,1 0 0,0 6,12A1,1 0 0,0 7,13H13V7A1,1 0 0,0 12,6Z" transform="rotate(45 12 12)"/></svg> Reboot
                </a>
            </div>
            
            <label class="upload-box" id="dropBox">
                <input type="file" id="zipFile" accept=".zip" onchange="startInstall(this.files[0])">
                <div style="color: var(--primary); margin-bottom: 12px;">
                    <svg class="icon" style="width:48px; height:48px;" viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                </div>
                <div style="font-weight: 700; font-size: 1.1rem; color:var(--text-main)" id="dropText">Tap or Drag .zip Here</div>
                <div style="color: var(--text-sub); font-size: 0.85rem; margin-top: 6px; font-weight: 500;">Install Magisk Module</div>
            </label>

            <div id="progressArea" class="progress-area">
                <div class="progress-track">
                    <div class="progress-fill" id="progressBar"></div>
                </div>
                <div class="progress-text">
                    <span id="progressStatus">Uploading...</span>
                    <span id="progressPercent">0%</span>
                </div>
            </div>

            <div id="consoleArea" class="console-wrap">
                <div class="console-head">INSTALLATION LOG</div>
                <iframe id="logFrame" src=""></iframe>
            </div>
        </div>

        <div class="card">
            <div class="title">
                <div class="title-left">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg> Installed Modules
                </div>
            </div>
            
            <?php 
            $modules = getModules();
            if (empty($modules)): ?>
                <div style="text-align:center; padding:30px; color:var(--text-sub); font-weight: 500; border: 1px dashed var(--border-dashed); border-radius: var(--radius); background: var(--inp-bg); position: relative; z-index: 2;">No modules found.</div>
            <?php else: 
                foreach ($modules as $mod): ?>
                <div class="mod-item" style="opacity: <?= ($mod['enabled'] && !($mod['remove'] ?? false)) ? '1' : '0.6' ?>">
                    <div class="mod-head">
                        <span class="mod-name"><?= htmlspecialchars($mod['name']) ?></span>
                        <span class="mod-ver"><?= htmlspecialchars($mod['version']) ?></span>
                    </div>
                    <span class="mod-auth">by <?= htmlspecialchars($mod['author']) ?></span>
                    <span class="mod-desc"><?= htmlspecialchars($mod['desc']) ?></span>
                    
                    <div class="mod-acts">
                        <?php if ($mod['enabled']): ?>
                            <a href="?tab=modules&action=disable&module=<?= urlencode($mod['id']) ?>" class="btn btn-sm btn-w">Disable</a>
                        <?php else: ?>
                            <a href="?tab=modules&action=enable&module=<?= urlencode($mod['id']) ?>" class="btn btn-sm btn-s">Enable</a>
                        <?php endif; ?>
                        
                        <a href="?tab=modules&action=remove&module=<?= urlencode($mod['id']) ?>" class="btn btn-sm btn-d" onclick="return confirm('Remove this module?')">Remove</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <script>
            const pgArea = document.getElementById('progressArea');
            const pgBar = document.getElementById('progressBar');
            const pgPct = document.getElementById('progressPercent');
            const pgStat = document.getElementById('progressStatus');
            const conArea = document.getElementById('consoleArea');
            const logFrame = document.getElementById('logFrame');

            window.scrollLog = function() {
                try {
                    let doc = logFrame.contentWindow.document;
                    if(!doc.body.dataset.styled) {
                        let style = doc.createElement('style');
                        style.textContent = `
                            body { font-family: 'SF Mono', monospace; font-size: 13px; margin: 15px; white-space: pre-wrap; line-height: 1.5; }
                            @media (prefers-color-scheme: dark) {
                                body { background-color: transparent; color: #C19A6B; }
                            }
                            @media (prefers-color-scheme: light) {
                                body { background-color: transparent; color: #FDF5E6; }
                            }
                        `;
                        doc.head.appendChild(style);
                        doc.body.dataset.styled = "true";
                    }
                    doc.scrollingElement.scrollTop = doc.scrollingElement.scrollHeight;
                } catch(e) {}
            };

            function startInstall(file) {
                if(!file || !file.name.endsWith('.zip')) { alert('Only .zip files!'); return; }

                pgArea.style.display = 'block';
                conArea.style.display = 'none';
                pgBar.style.width = '0%';
                pgBar.style.background = 'var(--primary)';
                pgPct.innerText = '0%';
                pgStat.innerText = 'Uploading: ' + file.name;

                let fd = new FormData();
                fd.append('zipfile', file);
                
                let xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener("progress", function(e) {
                    if (e.lengthComputable) {
                        let percent = Math.round((e.loaded / e.total) * 100);
                        pgBar.style.width = percent + '%';
                        pgPct.innerText = percent + '%';
                    }
                }, false);

                xhr.onload = function() {
                    if (xhr.status == 200) {
                        try {
                            let resp = JSON.parse(xhr.responseText);
                            if(resp.status === 'success') {
                                pgStat.innerText = 'Installing...';
                                pgBar.style.width = '100%'; 
                                pgBar.style.background = 'var(--success)';
                                conArea.style.display = 'block';
                                
                                logFrame.onload = function() {
                                    pgStat.innerText = 'Done';
                                };

                                logFrame.src = "?install_stream=1&file=" + encodeURIComponent(resp.file);
                            } else {
                                throw new Error(resp.message);
                            }
                        } catch(e) {
                            pgStat.innerText = "Error: " + e.message;
                            pgBar.style.background = 'var(--danger)';
                        }
                    } else {
                        pgStat.innerText = "Upload Failed";
                        pgBar.style.background = 'var(--danger)';
                    }
                };

                xhr.open("POST", window.location.href);
                xhr.send(fd);
            }
            
            const db = document.getElementById('dropBox');
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => db.addEventListener(e, ev => {ev.preventDefault(); ev.stopPropagation()}));
            db.addEventListener('drop', e => { if(e.dataTransfer.files.length) startInstall(e.dataTransfer.files[0]); });
        </script>

    <?php elseif ($activeTab == 'logs'): ?>
        <div class="card">
            <div class="title" style="justify-content:space-between">
                <div class="title-left">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Magisk Logs
                </div>
                <a href="?tab=logs" class="btn btn-sm btn-p">
                    <svg class="icon" style="width:16px;height:16px" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg> Refresh
                </a>
            </div>
            <div class="log-box">
                <?php
                $logFile = '/cache/magisk.log';
                if (file_exists($logFile)) {
                    $handle = fopen($logFile, "r");
                    if ($handle) {
                        while (($line = fgets($handle)) !== false) {
                            $c = 'log-row';
                            if (stripos($line, 'error') !== false || stripos($line, 'fail') !== false) $c .= ' log-err';
                            elseif (stripos($line, 'warn') !== false) $c .= ' log-warn';
                            echo "<div class='$c'>" . htmlspecialchars($line) . "</div>";
                        }
                        fclose($handle);
                    }
                } else {
                    echo '<div class="log-row log-err">Log file not found.</div>';
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
