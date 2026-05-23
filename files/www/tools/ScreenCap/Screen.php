<?php
$defaultIp   = '192.168.8.1'; 
$defaultPort = '8181';
function exec_bg($cmd) { return shell_exec("su -c '$cmd > /dev/null 2>&1 &'"); }
function exec_sync($cmd) { return shell_exec("su -c '$cmd'"); }
if (isset($_GET['action']) && $_GET['action'] == 'get_size') {
    header('Content-Type: application/json');
    $output = shell_exec("su -c 'wm size'");
    if (preg_match('/(\d+)x(\d+)/', $output, $matches)) {
        echo json_encode(['width' => (int)$matches[1], 'height' => (int)$matches[2]]);
    } else { echo json_encode(['width' => 1080, 'height' => 2400]); }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'start_stream') {
        exec_sync("pkill -x minicap"); 
        exec_sync("pkill -f minicap_server.py");
        exec_sync("pkill -f stream.py");
        $cmd = "/data/data/com.termux/files/usr/bin/python /data/adb/php8/scripts/minicap_server.py";
        exec_bg($cmd);
        echo "Started";
    }
    elseif ($action === 'stop_stream') {
        exec_sync("pkill -x minicap"); 
        exec_sync("pkill -f minicap_server.py");
        echo "Stopped";
    }
    elseif ($action === 'tap') { exec_bg("input tap {$_POST['x']} {$_POST['y']}"); }
    elseif ($action === 'swipe') { exec_bg("input swipe {$_POST['x1']} {$_POST['y1']} {$_POST['x2']} {$_POST['y2']} {$_POST['duration']}"); }
    elseif ($action === 'key') { exec_bg("input keyevent {$_POST['code']}"); }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Remote Control</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --dang: #ff3b30;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; background: transparent !important; color: var(--text-main); display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding: 20px; overscroll-behavior: none; -webkit-font-smoothing: antialiased; }
        .con { width: 100%; max-width: 600px; display: flex; flex-direction: column; gap: 20px; align-items: center; }
        .header { text-align: center; width: 100%; margin-bottom: 10px; }
        h2 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: 1px; }
        p.sub { font-size: 0.75rem; color: var(--text-sub); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 28px; padding: 25px; width: 100%; box-shadow: var(--shadow); border: 1px solid var(--border); 
            position: relative; overflow: hidden;
        }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .dev-wrap { position: relative; display: inline-block; border: 6px solid rgba(0,0,0,0.5); border-radius: 28px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.4); background: #000; line-height: 0; touch-action: none; min-height: 200px; min-width: 100px; display: none; user-select: none; }
        .dev-wrap.active { display: inline-block; animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        #img { width: 100%; max-height: 70vh; display: block; pointer-events: none; border-radius: 22px; }
        #layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; cursor: pointer; }
        .controls { display: flex; gap: 18px; background: var(--card-bg); backdrop-filter: var(--blur-val); padding: 12px 25px; border-radius: 50px; border: 1px solid var(--border); box-shadow: var(--shadow); opacity: 0.5; pointer-events: none; transition: opacity 0.3s; }
        .controls.active { opacity: 1; pointer-events: auto; }
        .btn-nav { background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text-main); cursor: pointer; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .btn-nav:hover { background: var(--accent); color: var(--primary); border-color: var(--primary); }
        .btn-nav:active { transform: scale(0.9); }
        .btn-nav svg { width: 22px; height: 22px; fill: currentColor; }
        .inp-grp { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; }
        .form-i { flex: 1; }
        label { display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-sub); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        input { width: 100%; padding: 14px; border-radius: 14px; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: var(--text-main); font-size: 0.95rem; font-weight: 600; font-family: 'SF Mono', monospace; transition: 0.3s; }
        input:focus { border-color: var(--primary); background: rgba(255,255,255,0.1); }
        input:disabled { opacity: 0.6; }
        .opt-row { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.05); padding: 14px 18px; border-radius: 16px; border: 1px solid var(--border); margin-top: 15px; }
        .opt-lbl { font-size: 0.8rem; color: var(--text-main); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .sw { position: relative; display: inline-block; width: 50px; height: 28px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.1); transition: .4s; border-radius: 34px; border: 1px solid var(--border); }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .sl { background: var(--primary); }
        input:checked + .sl:before { transform: translateX(22px); }
        .btn-main { width: 100%; padding: 16px; border: none; border-radius: 16px; font-weight: 800; font-size: 0.9rem; cursor: pointer; transition: 0.3s; color: white; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 25px; text-transform: uppercase; letter-spacing: 1px; }
        .btn-c { background: var(--primary); box-shadow: 0 4px 15px rgba(184, 115, 51, 0.25); }
        .btn-d { background: rgba(255, 59, 48, 0.15); color: var(--dang); border: 1px solid rgba(255, 59, 48, 0.3); }
        .btn-main:active { transform: scale(0.97); }
        .btn-main:disabled { opacity: 0.7; cursor: wait; }
    </style>
</head>
<body>
<div class="con">
    <div class="header">
        <h2>Mini Mirror</h2>
        <p class="sub">Remote Control Engine</p>
    </div>
    <div class="dev-wrap" id="sb">
        <img id="img" src="" alt="Live Stream">
        <div id="layer"></div>
    </div>
    <div class="controls" id="nb">
        <button class="btn-nav" onclick="sendKey(4)" title="Back"><svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button>
        <button class="btn-nav" onclick="sendKey(3)" title="Home"><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></button>
        <button class="btn-nav" onclick="sendKey(187)" title="Recent"><svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg></button>
        <button class="btn-nav" onclick="sendKey(26)" style="color:var(--dang); border-color:var(--dang);" title="Power"><svg viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg></button>
    </div>
    <div class="card">
        <div class="inp-grp">
            <div class="form-i"><label>IP Address</label><input type="text" id="ip" value="<?php echo $defaultIp; ?>"></div>
            <div class="form-i"><label>Port (Fixed)</label><input type="text" value="<?php echo $defaultPort; ?>" disabled></div>
        </div>
        <div class="opt-row">
            <span class="opt-lbl">Compatibility Mode</span>
            <label class="sw"><input type="checkbox" id="fjm"><span class="sl"></span></label>
        </div>
        <button id="mb" class="btn-main btn-c" data-connected="false" onclick="tgc()">
            <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M8 5v14l11-7z"/></svg> Connect
        </button>
    </div>
</div>
<script>
    const layer = document.getElementById('layer'), img = document.getElementById('img');
    const sb = document.getElementById('sb'), nb = document.getElementById('nb'), mb = document.getElementById('mb');
    const fjm = document.getElementById('fjm');
    const PORT = '8181';
    let rw = 0, rh = 0, rI = null, isR = false;
    let sX, sY, sT, isD = false, lTT = 0;
    if (window.location.hostname) { document.getElementById('ip').value = window.location.hostname; } 
    else if(localStorage.getItem('rem_ip')) { document.getElementById('ip').value = localStorage.getItem('rem_ip'); }
    fetch('?action=get_size').then(r => r.json()).then(d => { rw = d.width; rh = d.height; });
    function tgc() {
        if (!isR) {
            mb.disabled = true; mb.innerHTML = 'Starting...';
            const ip = document.getElementById('ip').value;
            localStorage.setItem('rem_ip', ip);
            const fd = new FormData(); fd.append('action', 'start_stream');
            fetch('', { method: 'POST', body: fd }).then(() => { setTimeout(() => start(ip, PORT), 1500); });
        } else {
            stop();
            const fd = new FormData(); fd.append('action', 'stop_stream');
            fetch('', { method: 'POST', body: fd });
        }
    }
    function start(ip, port) {
        isR = true; sb.classList.add('active'); nb.classList.add('active');
        mb.innerHTML = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M6 6h12v12H6z"/></svg> Disconnect';
        mb.className = "btn-main btn-d"; mb.disabled = false;
        const ujsm = fjm.checked; const bu = `http://${ip}:${port}`;
        if (ujsm) {
            if(rI) clearInterval(rI);
            rI = setInterval(() => { img.src = `${bu}/snapshot?t=${Date.now()}`; }, 100);
        } else {
            img.src = `${bu}/stream.mjpeg?t=${Date.now()}`;
            img.onerror = () => { img.onerror = null; fjm.checked = true; start(ip, port); };
        }
    }
    function stop() {
        isR = false; if(rI) clearInterval(rI); img.src = "";
        sb.classList.remove('active'); nb.classList.remove('active');
        mb.innerHTML = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M8 5v14l11-7z"/></svg> Connect';
        mb.className = "btn-main btn-c"; mb.disabled = false;
    }
    function sd(d) {
        const fd = new FormData();
        for(let k in d) fd.append(k, d[k]);
        fetch('', {method:'POST', body:fd});
    }
    function sendKey(code) { sd({action:'key', code:code}); }
    function getC(e) {
        const r = layer.getBoundingClientRect();
        let cx, cy;
        if (e.changedTouches) { cx = e.changedTouches[0].clientX; cy = e.changedTouches[0].clientY; }
        else { cx = e.clientX; cy = e.clientY; }
        return { x: Math.round((cx - r.left) * (rw / r.width)), y: Math.round((cy - r.top) * (rh / r.height)) };
    }
    function hS(e) {
        if (e.type === 'mousedown' && (Date.now() - lTT < 500)) return;
        if (e.type === 'touchstart') lTT = Date.now();
        if (e.type === 'mousedown') e.preventDefault(); 
        isD = true; sT = Date.now();
        const p = getC(e); sX = p.x; sY = p.y;
    }
    function hE(e) {
        if (!isD) return;
        if (e.cancelable && (e.type === 'mouseup' || e.type === 'touchend')) e.preventDefault();
        isD = false;
        const p = getC(e);
        const dX = Math.abs(p.x - sX), dY = Math.abs(p.y - sY), dur = Date.now() - sT;
        if (dX > 15 || dY > 15) { sd({action: 'swipe', x1:sX, y1:sY, x2:p.x, y2:p.y, duration:Math.max(100, dur)}); } 
        else { sd({action: 'tap', x:p.x, y:p.y}); }
    }
    layer.addEventListener('mousedown', hS); layer.addEventListener('touchstart', hS, {passive: false});
    layer.addEventListener('mouseup', hE); layer.addEventListener('touchend', hE, {passive: false});
    layer.addEventListener('mouseleave', () => isD = false); layer.addEventListener('touchcancel', () => isD = false);
</script>
</body>
</html>