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
        /* --- CSS VARIABLES (TRANSPARENT GLASSMORPHISM) --- */
        :root {
            /* LIGHT MODE */
            --card-bg: rgba(255, 248, 240, 0.15); /* Sangat transparan agar daun terlihat */
            --blur: blur(5px); /* Blur diturunkan */
            --text-main: #3E2A1C;
            --text-sub: #7A5C43;
            --border: rgba(255, 255, 255, 0.5); /* Border kaca jelas */
            
            --inp-bg: rgba(62, 42, 28, 0.08); 
            
            --primary: #B87333; 
            --accent: rgba(184, 115, 51, 0.15);
            
            --success: #34c759; 
            --danger: #ff3b30;
            
            /* Toggle Switch Colors */
            --tgl-bg: rgba(122, 92, 67, 0.3);
            --tgl-act: var(--primary);
            
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --radius: 24px;
        }
        
        @media (prefers-color-scheme: dark) {
            :root {
                /* DARK MODE */
                --card-bg: rgba(10, 5, 2, 0.2); /* Sangat transparan agar daun terlihat */
                --blur: blur(5px); /* Blur diturunkan */
                --text-main: #FDF5E6;
                --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.15);
                
                --inp-bg: rgba(253, 245, 230, 0.08); 
                
                --primary: #C19A6B; 
                --accent: rgba(193, 154, 107, 0.2);
                
                --success: #32d74b; 
                --danger: #ff453a;
                
                --tgl-bg: rgba(253, 245, 230, 0.3);
                --tgl-act: var(--primary);
                
                --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }

        /* --- BASE STYLES --- */
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
            background: transparent; 
            color: var(--text-main); 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
            overscroll-behavior: none; 
            -webkit-font-smoothing: antialiased;
            position: relative;
        }
        
        /* --- CANVAS FALLING LEAVES --- */
        #remote-leaves-canvas {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            pointer-events: none; 
            z-index: -1; 
        }

        .con { 
            width: 100%; 
            max-width: 500px; 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
            align-items: center; 
            position: relative;
            z-index: 1;
        }

        /* --- HEADER --- */
        .header { text-align: center; width: 100%; margin-bottom: 5px; }
        h2 { font-size: 1.4rem; font-weight: 700; color: var(--text-main); margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: 0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        p.sub { font-size: 0.85rem; color: var(--text-sub); font-weight: 500; }

        /* --- GLASSMORPHISM CARD --- */
        .card { 
            background: var(--card-bg); 
            backdrop-filter: var(--blur); 
            -webkit-backdrop-filter: var(--blur);
            border-radius: var(--radius); 
            padding: 22px; 
            width: 100%; 
            box-shadow: var(--shadow); 
            border: 1px solid var(--border); 
            position: relative;
            overflow: hidden;
        }

        .card::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            border-radius: var(--radius); box-shadow: inset 0 2px 5px rgba(255,255,255,0.2); pointer-events: none;
        }

        /* --- DEVICE WRAPPER (MINI MIRROR TAMPILAN) --- */
        .dev-wrap { 
            position: relative; 
            display: none; 
            border: 8px solid rgba(0, 0, 0, 0.15); 
            border-radius: 28px; 
            overflow: hidden; 
            box-shadow: var(--shadow); 
            background: rgba(0,0,0,0.6); 
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            line-height: 0; 
            touch-action: none; 
            min-height: 200px; 
            min-width: 100px; 
            user-select: none; 
            margin-bottom: 10px;
        }
        @media (prefers-color-scheme: dark) { .dev-wrap { border-color: rgba(255,255,255,0.1); } }
        
        .dev-wrap.active { display: inline-block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        #img { width: 100%; max-height: 70vh; display: block; pointer-events: none; border-radius: 20px; }
        #layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; cursor: pointer; }

        /* --- CONTROLS NAVIGATION (FLOATING GLASS) --- */
        .controls { 
            display: flex; 
            gap: 20px; 
            background: var(--card-bg); 
            backdrop-filter: var(--blur);
            -webkit-backdrop-filter: var(--blur);
            padding: 12px 25px; 
            border-radius: 50px; 
            border: 1px solid var(--border); 
            box-shadow: var(--shadow); 
            opacity: 0; 
            pointer-events: none; 
            transition: opacity 0.4s ease, transform 0.4s ease; 
            transform: translateY(20px);
            margin-bottom: 10px;
        }
        .controls.active { opacity: 1; pointer-events: auto; transform: translateY(0); }
        
        .btn-nav { 
            background: var(--inp-bg); 
            border: 1px solid transparent; 
            color: var(--text-main); 
            cursor: pointer; 
            width: 48px; height: 48px; 
            display: flex; align-items: center; justify-content: center; 
            border-radius: 50%; transition: 0.2s ease; 
        }
        .btn-nav:hover { background: var(--accent); color: var(--primary); border-color: var(--border); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-nav:active { transform: scale(0.9); }
        .btn-nav svg { width: 22px; height: 22px; fill: currentColor; }

        /* --- FORM ELEMENTS --- */
        .inp-grp { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; }
        .form-i { flex: 1; position: relative; z-index: 2; }
        
        label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-sub); margin-bottom: 8px; }
        
        input[type="text"] { 
            width: 100%; 
            padding: 14px; 
            border-radius: 12px; 
            border: 1px solid var(--border); 
            background: var(--inp-bg); 
            color: var(--text-main); 
            font-size: 0.95rem; 
            font-weight: 500;
            transition: all 0.2s ease;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        input[type="text"]:focus { 
            border-color: rgba(255, 255, 255, 0.6); 
            background: rgba(255, 255, 255, 0.15); 
        }
        input[type="text"]:disabled { opacity: 0.6; cursor: not-allowed; }

        /* --- TOGGLE SWITCH --- */
        .opt-row { 
            display: flex; align-items: center; justify-content: space-between; 
            background: var(--inp-bg); padding: 14px 16px; border-radius: 14px; 
            border: 1px solid var(--border); margin-bottom: 20px; position: relative; z-index: 2;
        }
        .opt-lbl { font-size: 0.9rem; color: var(--text-main); font-weight: 600; }
        
        .sw { position: relative; display: inline-block; width: 50px; height: 28px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: var(--tgl-bg); transition: .4s; border-radius: 34px; border: 1px solid var(--border); }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .sl { background: var(--tgl-act); border-color: transparent;}
        input:checked + .sl:before { transform: translateX(22px); }

        /* --- MAIN BUTTON --- */
        .btn-main { 
            width: 100%; padding: 16px; border: 1px solid var(--border); border-radius: 14px; 
            font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.2s ease; 
            color: #fff; display: flex; align-items: center; justify-content: center; gap: 8px; 
            text-transform: uppercase; letter-spacing: 0.5px; 
            position: relative; z-index: 2; overflow: hidden;
            box-shadow: inset 0 1px 1px rgba(255,255,255,0.2);
        }
        
        .btn-c { background-color: rgba(184, 115, 51, 0.85); } 
        .btn-c:hover { background-color: rgba(184, 115, 51, 1); transform: translateY(-2px); }
        
        .btn-d { background-color: rgba(255, 59, 48, 0.85); } 
        .btn-d:hover { background-color: rgba(255, 59, 48, 1); transform: translateY(-2px); }
        
        .btn-main:active { transform: scale(0.98); }
        .btn-main:disabled { opacity: 0.6; cursor: wait; transform: none; }

    </style>
</head>
<body>

<div class="con">
    
    <div class="header">
        <h2>Mini Mirror</h2>
        <p class="sub">Remote Control Dashboard</p>
    </div>

    <div class="dev-wrap" id="sb">
        <img id="img" src="" alt="Live Stream">
        <div id="layer"></div>
    </div>

    <div class="controls" id="nb">
        <button class="btn-nav" onclick="sendKey(26)" style="color:var(--danger)" title="Power Button">
            <svg viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg>
        </button>
        <button class="btn-nav" onclick="sendKey(187)" title="Recent Apps">
            <svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
        </button>
        <button class="btn-nav" onclick="sendKey(3)" title="Home">
            <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
        </button>
        <button class="btn-nav" onclick="sendKey(4)" title="Back">
            <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        </button>
    </div>

    <div class="card">
        <div class="inp-grp">
            <div class="form-i">
                <label>IP Address</label>
                <input type="text" id="ip" value="<?php echo $defaultIp; ?>">
            </div>
            <div class="form-i">
                <label>Port (Fixed)</label>
                <input type="text" value="<?php echo $defaultPort; ?>" disabled>
            </div>
        </div>

        <div class="opt-row">
            <span class="opt-lbl">Compatibility Mode</span>
            <label class="sw">
                <input type="checkbox" id="fjm">
                <span class="sl"></span>
            </label>
        </div>
        
        <button id="mb" class="btn-main btn-c" data-connected="false" onclick="tgc()">
            <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M8 5v14l11-7z"/></svg>
            Connect
        </button>
    </div>

</div>

<script>
    // ==========================================
    // LOGIKA FALLING LEAVES KHUSUS IFRAME
    // ==========================================
    const canvas = document.getElementById('remote-leaves-canvas');
    const ctx = canvas.getContext('2d');

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    const leavesCount = 40; 
    const leaves = [];
    const leafColors = ['#C19A6B', '#A47B5A', '#8B4513', '#D2B48C', '#6B4423'];

    class Leaf {
        constructor() { this.init(); }
        init() {
            this.x = Math.random() * canvas.width; 
            this.y = Math.random() * canvas.height * -1 - 20; 
            this.size = Math.random() * 8 + 4; 
            this.speed = Math.random() * 1.5 + 0.5; 
            this.color = leafColors[Math.floor(Math.random() * leafColors.length)];
            this.rotation = Math.random() * Math.PI * 2; 
            this.rotationSpeed = Math.random() * 0.02 - 0.01; 
            this.swing = Math.random() * 1.5; 
            this.swingSpeed = Math.random() * 0.02;
            this.swingOffset = Math.random() * Math.PI * 2;
            this.opacity = Math.random() * 0.5 + 0.3; 
        }
        draw() {
            ctx.save();
            ctx.translate(this.x, this.y);
            ctx.rotate(this.rotation);
            ctx.globalAlpha = this.opacity; 
            ctx.fillStyle = this.color;
            ctx.beginPath();
            ctx.moveTo(0, -this.size);
            ctx.bezierCurveTo(this.size/2, -this.size/2, this.size/2, this.size/2, 0, this.size);
            ctx.bezierCurveTo(-this.size/2, this.size/2, -this.size/2, -this.size/2, 0, -this.size);
            ctx.fill();
            ctx.restore();
        }
        update() {
            this.y += this.speed; 
            this.rotation += this.rotationSpeed; 
            this.x += Math.sin(this.swingOffset) * this.swing; 
            this.swingOffset += this.swingSpeed;
            if (this.y > canvas.height + 20) this.init();
        }
    }

    for (let i = 0; i < leavesCount; i++) leaves.push(new Leaf());

    // ==========================================
    // LOGIKA REMOTE CONTROL 
    // ==========================================
    const layer = document.getElementById('layer'), img = document.getElementById('img');
    const sb = document.getElementById('sb'), nb = document.getElementById('nb'), mb = document.getElementById('mb');
    const fjm = document.getElementById('fjm');
    const PORT = '8181';
    let rw = 0, rh = 0, rI = null, isR = false;
    let sX, sY, sT, isD = false, lTT = 0;
    
    // Auto detect IP
    if (window.location.hostname) {
        document.getElementById('ip').value = window.location.hostname;
    } else if(localStorage.getItem('rem_ip')) {
        document.getElementById('ip').value = localStorage.getItem('rem_ip');
    }
    
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

        const ujsm = fjm.checked;
        const bu = `http://${ip}:${port}`;

        if (ujsm) {
            if(rI) clearInterval(rI);
            rI = setInterval(() => { img.src = `${bu}/snapshot?t=${Date.now()}`; }, 100);
        } else {
            img.src = `${bu}/stream.mjpeg?t=${Date.now()}`;
            img.onerror = () => {
                img.onerror = null;
                fjm.checked = true;
                start(ip, port);
            };
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
        const p = getC(e);
        sX = p.x; sY = p.y;
    }

    function hE(e) {
        if (!isD) return;
        if (e.cancelable && (e.type === 'mouseup' || e.type === 'touchend')) e.preventDefault();
        isD = false;
        const p = getC(e);
        const dX = Math.abs(p.x - sX);
        const dY = Math.abs(p.y - sY);
        const dur = Date.now() - sT;

        if (dX > 15 || dY > 15) {
            sd({action: 'swipe', x1:sX, y1:sY, x2:p.x, y2:p.y, duration:Math.max(100, dur)});
        } else {
            sd({action: 'tap', x:p.x, y:p.y});
        }
    }

    layer.addEventListener('mousedown', hS);
    layer.addEventListener('touchstart', hS, {passive: false});
    layer.addEventListener('mouseup', hE);
    layer.addEventListener('touchend', hE, {passive: false});
    layer.addEventListener('mouseleave', () => isD = false);
    layer.addEventListener('touchcancel', () => isD = false);
</script>
</body>
</html>
