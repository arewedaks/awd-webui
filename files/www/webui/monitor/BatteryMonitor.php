<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
if (isset($_GET['action']) && $_GET['action'] === 'get_data') {
    header('Content-Type: application/json');
    function read_sys($f) { return file_exists($f) ? trim(file_get_contents($f)) : 'N/A'; }
    function read_int($f) { return file_exists($f) ? (int)trim(file_get_contents($f)) : 0; }
    $base = '/sys/class/power_supply/battery/';
    $data = [];
    $data['capacity'] = read_int($base.'capacity');
    $data['status']   = read_sys($base.'status');
    $data['health']   = read_sys($base.'health');
    $data['tech']     = read_sys($base.'technology');
    $data['voltage']  = round(read_int($base.'voltage_now') / 1000000, 2);
    $currentRaw = read_int($base.'current_now');
    if($currentRaw == 0) $currentRaw = read_int($base.'batt_current');
    $currentAbs = abs(round($currentRaw / 1000, 0));
    if (stripos($data['status'], 'Charging') !== false && stripos($data['status'], 'Dis') === false) {
        $data['current'] = $currentAbs;
    } else {
        $data['current'] = -1 * $currentAbs;
    }
    $data['wattage']  = round($data['voltage'] * ($currentAbs / 1000), 2);
    $data['temp']     = round(read_int($base.'temp') / 10, 1);
    $charge_full = read_int($base.'charge_full');
    $design      = read_int($base.'charge_full_design');
    if ($charge_full > 100000) $charge_full /= 1000;
    if ($design > 100000) $design /= 1000;
    $data['cap_full']   = round($charge_full);
    $data['cap_design'] = round($design);
    $data['cycle']      = read_sys($base.'cycle_count');
    $data['health_pct'] = ($data['cap_design'] > 0) ? round(($data['cap_full'] / $data['cap_design']) * 100) : 0;
    echo json_encode($data);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Battery Monitor</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
        .badge { font-size: 0.7rem; background: var(--accent); color: var(--primary); padding: 4px 12px; border-radius: 20px; font-weight: 800; border: 1px solid var(--border); }
        .last-up { font-size: 0.75rem; color: var(--text-sub); font-family: 'SF Mono', monospace; font-weight: 600; }
        .dashboard { display: grid; grid-template-columns: 280px 1fr; gap: 20px; margin-bottom: 20px; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; padding: 25px; box-shadow: var(--shadow); border: 1px solid var(--border); 
            display: flex; flex-direction: column; position: relative; overflow: hidden;
        }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .gauge-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px; }
        .gauge-svg { transform: rotate(-90deg); width: 160px; height: 160px; }
        .gauge-bg { fill: none; stroke: rgba(0,0,0,0.1); stroke-width: 10; }
        .gauge-val { fill: none; stroke: var(--primary); stroke-width: 10; stroke-dasharray: 440; stroke-dashoffset: 440; transition: 1.5s cubic-bezier(0.4, 0, 0.2, 1); stroke-linecap: round; }
        .gauge-text { position: absolute; text-align: center; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .gt-val { font-size: 2.2rem; font-weight: 800; color: var(--text-main); display: block; line-height: 1; }
        .status-pill { margin-top: 15px; padding: 6px 16px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 8px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .charging { color: var(--suc); background: rgba(50, 215, 75, 0.15); }
        .discharging { color: var(--warn); background: rgba(255, 159, 10, 0.15); }
        .sec-title { font-size: 1rem; font-weight: 800; margin-bottom: 20px; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .info-box { background: rgba(0,0,0,0.05); padding: 18px; border-radius: 18px; border: 1px solid var(--border); position: relative; overflow: hidden; }
        .ib-val { font-size: 1.2rem; font-weight: 800; color: var(--text-main); display: block; font-family: 'SF Mono', monospace; }
        .ib-lbl { font-size: 0.7rem; color: var(--text-sub); font-weight: 800; text-transform: uppercase; margin-top: 5px; display: block; letter-spacing: 0.5px; }
        .health-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px dashed rgba(122, 92, 67, 0.15); }
        .health-row:last-child { border-bottom: none; }
        .hr-lbl { font-size: 0.85rem; color: var(--text-sub); font-weight: 600; }
        .hr-val { font-weight: 800; color: var(--text-main); font-family: 'SF Mono', monospace; font-size: 0.95rem; }
        .progress-bg { height: 10px; background: rgba(0,0,0,0.1); border-radius: 5px; overflow: hidden; margin-top: 10px; border: 1px solid var(--border); }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; border-radius: 5px; transition: width 1.5s ease; }
        .t-hot { color: var(--dang) !important; }
        .t-warm { color: var(--warn) !important; }
        @media (max-width: 768px) { .dashboard { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle"><rect x="2" y="7" width="16" height="10" rx="2" ry="2"></rect><line x1="22" y1="11" x2="22" y2="13"></line></svg>
            Battery <span class="badge">Engine</span>
        </h1>
        <span class="last-up" id="time">Check...</span>
    </div>
    <div class="dashboard">
        <div class="card">
            <div class="gauge-wrapper">
                <div style="position:relative; width:160px; height:160px;">
                    <svg class="gauge-svg" viewBox="0 0 160 160">
                        <circle class="gauge-bg" cx="80" cy="80" r="70"></circle>
                        <circle class="gauge-val" cx="80" cy="80" r="70" id="bar"></circle>
                    </svg>
                    <div class="gauge-text">
                        <span class="gt-val" id="cap">0%</span>
                        <div class="status-pill" id="st-pill">
                            <div class="dot"></div> <span id="status">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="sec-title">Power Analytics</div>
            <div class="info-grid">
                <div class="info-box"><span class="ib-val" id="vol">0 V</span><span class="ib-lbl">Voltage</span></div>
                <div class="info-box"><span class="ib-val" id="cur">0 mA</span><span class="ib-lbl">Current</span></div>
                <div class="info-box"><span class="ib-val" id="tmp">0°C</span><span class="ib-lbl">Temperature</span></div>
                <div class="info-box"><span class="ib-val" id="watt">0 W</span><span class="ib-lbl">Wattage</span></div>
            </div>
        </div>
        <div class="card" style="grid-column: 1 / -1;">
            <div class="sec-title">Health & Lifespan</div>
            <div style="display: flex; flex-wrap: wrap; gap: 40px;">
                <div style="flex:1; min-width: 260px;">
                    <div class="health-row"><span class="hr-lbl">Condition</span><span class="hr-val" id="hlt" style="color:var(--suc)">-</span></div>
                    <div class="health-row"><span class="hr-lbl">Technology</span><span class="hr-val" id="tech">-</span></div>
                    <div class="health-row"><span class="hr-lbl">Cycle Count</span><span class="hr-val" id="cyc">-</span></div>
                </div>
                <div style="flex:1; min-width: 260px;">
                    <div class="health-row"><span class="hr-lbl">Actual Charge</span><span class="hr-val" id="cap-real">0 mAh</span></div>
                    <div class="health-row"><span class="hr-lbl">Design Peak</span><span class="hr-val" id="cap-des">0 mAh</span></div>
                    <div style="margin-top:15px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.7rem; color:var(--text-sub); font-weight:800; text-transform:uppercase;">
                            <span>Wear Efficiency</span><span id="health-pct-txt">0%</span>
                        </div>
                        <div class="progress-bg"><div class="progress-fill" id="health-bar"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
function updateStats() {
    fetch('?action=get_data').then(r => r.json()).then(d => {
        const p = d.capacity;
        const offset = 440 - (440 * p / 100);
        const bar = document.getElementById('bar');
        bar.style.strokeDashoffset = offset;
        document.getElementById('cap').innerText = p + '%';
        if(p <= 15) bar.style.stroke = 'var(--dang)';
        else if(p <= 30) bar.style.stroke = 'var(--warn)';
        else bar.style.stroke = 'var(--primary)';
        const pill = document.getElementById('st-pill');
        document.getElementById('status').innerText = d.status;
        pill.className = (d.status.toLowerCase().includes('charging') && !d.status.toLowerCase().includes('dis')) ? 'status-pill charging' : 'status-pill discharging';
        document.getElementById('vol').innerText = d.voltage + ' V';
        const curr = d.current;
        const sign = curr > 0 ? '+' : ''; 
        const curEl = document.getElementById('cur');
        curEl.innerText = sign + curr + ' mA';
        curEl.style.color = curr > 0 ? 'var(--suc)' : 'var(--text-main)';
        const t = d.temp;
        const tEl = document.getElementById('tmp');
        tEl.innerText = t + '°C';
        tEl.className = 'ib-val';
        if(t > 40) tEl.classList.add('t-warm');
        if(t > 45) tEl.classList.add('t-hot');
        document.getElementById('watt').innerText = d.wattage + ' W';
        document.getElementById('hlt').innerText = d.health;
        document.getElementById('tech').innerText = d.tech;
        document.getElementById('cyc').innerText = d.cycle != 'N/A' ? d.cycle : 'N/A';
        document.getElementById('cap-real').innerText = d.cap_full + ' mAh';
        document.getElementById('cap-des').innerText = d.cap_design + ' mAh';
        if(d.health_pct > 0) {
            document.getElementById('health-pct-txt').innerText = d.health_pct + '%';
            document.getElementById('health-bar').style.width = d.health_pct + '%';
            const hb = document.getElementById('health-bar');
            if(d.health_pct < 60) hb.style.background = 'var(--dang)';
            else if(d.health_pct < 80) hb.style.background = 'var(--warn)';
            else hb.style.background = 'var(--primary)';
        }
        document.getElementById('time').innerText = "LIVE: " + new Date().toLocaleTimeString();
    }).catch(e => console.log(e));
}
setInterval(updateStats, 3000);
updateStats();
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>