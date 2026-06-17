<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
require_once '/data/adb/php8/files/www/utils.php';

// PERBAIKAN 1: Cek session sebelum start agar tidak bentrok dengan auth_functions.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!is_pro_user()) {
    render_pro_lock_screen('APK Manager (App Controller)');
}

// --- FORCE REFRESH HANDLER ---
if (isset($_GET['refresh'])) {
    unset($_SESSION['app_cache']);
    header("Location: ?tab=manage");
    exit;
}

// --- INSTALL HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['apk_file'])) {
    // Pastikan folder uploads writable
    $uploadDir = __DIR__ . '/uploads/'; // Gunakan __DIR__ agar path absolut & aman
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $fileName = basename($_FILES['apk_file']['name']);
    // Sanitasi nama file untuk mencegah karakter aneh
    $fileName = preg_replace("/[^a-zA-Z0-9\.\-_]/", "", $fileName);
    
    $targetPath = $uploadDir . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    if ($fileType != 'apk') {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Only APK files allowed!'];
    } elseif (move_uploaded_file($_FILES['apk_file']['tmp_name'], $targetPath)) {
        // PERBAIKAN SECURITY: Gunakan escapeshellarg untuk path file
        $safePath = escapeshellarg($targetPath);
        $result = run_root("pm install -r $safePath");
        
        // Hapus file setelah install
        if (file_exists($targetPath)) unlink($targetPath); 
        
        if (trim($result) === 'Success') {
            $_SESSION['msg'] = ['type' => 'success', 'text' => 'APK Installed Successfully!'];
        } else {
            $_SESSION['msg'] = ['type' => 'error', 'text' => 'Failed: ' . htmlspecialchars($result)];
        }
    } else {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Upload failed. Check folder permissions.'];
    }
    header("Location: ?tab=install");
    exit;
}

// --- UNINSTALL HANDLER ---
if (isset($_GET['uninstall'])) {
    $pkg = $_GET['uninstall'];
    
    // PERBAIKAN SECURITY FATAL: 
    // Mencegah command injection (misal: user kirim param "?uninstall=com.app; rm -rf /")
    // Kita wajib sanitize input paket.
    if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $pkg)) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid Package Name!'];
    } else {
        $safePkg = escapeshellarg($pkg);
        $result = run_root("pm uninstall $safePkg");
        $status = (trim($result) == 'Success');
        
        $_SESSION['msg'] = [
            'type' => $status ? 'success' : 'error',
            'text' => $status ? "Uninstalled $pkg" : "Failed: " . htmlspecialchars($result)
        ];
        unset($_SESSION['app_cache']); 
    }
    header("Location: ?tab=manage");
    exit;
}

// --- AJAX API ENDPOINT ---
if (isset($_GET['action']) && $_GET['action'] == 'get_apps') {
    header('Content-Type: application/json');
    $jsonFile = '/data/data/com.awd.modemtools/files/awd_user_apps.json';
    
    // Cek mtime lama
    $oldMtime = trim(run_root("stat -c %Y $jsonFile 2>/dev/null || echo 0"));
    
    // Trigger ekstrak
    run_root("am broadcast -a com.awd.modemtools.GET_APPS -n com.awd.modemtools/.AppListReceiver");
    
    // Tunggu maksimal 5 detik
    $timeout = 50; 
    while ($timeout > 0) {
        $currentMtime = trim(run_root("stat -c %Y $jsonFile 2>/dev/null || echo 0"));
        if ($currentMtime > $oldMtime && $currentMtime > 0) break;
        usleep(100000); 
        $timeout--;
    }

    $jsonData = trim(run_root("cat $jsonFile 2>/dev/null"));
    if (!empty($jsonData)) {
        echo $jsonData;
    } else {
        echo "[]";
    }
    exit;
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'install';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>APK Manager</title>
    <style>
        /* --- CSS VARIABLES (TRANSPARENT GLASSMORPHISM) --- */
        :root {
            --card-bg: rgba(255, 248, 240, 0.15); 
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
            --success: #34c759; 
            --success-bg: rgba(52, 199, 89, 0.15);
            --sys-color: #8B4513; 
            --sys-bg: rgba(139, 69, 19, 0.15);
            --usr-color: #D2691E; 
            --usr-bg: rgba(210, 105, 30, 0.15);
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --radius: 20px;
            --inner-radius: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); 
                --text-main: #FDF5E6;
                --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.15);
                --border-dashed: rgba(253, 245, 230, 0.15);
                --inp-bg: rgba(253, 245, 230, 0.08); 
                --primary: #C19A6B; 
                --primary-bg: rgba(193, 154, 107, 0.2);
                --danger: #ff453a; 
                --danger-bg: rgba(255, 69, 58, 0.2);
                --success: #32d74b; 
                --success-bg: rgba(50, 215, 75, 0.2);
                --sys-color: #DEB887; 
                --sys-bg: rgba(222, 184, 135, 0.2);
                --usr-color: #F4A460; 
                --usr-bg: rgba(244, 164, 96, 0.2);
                --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; background: transparent; color: var(--text-main); padding: 16px; max-width: 900px; margin: 0 auto; padding-bottom: 80px; -webkit-font-smoothing: antialiased; }
        .card { background: var(--card-bg); backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); overflow: hidden; margin-bottom: 15px; position: relative; }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: var(--radius); box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .btn { border: 1px solid transparent; border-radius: var(--inner-radius); padding: 12px 18px; font-weight: 600; cursor: pointer; transition: 0.2s ease; font-size: 0.95rem; display: inline-flex; justify-content: center; align-items: center; gap: 8px; backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); }
        .btn-p { background: rgba(184, 115, 51, 0.85); color: #fff; width: 100%; border: 1px solid var(--border); box-shadow: inset 0 1px 1px rgba(255,255,255,0.2); }
        .btn-p:hover { background: rgba(184, 115, 51, 1); transform: translateY(-2px); }
        .btn-d { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(255, 59, 48, 0.3); }
        .btn-d:hover { background: rgba(255, 59, 48, 0.25); transform: translateY(-2px); }
        .btn-s { background: transparent; border: none; color: var(--text-main); padding: 8px 12px; font-size: 0.8rem; text-decoration: none; }
        .btn-s:hover { transform: translateY(-2px); }
        .icon { width: 20px; height: 20px; fill: currentColor; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; background: var(--card-bg); backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); padding: 6px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); }
        .tab { flex: 1; text-align: center; padding: 12px; border-radius: 14px; font-weight: 600; text-decoration: none; color: var(--text-sub); transition: 0.2s ease; border: 1px solid transparent; }
        .tab.active { background: var(--primary-bg); color: var(--primary); border-color: rgba(184, 115, 51, 0.3); box-shadow: inset 0 1px 2px rgba(255,255,255,0.1); }
        .alert { padding: 14px 18px; border-radius: var(--inner-radius); margin-bottom: 16px; font-size: 0.9rem; font-weight: 600; display: flex; gap: 10px; align-items: center; border: 1px solid transparent; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .alert.success { background: var(--success-bg); color: var(--success); border-color: rgba(52, 199, 89, 0.4); }
        .alert.error { background: var(--danger-bg); color: var(--danger); border-color: rgba(255, 59, 48, 0.4); }
        .upload-box { border: 2px dashed var(--border); border-radius: var(--radius); padding: 50px 20px; text-align: center; background: var(--inp-bg); cursor: pointer; position: relative; transition: 0.2s ease; }
        .upload-box:hover, .upload-box.drag-over { border-color: var(--primary); background: var(--primary-bg); transform: scale(1.02); }
        input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .spin { width: 18px; height: 18px; border: 2px solid #fff; border-bottom-color: transparent; border-radius: 50%; display: inline-block; animation: s 1s infinite; }
        .spin-big { width: 40px; height: 40px; border: 4px solid var(--primary); border-bottom-color: transparent; border-radius: 50%; display: inline-block; animation: s 1s infinite; margin: 20px auto; }
        @keyframes s { to { transform: rotate(360deg); } }
        .stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .stat { background: var(--card-bg); backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); padding: 16px 12px; border-radius: var(--radius); text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow); position: relative; }
        .s-val { font-size: 1.6rem; font-weight: 700; display: block; line-height: 1.2; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .s-lbl { font-size: 0.8rem; text-transform: uppercase; color: var(--text-sub); font-weight: 600; letter-spacing: 0.5px; }
        .search-row { display: flex; gap: 10px; margin-bottom: 20px; align-items: center;}
        .inp { flex: 1; padding: 14px 18px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card-bg); backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); color: var(--text-main); font-size: 0.95rem; font-weight: 500; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); transition: 0.2s; }
        .inp:focus { border-color: rgba(255, 255, 255, 0.8); background: rgba(255, 255, 255, 0.1); }
        .chips { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; }
        .chip { padding: 8px 18px; border-radius: 20px; font-size: 0.85rem; background: var(--card-bg); backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); border: 1px solid var(--border); color: var(--text-main); cursor: pointer; white-space: nowrap; font-weight: 600; transition: 0.2s ease; }
        .chip:hover { border-color: var(--primary); }
        .chip.active { background: var(--primary); color: #fff; border-color: transparent; box-shadow: 0 4px 10px rgba(184, 115, 51, 0.3); }
        .list { display: grid; grid-template-columns: 1fr; gap: 15px; }
        @media (min-width: 600px) { .list { grid-template-columns: 1fr 1fr; } }
        .app { padding: 20px; position: relative; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s ease; }
        .app:hover { transform: translateY(-4px); border-color: rgba(255, 255, 255, 0.7); }
        .app-h { margin-bottom: 16px; z-index: 2; position: relative; display: flex; align-items: flex-start; gap: 14px; }
        .app-icon { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.1); flex-shrink: 0; background: var(--inp-bg); padding: 4px; border: 1px solid var(--border); }
        .app-info { flex: 1; min-width: 0; }
        .app-n { font-weight: 700; font-size: 1.05rem; margin-bottom: 6px; word-break: break-word; color: var(--text-main); line-height: 1.2; }
        .app-p { color: var(--text-sub); font-size: 0.8rem; font-family: 'SF Mono', monospace; word-break: break-all; }
        .tags { display: flex; gap: 8px; margin-top: 12px; }
        .tag { font-size: 0.7rem; padding: 4px 10px; border-radius: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid transparent; }
        .t-sys { background: var(--sys-bg); color: var(--sys-color); border-color: rgba(139, 69, 19, 0.2); }
        .t-usr { background: var(--usr-bg); color: var(--usr-color); border-color: rgba(210, 105, 30, 0.2); }
        .loading-container { grid-column: 1/-1; text-align: center; padding: 50px; background: var(--card-bg); border-radius: var(--radius); border: 1px dashed var(--border-dashed); backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur); }
    </style>
</head>
<body>

    <div class="tabs">
        <a href="?tab=install" class="tab <?= $activeTab == 'install' ? 'active' : '' ?>">Installer</a>
        <a href="?tab=manage" class="tab <?= $activeTab == 'manage' ? 'active' : '' ?>">Manager</a>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert <?= $_SESSION['msg']['type'] ?>">
            <span><?= htmlspecialchars($_SESSION['msg']['text']) ?></span>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

    <?php if ($activeTab == 'install'): ?>
    <div class="card" style="padding: 30px;">
        <h2 style="margin-bottom: 25px; color:var(--text-main); text-align:center; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Install APK</h2>
        <form method="post" enctype="multipart/form-data" id="fInst" style="position: relative; z-index: 2;">
            <div class="upload-box" id="dropBox">
                <input type="file" name="apk_file" id="fApk" accept=".apk" required>
                <div style="margin-bottom: 15px;">
                    <svg class="icon" style="width:48px; height:48px; color: var(--primary);" viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                </div>
                <div id="fInfo" style="font-size: 0.95rem; color: var(--text-main); font-weight:600;">Tap to select or Drag & Drop APK</div>
            </div>
            <button type="submit" class="btn btn-p" id="bSub" disabled style="margin-top: 25px; padding: 16px;">
                <span class="spin" id="spin" style="display:none;"></span>
                <span id="bTxt">Install App</span>
            </button>
        </form>
    </div>
    <script>
        const db = document.getElementById('dropBox'), fi = document.getElementById('fApk');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => db.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); }));
        ['dragenter', 'dragover'].forEach(e => db.addEventListener(e, () => db.classList.add('drag-over')));
        ['dragleave', 'drop'].forEach(e => db.addEventListener(e, () => db.classList.remove('drag-over')));
        db.addEventListener('drop', e => { if(e.dataTransfer.files.length) { fi.files = e.dataTransfer.files; fi.dispatchEvent(new Event('change')); } });
        fi.addEventListener('change', function() {
            if (this.files.length > 0) { document.getElementById('fInfo').textContent = this.files[0].name; document.getElementById('bSub').disabled = false; }
        });
        document.getElementById('fInst').addEventListener('submit', function() {
            document.getElementById('bSub').disabled = true; document.getElementById('spin').style.display = 'inline-block'; document.getElementById('bTxt').textContent = 'Installing...';
        });
    </script>
    <?php endif; ?>

    <?php if ($activeTab == 'manage'): ?>
    <div class="stats">
        <div class="stat"><span class="s-val" id="st-tot" style="color: var(--primary)">0</span><span class="s-lbl">Total</span></div>
        <div class="stat"><span class="s-val" id="st-usr" style="color: var(--usr-color)">0</span><span class="s-lbl">User</span></div>
        <div class="stat" onclick="fetchApps()" style="cursor: pointer; transition: transform 0.2s ease;">
            <div style="color:var(--success); margin-bottom: 4px; display: block; line-height: 1;">
                <svg style="width:30px; height:30px; fill:currentColor; vertical-align: middle;" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
            </div>
            <span class="s-lbl" style="color:var(--text-main);">REFRESH</span>
        </div>
    </div>

    <div class="search-row">
        <input type="text" id="sInp" class="inp" placeholder="Search app name or package...">
    </div>

    <div class="chips">
        <div class="chip active" onclick="setFilter('user', this)">User</div>
        <div class="chip" onclick="setFilter('system', this)">System</div>
    </div>

    <div class="list" id="appList">
        <div class="loading-container" id="loadingDiv">
            <div class="spin-big"></div>
            <h3 style="color: var(--text-main);">Loading Apps...</h3>
            <p style="color: var(--text-sub); font-size:0.9rem;">Fetching data from Android System</p>
        </div>
    </div>

    <script>
        let allApps = [];
        let currentFilter = 'user';

        function fetchApps() {
            document.getElementById('appList').innerHTML = `
                <div class="loading-container">
                    <div class="spin-big"></div>
                    <h3 style="color: var(--text-main);">Loading Apps...</h3>
                    <p style="color: var(--text-sub); font-size:0.9rem;">Fetching data from Android System</p>
                </div>`;
                
            fetch('?tab=manage&action=get_apps')
                .then(r => r.json())
                .then(data => {
                    if (Array.isArray(data)) {
                        allApps = data.sort((a,b) => a.name.localeCompare(b.name));
                        updateStats();
                        renderApps();
                    } else {
                        document.getElementById('appList').innerHTML = `<div class="loading-container">Failed to parse JSON.</div>`;
                    }
                })
                .catch(e => {
                    document.getElementById('appList').innerHTML = `<div class="loading-container">Failed to load apps. Ensure SoftApHelper is installed.</div>`;
                });
        }

        function updateStats() {
            let usr = 0, sys = 0;
            allApps.forEach(a => { if(a.type == 'user') usr++; else sys++; });
            document.getElementById('st-tot').textContent = allApps.length;
            document.getElementById('st-usr').textContent = usr;
        }

        function setFilter(filter, el) {
            currentFilter = filter;
            document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            renderApps();
        }

        function renderApps() {
            const list = document.getElementById('appList');
            const search = document.getElementById('sInp').value.toLowerCase();
            list.innerHTML = '';
            
            let filtered = allApps.filter(a => a.type === currentFilter);
            if(search) {
                filtered = filtered.filter(a => a.name.toLowerCase().includes(search) || a.package.toLowerCase().includes(search));
            }

            if(filtered.length === 0) {
                list.innerHTML = `<div class="loading-container">No apps found.</div>`;
                return;
            }

            let html = '';
            filtered.forEach(app => {
                let iconHtml = app.icon 
                    ? `<img src="data:image/png;base64,${app.icon}" class="app-icon" alt="Icon">` 
                    : `<div class="app-icon" style="display:flex; align-items:center; justify-content:center; color:var(--text-sub);"><svg style="width:24px;height:24px;fill:currentColor" viewBox="0 0 24 24"><path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4M11,16.5L6.5,12L7.91,10.59L11,13.67L16.59,8.09L18,9.5L11,16.5Z" /></svg></div>`;
                
                let tagClass = app.type === 'system' ? 't-sys' : 't-usr';
                
                // Note: htmlspecialchars logic is simplified in JS, we just escape package to avoid injection
                let safePkg = app.package.replace(/'/g, "\\'");
                let safeName = app.name.replace(/'/g, "\\'");

                html += `
                <div class="card app">
                    <div class="app-h">
                        ${iconHtml}
                        <div class="app-info">
                            <div class="app-n">${app.name}</div>
                            <div class="app-p">${app.package}</div>
                            <div class="tags">
                                <span class="tag ${tagClass}">${app.type.toUpperCase()}</span>
                            </div>
                        </div>
                    </div>
                    <button onclick="uninst('${safePkg}', '${safeName}')" class="btn btn-d" style="margin-top: auto; position: relative; z-index: 2;">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg> Uninstall
                    </button>
                </div>`;
            });
            list.innerHTML = html;
        }

        document.getElementById('sInp').addEventListener('input', renderApps);

        function uninst(p, n) {
            if (confirm('Uninstall "' + n + '"?')) {
                window.location.href = "?tab=manage&uninstall=" + p;
            }
        }

        // Auto load on start
        fetchApps();
    </script>
    <?php endif; ?>

</body>
</html>
