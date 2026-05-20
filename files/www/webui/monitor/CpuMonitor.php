<?php
if (isset($_GET['action']) && $_GET['action'] === 'get_data') {
    header('Content-Type: application/json');
    $i = [];
    $i['model'] = trim(shell_exec("getprop ro.soc.model")); 
    if (empty($i['model'])) $i['model'] = trim(shell_exec("cat /proc/cpuinfo | grep 'Hardware' | head -1 | cut -d ':' -f2"));
    if (empty($i['model'])) $i['model'] = 'Android Device';
    $i['cores'] = (int)shell_exec('nproc');
    $i['arch'] = trim(shell_exec("uname -m"));
    $i['gov'] = trim(@file_get_contents("/sys/devices/system/cpu/cpu0/cpufreq/scaling_governor"));
    $l = @file_get_contents('/proc/loadavg');
    $i['load'] = $l ? explode(' ', $l) : ['0.00', '0.00', '0.00'];
    $tz = glob('/sys/class/thermal/thermal_zone*/temp');
    $tm = [];
    foreach ($tz as $z) {
        $v = (int)@file_get_contents($z);
        if ($v > 10000 && $v < 100000) {
            $typePath = str_replace('temp', 'type', $z);
            $t = @file_get_contents($typePath);
            $name = trim($t);
            if(stripos($name, 'cpu') !== false || stripos($name, 'soc') !== false || stripos($name, 'bms') !== false) {
                 $tm[$name] = round($v / 1000, 1);
            }
        }
    }
    $i['temp'] = $tm;
    $fr = [];
    for ($n = 0; $n < $i['cores']; $n++) {
        $cur = (int)@file_get_contents("/sys/devices/system/cpu/cpu$n/cpufreq/scaling_cur_freq");
        $max = (int)@file_get_contents("/sys/devices/system/cpu/cpu$n/cpufreq/scaling_max_freq");
        if($max == 0) $max = 2000000;
        $fr[] = [
            'id' => $n,
            'cur' => $cur,
            'max' => $max,
            'on'  => file_exists("/sys/devices/system/cpu/cpu$n/online") ? (int)@file_get_contents("/sys/devices/system/cpu/cpu$n/online") : 1
        ];
    }
    $i['freq'] = $fr;
    $stat = explode(' ', preg_replace('!\s+!', ' ', trim(shell_exec('head -n1 /proc/stat'))));
    $i['stat'] = array_slice($stat, 1, 7);
    echo json_encode($i);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CPU Monitor</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --suc: #32d74b; --dang: #ff3b30; --warn: #ff9f0a;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; max-width: 1000px; margin: 0 auto; -webkit-font-smoothing: antialiased;
        }
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
        .gauge-val { fill: none; stroke: var(--primary); stroke-width: 10; stroke-dasharray: 440; stroke-dashoffset: 440; transition: 0.5s linear; stroke-linecap: round; }
        .gauge-text { position: absolute; text-align: center; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .gt-val { font-size: 2.2rem; font-weight: 800; color: var(--text-main); display: block; }
        .gt-lbl { font-size: 0.75rem; color: var(--text-sub); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; width: 100%; margin-bottom: 20px; }
        .info-item { padding-bottom: 8px; border-bottom: 1px dashed rgba(122, 92, 67, 0.15); }
        .info-lbl { font-size: 0.65rem; color: var(--text-sub); text-transform: uppercase; font-weight: 800; display: block; margin-bottom: 4px; }
        .info-val { font-size: 0.95rem; font-weight: 700; color: var(--text-main); }
        .load-title { font-size: 0.8rem; font-weight: 800; color: var(--text-sub); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .load-flex { display: flex; gap: 12px; }
        .load-box { flex: 1; background: rgba(0,0,0,0.05); padding: 12px; border-radius: 14px; text-align: center; border: 1px solid var(--border); }
        .lb-val { font-size: 1.1rem; font-weight: 800; color: var(--text-main); display: block; font-family: 'SF Mono', monospace; }
        .lb-lbl { font-size: 0.6rem; color: var(--text-sub); font-weight: 800; text-transform: uppercase; }
        .sec-title { font-size: 1rem; font-weight: 800; margin: 30px 0 15px; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }
        .cores-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 15px; }
        .core-card { background: var(--card-bg); backdrop-filter: var(--blur-val); padding: 18px; border-radius: 20px; border: 1px solid var(--border); box-shadow: var(--shadow); position: relative; overflow: hidden; }
        .core-head { display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--text-sub); font-weight: 800; margin-bottom: 8px; text-transform: uppercase; }
        .freq-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 10px; }
        .core-freq { font-size: 1.1rem; font-weight: 800; font-family: 'SF Mono', monospace; color: var(--primary); }
        .max-freq { font-size: 0.65rem; font-weight: 700; color: var(--text-sub); opacity: 0.7; }
        .progress-bg { height: 6px; background: rgba(0,0,0,0.1); border-radius: 3px; overflow: hidden; border: 1px solid var(--border); }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 3px; }
        .core-card.offline { opacity: 0.5; filter: grayscale(1); }
        .therm-flex { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .chip { background: var(--card-bg); backdrop-filter: var(--blur-val); border: 1px solid var(--border); padding: 8px 16px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; gap: 8px; box-shadow: var(--shadow); color: var(--text-main); text-transform: uppercase; }
        .temp-ok { color: var(--suc); } .temp-warm { color: var(--warn); } .temp-hot { color: var(--dang); }
        .skel { color: transparent; background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%); background-size: 200% 100%; animation: ld 1.5s infinite; border-radius: 4px; display: inline-block; min-width: 50px; }
        @keyframes ld { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        @media (max-width: 768px) { .dashboard { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
            CPU <span class="badge">Engine</span>
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
                        <span class="gt-val" id="cpu-pct">0%</span>
                        <span class="gt-lbl">Total Load</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="info-grid">
                <div class="info-item"><span class="info-lbl">SoC Model</span><span class="info-val" id="md">...</span></div>
                <div class="info-item"><span class="info-lbl">Arch</span><span class="info-val" id="ar">...</span></div>
                <div class="info-item"><span class="info-lbl">Cores</span><span class="info-val" id="cr">...</span></div>
                <div class="info-item"><span class="info-lbl">Scaling</span><span class="info-val" id="gv">...</span></div>
            </div>
            <div class="load-title">Load Average</div>
            <div class="load-flex">
                <div class="load-box"><span class="lb-val" id="l1">-</span><span class="lb-lbl">1m</span></div>
                <div class="load-box"><span class="lb-val" id="l5">-</span><span class="lb-lbl">5m</span></div>
                <div class="load-box"><span class="lb-val" id="l15">-</span><span class="lb-lbl">15m</span></div>
            </div>
        </div>
    </div>
    <div class="sec-title">Thermal State</div>
    <div class="therm-flex" id="therm-list"><div class="chip">Polling...</div></div>
    <div class="sec-title">Core Performance</div>
    <div class="cores-grid" id="core-list"></div>
<script>
let prevTotal = 0; let prevIdle = 0;
function updateStats() {
    fetch('?action=get_data').then(r => r.json()).then(d => {
        document.getElementById('md').innerText = d.model;
        document.getElementById('ar').innerText = d.arch;
        document.getElementById('cr').innerText = d.cores + ' Physical';
        document.getElementById('gv').innerText = d.gov;
        document.getElementById('l1').innerText = d.load[0];
        document.getElementById('l5').innerText = d.load[1];
        document.getElementById('l15').innerText = d.load[2];
        const s = d.stat.map(Number);
        const currentTotal = s.reduce((a, b) => a + b, 0);
        const currentIdle = s[3] + s[4]; 
        let percent = 0;
        if (prevTotal > 0) {
            const diffTotal = currentTotal - prevTotal;
            const diffIdle = currentIdle - prevIdle;
            percent = ((diffTotal - diffIdle) / diffTotal) * 100;
        }
        prevTotal = currentTotal; prevIdle = currentIdle;
        const p = Math.max(0, Math.min(100, percent.toFixed(1)));
        const offset = 440 - (440 * p / 100);
        const bar = document.getElementById('bar');
        bar.style.strokeDashoffset = offset;
        document.getElementById('cpu-pct').innerText = p + '%';
        bar.style.stroke = (p > 90) ? 'var(--dang)' : (p > 70) ? 'var(--warn)' : 'var(--primary)';
        let thHtml = '';
        for (const [key, val] of Object.entries(d.temp)) {
            let col = (val > 75) ? 'temp-hot' : (val > 60) ? 'temp-warm' : 'temp-ok';
            thHtml += `<div class="chip">${key} <span class="${col}">${val}°C</span></div>`;
        }
        document.getElementById('therm-list').innerHTML = thHtml || '<div class="chip">No sensors</div>';
        let coreHtml = '';
        d.freq.forEach(c => {
            const mhz = (c.cur / 1000).toFixed(0);
            const maxMhz = (c.max / 1000).toFixed(0);
            const pct = (c.cur / c.max) * 100;
            const isOff = c.on === 0;
            let barCol = (pct > 90) ? 'var(--dang)' : 'var(--primary)';
            coreHtml += `
            <div class="core-card ${isOff ? 'offline' : ''}">
                <div class="core-head"><span>Core ${c.id}</span><span>${isOff ? 'ZZZ' : pct.toFixed(0)+'%'}</span></div>
                <div class="freq-row"><span class="core-freq">${isOff ? 'OFFLINE' : mhz + ' MHz'}</span><span class="max-freq">${isOff ? '' : maxMhz + ' MHz'}</span></div>
                <div class="progress-bg"><div class="progress-fill" style="width:${isOff ? 0 : pct}%; background:${barCol}"></div></div>
            </div>`;
        });
        document.getElementById('core-list').innerHTML = coreHtml;
        document.getElementById('time').innerText = "LIVE: " + new Date().toLocaleTimeString();
    }).catch(e => console.log(e));
}
setInterval(updateStats, 2000);
updateStats();
</script>
</body>
</html>