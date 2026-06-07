<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $a = $_GET['action'];
    if ($a === 'get_data') {
        $i = [];
        $m = @file_get_contents('/proc/meminfo');
        if ($m) {
            $l = explode("\n", $m);
            $d = [];
            foreach ($l as $r) {
                if (empty($r)) continue;
                $p = explode(':', $r);
                $d[trim($p[0])] = (int)preg_replace('/\D/', '', $p[1]);
            }
            $i['t'] = round($d['MemTotal']/1024, 2);
            $i['f'] = round($d['MemFree']/1024, 2);
            $i['a'] = round($d['MemAvailable']/1024, 2);
            $i['c'] = round($d['Cached']/1024, 2);
            $i['st'] = round($d['SwapTotal']/1024, 2);
            $i['su'] = $i['st'] - round($d['SwapFree']/1024, 2);
            $i['u'] = $i['t'] - $i['a'];
            $i['up'] = ($i['t']>0) ? round(($i['u']/$i['t'])*100, 1) : 0;
            $i['sup'] = ($i['st']>0) ? round(($i['su']/$i['st'])*100, 1) : 0;
        }
        $p = [];
        $o = shell_exec('ps -eo pid,user,%mem,%cpu,comm --sort=-%mem | head -n 50');
        if ($o) {
            $l = explode("\n", trim($o)); array_shift($l);
            foreach ($l as $r) {
                $x = preg_split('/\s+/', trim($r));
                if (count($x) >= 5) {
                    $p[] = ['pid' => $x[0], 'user' => $x[1], 'mem' => $x[2], 'cpu' => $x[3], 'name' => basename($x[4])];
                }
            }
        }
        $i['proc'] = $p;
        echo json_encode($i);
    } 
    elseif ($a === 'clean') {
        shell_exec('sync; echo 3 > /proc/sys/vm/drop_caches; sysctl vm.drop_caches=3; swapoff -a && swapon -a 2>&1');
        echo json_encode(['ok'=>true]);
    } 
    elseif ($a === 'kill' && isset($_GET['pid'])) {
        shell_exec("kill -9 ".(int)$_GET['pid']." 2>&1");
        echo json_encode(['ok'=>true]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Monitor</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
        .badge { font-size: 0.7rem; background: var(--accent); color: var(--primary); padding: 4px 12px; border-radius: 20px; font-weight: 800; border: 1px solid var(--border); }
        .last-up { font-size: 0.75rem; color: var(--text-sub); font-family: 'SF Mono', monospace; font-weight: 600; }
        .dashboard { display: grid; grid-template-columns: 280px 1fr; gap: 20px; margin-bottom: 30px; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; padding: 25px; box-shadow: var(--shadow); border: 1px solid var(--border); 
            display: flex; flex-direction: column; position: relative; overflow: hidden;
        }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .gauge-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; }
        .gauge-svg { transform: rotate(-90deg); width: 160px; height: 160px; }
        .gauge-bg { fill: none; stroke: rgba(0,0,0,0.1); stroke-width: 10; }
        .gauge-val { fill: none; stroke: var(--primary); stroke-width: 10; stroke-dasharray: 440; stroke-dashoffset: 440; transition: 1.5s cubic-bezier(0.4, 0, 0.2, 1); stroke-linecap: round; }
        .gauge-text { position: absolute; text-align: center; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .gt-val { font-size: 2.2rem; font-weight: 800; color: var(--text-main); display: block; line-height: 1; }
        .gt-lbl { font-size: 0.7rem; color: var(--text-sub); font-weight: 800; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .stat-box { background: rgba(0,0,0,0.05); border-radius: 18px; padding: 18px; border: 1px solid var(--border); }
        .sb-head { font-size: 0.65rem; text-transform: uppercase; color: var(--text-sub); font-weight: 800; margin-bottom: 6px; display: flex; justify-content: space-between; letter-spacing: 0.5px; }
        .sb-val { font-size: 1.1rem; font-weight: 800; color: var(--text-main); font-family: 'SF Mono', monospace; }
        .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: var(--primary); }
        .proc-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .search-box { position: relative; flex: 1; max-width: 320px; }
        .search-inp { 
            width: 100%; padding: 12px 15px 12px 40px; border-radius: 14px; border: 1px solid var(--border); 
            background: rgba(255, 255, 255, 0.05); color: var(--text-main); font-size: 0.9rem; font-weight: 600; transition: 0.3s;
        }
        .search-inp:focus { border-color: var(--primary); background: rgba(255,255,255,0.1); }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; opacity: 0.6; fill: var(--text-main); }
        .table-wrap { overflow-x: auto; max-height: 550px; overflow-y: auto; border-radius: 20px; border: 1px solid var(--border); background: rgba(0,0,0,0.02); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 18px; background: rgba(62, 42, 28, 0.05); color: var(--text-sub); font-size: 0.7rem; text-transform: uppercase; font-weight: 800; position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border); letter-spacing: 0.5px; }
        td { padding: 12px 18px; border-bottom: 1px dashed rgba(122, 92, 67, 0.1); font-size: 0.85rem; color: var(--text-main); vertical-align: middle; }
        tr:hover { background: rgba(255,255,255,0.05); }
        .proc-name { font-weight: 800; display: block; color: var(--text-main); }
        .proc-user { font-size: 0.7rem; color: var(--text-sub); font-weight: 600; text-transform: uppercase; }
        .tag { padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; font-family: 'SF Mono', monospace; border: 1px solid var(--border); }
        .tag-mem { background: var(--accent); color: var(--primary); }
        .tag-cpu { background: rgba(50, 215, 75, 0.1); color: var(--suc); }
        .btn-main { 
            background: var(--primary); color: #fff; border: none; padding: 14px 20px; border-radius: 16px; 
            font-weight: 800; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); 
            text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem;
        }
        .btn-main:active { transform: scale(0.96); }
        .btn-kill { background: rgba(255, 59, 48, 0.1); border: 1px solid rgba(255, 59, 48, 0.2); color: var(--dang); padding: 6px 12px; border-radius: 10px; font-size: 0.7rem; cursor: pointer; transition: 0.2s; font-weight: 800; text-transform: uppercase; }
        .btn-kill:hover { background: var(--dang); color: #fff; }
        @media (max-width: 768px) { .dashboard { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            System <span class="badge">Resources</span>
        </h1>
        <span class="last-up" id="time">Check...</span>
    </div>
    <div class="dashboard">
        <div class="card">
            <div class="gauge-wrapper">
                <div style="position:relative; width:160px; height:160px; margin-bottom:25px;">
                    <svg class="gauge-svg" viewBox="0 0 160 160">
                        <circle class="gauge-bg" cx="80" cy="80" r="70"></circle>
                        <circle class="gauge-val" cx="80" cy="80" r="70" id="bar"></circle>
                    </svg>
                    <div class="gauge-text">
                        <span class="gt-val" id="pct">0%</span>
                        <span class="gt-lbl">RAM Used</span>
                    </div>
                </div>
                <button class="btn-main" onclick="cleanRam()" style="width:100%">Boost Engine</button>
            </div>
        </div>
        <div class="card">
            <div style="margin-bottom:20px; font-weight:800; color:var(--text-main); display:flex; gap:10px; align-items:center; text-transform:uppercase; font-size:0.85rem; letter-spacing:1px;">
                <span class="dot"></span> Memory Analytics
            </div>
            <div class="stats-grid">
                <div class="stat-box"><div class="sb-head">Hardware RAM</div><div class="sb-val" id="rt">-</div></div>
                <div class="stat-box"><div class="sb-head">Available</div><div class="sb-val" id="ra">-</div></div>
                <div class="stat-box"><div class="sb-head">Cached Buffer</div><div class="sb-val" id="rc">-</div></div>
                <div class="stat-box"><div class="sb-head">Virtual Swap <span id="spct" style="opacity:0.6"></span></div><div class="sb-val" id="su">-</div></div>
            </div>
        </div>
    </div>
    <div class="card" style="padding:0; overflow:hidden;">
        <div style="padding:25px 25px 15px;">
            <div class="proc-head">
                <h3 style="margin:0; font-weight:800; text-transform:uppercase; font-size:1rem; letter-spacing:1px;">Process Auditor <span style="font-size:0.75rem; color:var(--text-sub); opacity:0.6;">(Top 50)</span></h3>
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="search" class="search-inp" placeholder="Filter command..." onkeyup="filterProc()">
                </div>
            </div>
        </div>
        <div class="table-wrap">
            <table id="procTable">
                <thead><tr><th width="45%">Application</th><th width="15%">RAM</th><th width="15%">CPU</th><th width="25%" style="text-align:right">Control</th></tr></thead>
                <tbody id="pl"><tr><td colspan="4" style="text-align:center; padding:40px; opacity:0.5;">Initializing...</td></tr></tbody>
            </table>
        </div>
    </div>
<script>
let procData = [];
function updateStats() {
    fetch('?action=get_data').then(r => r.json()).then(d => {
        document.getElementById('rt').innerText = d.t + ' MB';
        document.getElementById('ra').innerText = d.a + ' MB';
        document.getElementById('rc').innerText = d.c + ' MB';
        document.getElementById('su').innerText = d.su + ' MB';
        document.getElementById('spct').innerText = '(' + d.sup + '%)';
        const p = d.up;
        const offset = 440 - (440 * p / 100);
        const bar = document.getElementById('bar');
        bar.style.strokeDashoffset = offset;
        document.getElementById('pct').innerText = p + '%';
        bar.style.stroke = (p > 85) ? 'var(--dang)' : (p > 60) ? 'var(--warn)' : 'var(--primary)';
        procData = d.proc;
        renderTable(procData);
        document.getElementById('time').innerText = "LIVE: " + new Date().toLocaleTimeString();
    }).catch(e => console.log(e));
}
function renderTable(data) {
    const tbody = document.getElementById('pl');
    const filter = document.getElementById('search').value.toLowerCase();
    const filtered = data.filter(i => i.name.toLowerCase().includes(filter) || i.user.toLowerCase().includes(filter) || i.pid.toString().includes(filter));
    if(filtered.length === 0) { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px;">No process matched</td></tr>'; return; }
    let h = '';
    filtered.forEach(x => {
        h += `<tr><td><span class="proc-name">${x.name}</span><span class="proc-user">${x.user} • PID ${x.pid}</span></td>
            <td><span class="tag tag-mem">${x.mem}%</span></td><td><span class="tag tag-cpu">${x.cpu}%</span></td>
            <td style="text-align:right"><button class="btn-kill" onclick="killProc(${x.pid}, '${x.name}')">Kill</button></td></tr>`;
    });
    tbody.innerHTML = h;
}
function filterProc() { renderTable(procData); }
function cleanRam() { if(confirm("Boost memory by clearing caches?")) { fetch('?action=clean').then(() => { updateStats(); }); } }
function killProc(pid, name) { if(confirm("Kill process " + name + "?")) { fetch('?action=kill&pid=' + pid).then(() => { updateStats(); }); } }
setInterval(updateStats, 3000);
updateStats();
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>