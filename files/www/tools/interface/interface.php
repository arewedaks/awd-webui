<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
if (isset($_REQUEST['api'])) {
    header('Content-Type: application/json');
    $act = $_REQUEST['api'];
    function get_val($f) { return trim(@file_get_contents($f)) ?: '-'; }
    function fmt($b) {
        if ($b <= 0) return '0 B';
        $u = ['B','KB','MB','GB','TB'];
        $i = floor(log($b, 1024));
        return round($b/pow(1024, $i), 2).' '.$u[$i];
    }
    if ($act === 'stats') {
        $data = [];
        $interfaces = array_diff(scandir('/sys/class/net'), ['.', '..', 'lo']); 
        foreach($interfaces as $real) {
            $ipRaw = shell_exec("ip -4 addr show $real | awk '/inet/ {print $2}' | cut -d/ -f1");
            $ip = trim($ipRaw);
            if (empty($ip)) continue; 
            $operstate = get_val("/sys/class/net/$real/operstate");
            $status = ($operstate == 'up' || $operstate == 'unknown') ? 'ONLINE' : 'OFFLINE';
            $data[$real] = [
                'exists' => true,
                'real' => $real,
                'status' => $status,
                'mac' => get_val("/sys/class/net/$real/address"),
                'ip' => $ip,
                'rx' => fmt(get_val("/sys/class/net/$real/statistics/rx_bytes")),
                'tx' => fmt(get_val("/sys/class/net/$real/statistics/tx_bytes"))
            ];
        }
        echo json_encode($data);
    } elseif ($act === 'exec') {
        $cmd = $_POST['cmd'] ?? '';
        if ($cmd) {
            shell_exec("su -c '$cmd' 2>&1");
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Interface Manager</title>
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
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; max-width: 1100px; margin: 0 auto; -webkit-font-smoothing: antialiased;
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; padding: 22px; box-shadow: var(--shadow); border: 1px solid var(--border);
            display: flex; flex-direction: column; position: relative; overflow: hidden;
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .c-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .c-ti { display: flex; align-items: center; gap: 14px; }
        .ico { width: 48px; height: 48px; border-radius: 14px; background: var(--accent); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid var(--border); }
        .ico svg { width: 24px; height: 24px; fill: currentColor; }
        .meta h3 { font-size: 1.1rem; font-weight: 800; margin: 0; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; }
        .meta span { font-size: 0.75rem; color: var(--text-sub); font-family: 'SF Mono', monospace; font-weight: 600; }
        .bdg { font-size: 0.7rem; padding: 5px 12px; border-radius: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid var(--border); }
        .act { background: rgba(50, 215, 75, 0.15); color: var(--suc); }
        .inact { background: rgba(255, 59, 48, 0.15); color: var(--dang); }
        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; background: rgba(0,0,0,0.05); padding: 18px; border-radius: 18px; border: 1px solid var(--border); }
        .row { display: flex; flex-direction: column; overflow: hidden; }
        .lbl { font-size: 0.65rem; color: var(--text-sub); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 4px; }
        .val { font-size: 0.9rem; font-weight: 700; font-family: 'SF Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-main); }
        .acts { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: auto; }
        .btn { border: 1px solid var(--border); padding: 12px; border-radius: 14px; font-weight: 800; cursor: pointer; font-size: 0.8rem; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn:active { transform: scale(0.96); }
        .bs { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); border: none; }
        .bx { background: rgba(255, 59, 48, 0.1); color: var(--dang); }
        .bo { background: rgba(255, 255, 255, 0.05); color: var(--text-main); }
        .x-acts { margin-top: 12px; display: flex; gap: 10px; } 
        .bf { flex: 1; }
        .ovl { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(30, 18, 10, 0.6); z-index: 100; display: none; justify-content: center; align-items: center; backdrop-filter: blur(10px); }
        .mdl { background: var(--card-bg); width: 90%; max-width: 420px; border-radius: 28px; padding: 30px; box-shadow: var(--shadow); border: 1px solid var(--border); animation: pop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes pop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .mh { font-weight: 800; font-size: 1.2rem; margin-bottom: 20px; text-align: center; text-transform: uppercase; color: var(--text-main); }
        .mb label { display: block; font-size: 0.75rem; color: var(--text-sub); margin: 12px 0 6px; font-weight: 800; text-transform: uppercase; }
        .mb textarea { width: 100%; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); color: var(--text-main); border-radius: 14px; padding: 14px; font-family: 'SF Mono', monospace; font-size: 0.85rem; resize: none; min-height: 70px; }
        .mf { display: flex; gap: 12px; margin-top: 25px; }
        .skel { color: transparent; background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%); background-size: 200% 100%; animation: ld 1.5s infinite; border-radius: 4px; display: inline-block; min-width: 60px; }
        @keyframes ld { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body>

<div class="container">
    <div class="grid" id="mainGrid"></div>
</div>

<div id="em" class="ovl">
    <div class="mdl">
        <div class="mh">Configure <span id="mn" style="color:var(--primary)"></span></div>
        <div class="mb">
            <label>Enable Script</label><textarea id="ce"></textarea>
            <label>Disable Script</label><textarea id="cd"></textarea>
        </div>
        <div class="mf"><button class="btn bo bf" onclick="cl()">Cancel</button><button class="btn bs bf" onclick="sv()">Save Config</button></div>
    </div>
</div>

<script>
let ci = '';
const DEFAULTS = {
    'wlan': { 
        title: 'Wi-Fi Adapter', 
        icon: '<path d="M12 11c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 2c0-3.31-2.69-6-6-6s-6 2.69-6 6c0 2.22 1.21 4.15 3 5.19l1-1.74c-1.19-.7-2-1.97-2-3.45 0-2.21 1.79-4 4-4s4 1.79 4 4c0 1.48-.81 2.75-2 3.45l1 1.74c1.79-1.04 3-2.97 3-5.19zM12 3C6.48 3 2 7.48 2 13c0 3.7 2.01 6.92 4.99 8.65l1-1.73C5.61 18.53 4 15.96 4 13c0-4.42 3.58-8 8-8s8 3.58 8 8c0 2.96-1.61 5.53-4 6.92l1 1.73c2.99-1.73 5-4.95 5-8.65 0-5.52-4.48-10-10-10z"/>',
        en: 'svc wifi enable', dis: 'svc wifi disable' 
    },
    'ap': { 
        title: 'Wireless AP', 
        icon: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 16c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6z"/><circle cx="12" cy="12" r="2"/>',
        en: 'cmd connectivity start-tethering wifi', dis: 'cmd connectivity stop-tethering wifi' 
    },
    'rndis': { 
        title: 'USB Tether', 
        icon: '<path d="M15 7v4h1v2h-3V5h2l-3-4-3 4h2v8H8v-2.07c.7-.37 1.2-1.08 1.2-1.93 0-1.21-.99-2.2-2.2-2.2-1.21 0-2.2.99-2.2 2.2 0 .85.5 1.56 1.2 1.93V13c0 1.11.89 2 2 2h3v3.05c-.71.37-1.21 1.1-1.21 1.95 0 1.22.99 2.2 2.2 2.2 1.21 0 2.2-.98 2.2-2.2 0-.85-.5-1.58-1.21-1.95V13h3c1.11 0 2-.89 2-2v-2h1V7h-1z"/>',
        en: 'svc usb setFunctions rndis', dis: 'svc usb setFunctions none' 
    },
    'eth': { 
        title: 'Ethernet', 
        icon: '<path d="M7.77 6.76L6.23 5.48.82 12l5.41 6.52 1.54-1.28L3.42 12l4.35-5.24zM7 13h2v-2H7v2zm10-2h-2v2h2v-2zm-6 2h2v-2h-2v2zm6.77-7.52l-1.54 1.28L20.58 12l-4.35 5.24 1.54 1.28L23.18 12l-5.41-6.52z"/>',
        en: 'ifconfig {iface} up', dis: 'ifconfig {iface} down' 
    },
    'rmnet': { 
        title: 'Cellular', 
        icon: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>',
        en: 'svc data enable', dis: 'svc data disable' 
    },
    'default': {
        title: 'Network Interface',
        icon: '<path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>',
        en: 'ip link set {iface} up', dis: 'ip link set {iface} down'
    }
};
function getConfig(n) {
    if (n.includes('wlan')) return DEFAULTS['wlan'];
    if (n.includes('ap')) return DEFAULTS['ap'];
    if (n.includes('rndis')) return DEFAULTS['rndis'];
    if (n.includes('eth')) return DEFAULTS['eth'];
    if (n.includes('rmnet') || n.includes('ccmni')) return DEFAULTS['rmnet'];
    return DEFAULTS['default'];
}
function up() {
    fetch('?api=stats').then(r=>r.json()).then(d => {
        const grid = document.getElementById('mainGrid');
        const currentIds = Object.keys(d);
        document.querySelectorAll('.card').forEach(c => { if (!currentIds.includes(c.id.replace('c-', ''))) c.remove(); });
        for (const k in d) {
            const o = d[k];
            const elId = `c-${k}`;
            let el = document.getElementById(elId);
            if (!el) {
                const conf = getConfig(k);
                const html = `
                <div class="card" id="${elId}">
                    <div class="c-head">
                        <div class="c-ti">
                            <div class="ico"><svg viewBox="0 0 24 24">${conf.icon}</svg></div>
                            <div class="meta"><h3>${conf.title}</h3><span id="n-${k}">${o.real}</span></div>
                        </div>
                        <span class="bdg inact" id="s-${k}">...</span>
                    </div>
                    <div class="stats">
                        <div class="row"><span class="lbl">IPV4</span><span class="val" id="i-${k}"><span class="skel">...</span></span></div>
                        <div class="row"><span class="lbl">MAC</span><span class="val" id="m-${k}"><span class="skel">...</span></span></div>
                        <div class="row"><span class="lbl">Upload</span><span class="val" id="t-${k}"><span class="skel">...</span></span></div>
                        <div class="row"><span class="lbl">Download</span><span class="val" id="r-${k}"><span class="skel">...</span></span></div>
                    </div>
                    <div class="acts">
                        <button class="btn bs" onclick="xc('${k}','en')">Start</button>
                        <button class="btn bx" onclick="xc('${k}','dis')">Stop</button>
                    </div>
                    <div class="x-acts">
                        <button class="btn bo bf" onclick="md('${k}')">Edit</button>
                        <button class="btn bo bf" onclick="xc('${k}','rst')">Reset</button>
                    </div>
                </div>`;
                grid.insertAdjacentHTML('beforeend', html);
            }
            if (document.getElementById(`n-${k}`)) {
                const b = document.getElementById(`s-${k}`);
                b.innerText = o.status;
                b.className = o.status === 'ONLINE' ? 'bdg act' : 'bdg inact';
                document.getElementById(`i-${k}`).innerText = o.ip;
                document.getElementById(`m-${k}`).innerText = o.mac;
                document.getElementById(`t-${k}`).innerText = o.tx;
                document.getElementById(`r-${k}`).innerText = o.rx;
            }
        }
    });
}
function xc(k, a) {
    let c = '';
    const def = getConfig(k);
    if (a === 'rst') { if(!confirm('Reset commands?')) return; localStorage.removeItem(`${k}-en`); localStorage.removeItem(`${k}-dis`); return alert('Reset Done'); }
    if (a === 'en') c = localStorage.getItem(`${k}-en`) || def.en;
    if (a === 'dis') c = localStorage.getItem(`${k}-dis`) || def.dis;
    c = c.replace(/{iface}/g, k);
    const fd = new FormData(); fd.append('cmd', c);
    fetch('?api=exec', { method: 'POST', body: fd }).then(() => { setTimeout(up, 1000); });
}
function md(k) {
    ci = k; const def = getConfig(k);
    document.getElementById('mn').innerText = k.toUpperCase();
    document.getElementById('ce').value = localStorage.getItem(`${k}-en`) || def.en;
    document.getElementById('cd').value = localStorage.getItem(`${k}-dis`) || def.dis;
    document.getElementById('em').style.display = 'flex';
}
function cl() { document.getElementById('em').style.display = 'none'; }
function sv() { localStorage.setItem(`${ci}-en`, document.getElementById('ce').value); localStorage.setItem(`${ci}-dis`, document.getElementById('cd').value); cl(); }
setInterval(up, 2000);
up();
</script>
</body>
</html>