<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cmd = '';
    switch ($action) {
        case 'reboot': $cmd = 'su -c reboot'; break;
        case 'shutdown': $cmd = 'su -c reboot -p'; break;
        case 'recovery': $cmd = 'su -c reboot recovery'; break;
        case 'fastboot': $cmd = 'su -c reboot bootloader'; break;
    }
    if ($cmd) { shell_exec($cmd); }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Power Menu</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; align-items: center; justify-content: center; }
        .con { width: 100%; max-width: 420px; }
        .card { padding: 35px 30px; border-radius: 28px; text-align: center; }
        p { font-size: 0.85rem; color: var(--text-sub); margin-bottom: 30px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge { display: inline-block; padding: 6px 16px; background: var(--accent); color: var(--primary); border-radius: 20px; font-size: 0.7rem; font-weight: 800; margin-bottom: 30px; border: 1px solid var(--border); text-transform: uppercase; letter-spacing: 1px; }
        .grid { display: grid; gap: 15px; }
        .btn-power { 
            width: 100%; padding: 18px; border: 1px solid var(--border); border-radius: 16px; 
            font-size: 0.9rem; font-weight: 800; cursor: pointer; transition: 0.3s; 
            color: var(--text-main); display: flex; align-items: center; justify-content: center; gap: 12px; 
            text-transform: uppercase; letter-spacing: 1px; background: rgba(255,255,255,0.05);
            backdrop-filter: var(--blur-val);
        }
        .btn-pri { background: var(--primary); color: #fff; border: none; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); }
        .btn-power:active { transform: scale(0.96); }
        .ovl { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(30, 18, 10, 0.6); z-index: 50; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px); }
        .mdl { 
            background: var(--card-bg); padding: 30px; border-radius: 28px; width: 90%; max-width: 340px; 
            text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border); 
            animation: pop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        @keyframes pop { from{transform:scale(0.9);opacity:0} to{transform:scale(1);opacity:1} }
        .mdl h3 { margin-bottom: 12px; font-size: 1.1rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; }
        .mdl p { color: var(--text-sub); font-size: 0.85rem; margin-bottom: 25px; text-transform: none; }
        .act { display: flex; gap: 12px; }
        .bo { background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text-main); }
    </style>
</head>
<body>
    <div class="con">
        <div class="card">
            <h1>Power Menu</h1>
            <p>System Controller</p>
            <div class="badge">● Instance Active</div>
            <div class="grid">
                <button class="btn btn-power" onclick="cf('reboot', 'Reboot', 'Restart system immediately?')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg> Reboot
                </button>
                <button class="btn btn-power" onclick="cf('shutdown', 'Shutdown', 'Power off device?')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg> Power Off
                </button>
                <button class="btn btn-power" onclick="cf('recovery', 'Recovery', 'Boot to recovery mode?')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg> Recovery
                </button>
                <button class="btn btn-power" onclick="cf('fastboot', 'Fastboot', 'Boot to bootloader?')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg> Fastboot
                </button>
            </div>
        </div>
    </div>
    <div id="mdl" class="ovl">
        <div class="mdl">
            <h3 id="mt"></h3>
            <p id="md"></p>
            <div class="act">
                <button class="btn bo" onclick="cl()">Cancel</button>
                <form method="POST" style="width:100%">
                    <input type="hidden" name="action" id="ma">
                    <button type="submit" class="btn btn-pri">Confirm</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        const m = document.getElementById('mdl'), mt = document.getElementById('mt'), md = document.getElementById('md'), ma = document.getElementById('ma');
        function cf(a, t, d) { mt.innerText = t; md.innerText = d; ma.value = a; m.style.display = 'flex'; }
        function cl() { m.style.display = 'none'; }
        m.addEventListener('click', e => { if(e.target===m) cl(); });
    </script>
<script src="/assets/js/main.js"></script>
</body>
</html>