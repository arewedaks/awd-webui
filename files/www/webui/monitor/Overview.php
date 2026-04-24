<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

if (isset($_GET['api']) && $_GET['api'] === 'get_all') {
    header('Content-Type: application/json');

    function getCmd($c) { return trim(shell_exec($c)); }
    function fmtB($b) { 
        if ($b<=0) return '0 B'; 
        $u=['B','KB','MB','GB','TB']; 
        $i=floor(log($b,1024)); 
        return round($b/pow(1024,$i),2).' '.$u[$i]; 
    }

    $data = [];

    // Mengambil data utama
    $lines = explode("\n", getCmd("cat /proc/uptime; getprop ro.product.model; getprop ro.build.version.release; getprop gsm.sim.operator.alpha; getprop ro.soc.model"));
    $up = floatval(explode(' ', $lines[0]??'0')[0]);
    $days = floor($up/86400); $hours = floor(($up%86400)/3600); $mins = floor(($up%3600)/60);
    
    $hw = trim($lines[4]??'');
    if ($hw===''||$hw==='Unknown SoC') {
        if(preg_match('/Hardware\s+:\s+([^\n]+)/', @file_get_contents('/proc/cpuinfo'), $m)) $hw=trim($m[1]);
    }

    $data['system'] = [
        'uptime' => "{$days}d {$hours}h {$mins}m",
        'model' => trim($lines[1]??'Android'),
        'os' => trim($lines[2]??'-'),
        'soc' => $hw,
        'operator' => trim($lines[3]??'No SIM'),
        'cores' => substr_count(@file_get_contents('/proc/cpuinfo'), 'processor')
    ];

    $data['battery'] = [
        'level' => trim(@file_get_contents('/sys/class/power_supply/battery/capacity')),
        'status' => trim(@file_get_contents('/sys/class/power_supply/battery/status')) ?: 'Unknown',
        'voltage' => number_format(floatval(trim(@file_get_contents('/sys/class/power_supply/battery/voltage_now')))/1000000, 2).'V',
        'temp' => number_format(floatval(trim(@file_get_contents('/sys/class/power_supply/battery/temp')))/10, 1)
    ];

    $netList = [];
    $ipMap = [];
    foreach(explode("\n", getCmd("ip -o -4 addr show | awk '{print $2, $4}'")) as $r) {
        $c = preg_split('/\s+/', $r);
        if(isset($c[1])) $ipMap[$c[0]] = explode('/', $c[1])[0];
    }
    foreach(explode("\n", @file_get_contents('/proc/net/dev')) as $l) {
        if(strpos($l,':')===false) continue;
        list($if,$s) = explode(':',$l); $if=trim($if);
        if($if=='lo') continue;
        $c = preg_split('/\s+/', trim($s));
        $rx=$c[0]??0; $tx=$c[8]??0;
        
        if($rx>0 || isset($ipMap[$if])) {
            $fmt = function($b){return ($b>1073741824)?round($b/1073741824,2).' GB':round($b/1048576,2).' MB';};
            $netList[] = ['name'=>$if, 'ip'=>$ipMap[$if]??'-', 'rx'=>$fmt($rx), 'tx'=>$fmt($tx)];
        }
    }
    $data['network'] = $netList;

    $signals = [];
    $dump = getCmd('dumpsys telephony.registry');
    
    $rawNames = $lines[3] ?? '';
    $simNames = [];
    if (!empty($rawNames)) {
        $simNames = explode(',', $rawNames);
    }

    if(!empty($dump) && preg_match_all('/CellSignalStrength(Lte|Nr|Gsm|Wcdma|Tdscdma):(.+?)(?=CellSignalStrength|$)/s', $dump, $matches, PREG_SET_ORDER)) {
        $count = 0; 
        foreach($matches as $m) {
            if ($count >= 2) break;

            $type = strtoupper($m[1]);
            $ld = $m[2];
            
            preg_match('/rssi=([-\d]+)/',$ld,$rssi); 
            preg_match('/rsrp=([-\d]+)/',$ld,$rsrp);
            preg_match('/rsrq=([-\d]+)/',$ld,$rsrq); 
            preg_match('/rssnr=([-\d]+)/',$ld,$sinr);
            preg_match('/level=(\d)/',$ld,$lvl);

            $i_lvl = (int)($lvl[1]??0);
            if ($i_lvl === 0 && !isset($rsrp[1])) continue;

            $v_rsrp = (isset($rsrp[1]) && abs($rsrp[1]) < 200) ? $rsrp[1] : 'N/A';
            $v_rssi = (isset($rssi[1]) && abs($rssi[1]) < 200) ? $rssi[1] : 'N/A';

            $pName = 'SIM '.($count+1);
            if (isset($simNames[$count]) && trim($simNames[$count]) !== '') {
                $pName = trim($simNames[$count]);
            }

            $signals[] = [
                'provider' => $pName,
                'type' => $type, 
                'level' => $i_lvl,
                'rsrp' => $v_rsrp, 
                'rssi' => $v_rssi,
                'sinr' => $sinr[1]??'N/A', 
                'rsrq' => $rsrq[1]??'N/A'
            ];
            $count++; 
        }
    }

    if (empty($signals)) {
        $pName = isset($simNames[0]) ? $simNames[0] : 'No SIM';
        $signals[] = ['provider'=>$pName, 'type'=>'NO SIGNAL', 'level'=>0, 'rsrp'=>'N/A', 'rssi'=>'N/A', 'sinr'=>'N/A', 'rsrq'=>'N/A'];
    }
    
    $data['signal'] = $signals; 

    $parts = [];
    foreach(['data'=>'/data','system'=>'/system','sdcard'=>'/sdcard'] as $k=>$p) {
        $df = getCmd("df $p 2>/dev/null | tail -n1");
        if($df && $df!=='0') {
            $s = preg_split('/\s+/', trim($df));
            if(count($s)>=6) {
                $parts[] = [
                    'name'=>strtoupper($k), 
                    'free'=>fmtB($s[3]*1024), 
                    'pct'=>str_replace('%','',$s[4])
                ];
            }
        }
    }
    $data['storage'] = $parts;
    
    $tCache = 0;
    $cList = [];
    foreach(['Dalvik'=>'/data/dalvik-cache','App'=>'/data/data/*/cache'] as $n=>$p) {
        $b = (int)getCmd("du -sk $p 2>/dev/null | awk '{print $1}'") * 1024;
        $cList[] = ['name'=>$n, 'size'=>fmtB($b)];
        $tCache += $b;
    }
    $data['cache'] = ['total'=>fmtB($tCache), 'details'=>$cList];

    echo json_encode($data);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lite Dashboard</title>
  <style>
    /* --- CSS VARIABLES (TRANSPARENT GLASSMORPHISM) --- */
    :root {
        /* LIGHT MODE */
        --card-bg: rgba(255, 248, 240, 0.15); 
        --blur: blur(5px); 
        --text-main: #3E2A1C;
        --text-sub: #7A5C43;
        --border: rgba(255, 255, 255, 0.5); 
        --border-dashed: rgba(122, 92, 67, 0.2);
        
        --primary: #B87333; 
        --accent: rgba(184, 115, 51, 0.15);
        
        --success: #34c759; 
        --danger: #ff3b30;
        
        --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
        --radius: 24px;
        --inner-radius: 12px;
    }
    
    @media (prefers-color-scheme: dark) {
        :root {
            /* DARK MODE */
            --card-bg: rgba(10, 5, 2, 0.2); 
            --blur: blur(5px); 
            --text-main: #FDF5E6;
            --text-sub: #C0B2A2;
            --border: rgba(255, 255, 255, 0.15);
            --border-dashed: rgba(253, 245, 230, 0.15);
            
            --primary: #C19A6B; 
            --accent: rgba(193, 154, 107, 0.2);
            
            --success: #32d74b; 
            --danger: #ff453a;
            
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
        }
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body { 
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
        background: transparent; /* TRANSPARAN TOTAL */
        color: var(--text-main); 
        padding: 20px; 
        font-size: 14px; 
        -webkit-font-smoothing: antialiased;
        position: relative; 
    }
    
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; max-width: 1200px; margin: 0 auto; position: relative; z-index: 1; }
    
    /* STYLE CARD GLASSMORPHISM */
    .card { 
        background: var(--card-bg); 
        backdrop-filter: var(--blur); 
        -webkit-backdrop-filter: var(--blur);
        border: 1px solid var(--border); 
        border-radius: var(--radius); 
        padding: 22px; 
        box-shadow: var(--shadow); 
        position: relative;
        overflow: hidden;
    }

    .card::after {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        border-radius: var(--radius); box-shadow: inset 0 2px 5px rgba(255,255,255,0.2); pointer-events: none;
    }
    
    .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
    .title { font-weight: 700; color: var(--text-main); text-transform: uppercase; font-size: 0.9rem; letter-spacing: 0.5px; }
    .badge { background: var(--accent); color: var(--primary); padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; border: 1px solid var(--border); backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px); }
    
    .row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed var(--border-dashed); }
    .row:last-child { border: none; }
    .lbl { color: var(--text-sub); font-size: 0.9rem; font-weight: 500; }
    .val { font-weight: 600; color: var(--text-main); text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    
    .prog-wrap { margin-bottom: 14px; }
    .prog-info { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 6px; color: var(--text-sub); font-weight: 500; }
    .prog-bg { background: rgba(0,0,0,0.1); border: 1px solid var(--border); height: 8px; border-radius: 4px; overflow: hidden; }
    @media (prefers-color-scheme: dark) { .prog-bg { background: rgba(255,255,255,0.08); } }
    .prog-bar { background: var(--primary); height: 100%; width: 0%; transition: width 0.5s ease; border-radius: 4px; box-shadow: inset 0 1px 2px rgba(255,255,255,0.3); }
    
    .cpu-viz { display: flex; align-items: flex-end; gap: 4px; height: 40px; margin: 12px 0; background: rgba(0,0,0,0.08); padding: 6px; border-radius: 8px; border: 1px solid var(--border); }
    @media (prefers-color-scheme: dark) { .cpu-viz { background: rgba(255,255,255,0.05); } }
    .cpu-stick { flex: 1; background: var(--primary); opacity: 0.4; border-radius: 3px; transition: height 0.3s ease; }
    .cpu-stick.active { opacity: 1; box-shadow: 0 0 5px var(--primary); }
    
    .sig-viz { display: flex; align-items: flex-end; gap: 3px; height: 18px; }
    .s-bar { width: 5px; background: rgba(0,0,0,0.15); border-radius: 2px; transition: 0.3s ease; }
    @media (prefers-color-scheme: dark) { .s-bar { background: rgba(255,255,255,0.15); } }
    .s-bar.active { background: var(--primary); }

    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    th { text-align: left; color: var(--text-sub); padding: 10px 5px; border-bottom: 1px solid var(--border); font-weight: 600; }
    td { padding: 10px 5px; border-bottom: 1px solid var(--border-dashed); color: var(--text-main); }
    .iface { color: var(--primary); font-weight: 700; }

    .cache-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
    .cache-box { background: var(--accent); border: 1px solid var(--border); padding: 10px; border-radius: var(--inner-radius); text-align: center; }
    
    .divider { border-top: 1px dashed var(--border-dashed); margin: 22px 0 16px; padding-top: 12px; display: flex; align-items: center; justify-content: space-between; }
    .div-title { font-weight: 700; color: var(--text-sub); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }

    .sim-header { 
        font-size: 0.9rem; 
        font-weight: 800; 
        color: var(--text-main); 
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px; 
        margin-top: 6px; 
    }
    .sim-tech { font-size: 0.7rem; background: var(--accent); padding: 3px 8px; border-radius: 6px; color: var(--primary); border: 1px solid var(--border); margin-left: 8px;}
    
    .skeleton { color: transparent; background: linear-gradient(90deg, var(--border) 25%, rgba(255,255,255,0) 50%, var(--border) 75%); background-size: 200% 100%; animation: loading 1.5s infinite; border-radius: 4px; display: inline-block; min-width: 50px; }
    @keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  </style>
</head>
<body>

<div class="grid">

    <div class="card">
        <div class="head"><span class="title">System Load</span><span class="badge" id="ramBadge">-</span></div>
        <div class="prog-wrap">
            <div class="prog-info"><span class="lbl">RAM</span><span class="val" id="totalRam"><span class="skeleton">...</span></span></div>
            <div class="prog-bg"><div class="prog-bar" id="memBar"></div></div>
        </div>
        <div class="prog-wrap">
            <div class="prog-info"><span class="lbl">SWAP</span><span class="val" id="totalSwap"><span class="skeleton">...</span></span></div>
            <div class="prog-bg"><div class="prog-bar" id="swapBar" style="opacity:0.7"></div></div>
        </div>
        <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center;">
            <div class="lbl">CPU Activity</div><div class="val" id="cpuLoadVal" style="color:var(--primary); font-size: 1.1rem;">-</div>
        </div>
        <div class="cpu-viz"><?php for($i=0;$i<8;$i++) echo '<div class="cpu-stick" style="height:20%"></div>'; ?></div>
    </div>

    <div class="card">
        <div class="head"><span class="title">Device Info</span><span class="badge" id="uptime">-</span></div>
        <div class="row"><span class="lbl">Model</span><span class="val" id="model"><span class="skeleton">...</span></span></div>
        <div class="row"><span class="lbl">SoC</span><span class="val" id="soc" style="font-size:0.85rem"><span class="skeleton">...</span></span></div>
        <div class="row"><span class="lbl">Battery</span><span class="val" id="battLevel" style="color:var(--primary)">-</span></div>
        <div class="row"><span class="lbl">Status</span><span class="val" id="battStatus">-</span></div>
        <div class="row"><span class="lbl">Voltage / Temp</span><span class="val" id="battVolt">-</span></div>
    </div>

    <div class="card">
        <div class="head"><span class="title">Storage</span></div>
        <div id="storageList"><div style="padding:10px; text-align:center"><span class="skeleton">Loading Storage...</span></div></div>
        
        <div style="margin-top:18px; border-top:1px dashed var(--border-dashed); padding-top:12px;">
            <div class="row" style="border:none; padding:0 0 8px 0;">
                <span class="lbl" style="font-size:0.85rem">Junk Files</span>
                <span class="val" id="totalCache" style="color:var(--danger)">-</span>
            </div>
            <div class="cache-grid" id="cacheDetails"></div>
        </div>
    </div>

    <div class="card" style="grid-column: 1 / -1;">
        <div class="head">
            <span class="title">Connectivity</span>
            <span class="badge" id="simCount">Signal</span> 
        </div>

        <div style="margin-bottom: 25px;" id="sigDetails">
             <div style="text-align:center; color:var(--text-sub); padding:15px;"><span class="skeleton">Scanning Signal...</span></div>
        </div>

        <div class="divider"><span class="div-title">Interfaces</span></div>
        <div style="overflow-x:auto;">
            <table id="netTable">
                <thead><tr><th>Interface</th><th>IP Address</th><th align="right">Down</th><th align="right">Up</th></tr></thead>
                <tbody><tr><td colspan="4" align="center" style="color:var(--text-sub)">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>

</div>

<script>
// ==========================================
// LOGIKA SISTEM DASHBOARD (CLEAN)
// ==========================================
const getW = (v, min, range) => Math.max(5, Math.min(100, (v - min) / range * 100)) + '%';

function updateAll() {
    const getC = (v, type) => {
        const i = parseInt(v);
        if (type === 'rsrp') return i > -95 ? 'var(--success)' : (i > -110 ? 'var(--primary)' : 'var(--danger)');
        if (type === 'sinr') return i > 0 ? 'var(--success)' : (i > -10 ? 'var(--primary)' : 'var(--danger)');
        if (type === 'rsrq') return i > -13 ? 'var(--success)' : (i > -18 ? 'var(--primary)' : 'var(--danger)');
        if (type === 'rssi') return i > -90 ? 'var(--success)' : 'var(--text-sub)';
        return 'var(--primary)';
    };

    fetch('exec/helpers.php').then(r=>r.json()).then(d=>{
        const m = d.used_memory_percent||0;
        document.getElementById('ramBadge').innerText = m+'%';
        document.getElementById('memBar').style.width = m+'%';
        document.getElementById('swapBar').style.width = (d.used_swap_percent||0)+'%';
        document.getElementById('totalRam').innerText = d.free_memory+' Free';
        document.getElementById('totalSwap').innerText = d.total_swap;
        
        const cl = parseFloat(d.active)||0;
        document.getElementById('cpuLoadVal').innerText = cl.toFixed(1)+'%';
        document.querySelectorAll('.cpu-stick').forEach(b => {
            const h = Math.min(100, Math.max(15, cl+(Math.random()*50-25)));
            b.style.height=h+'%';
            b.className='cpu-stick '+(h>70?'active':'');
        });
    }).catch(()=>{});

    fetch('?api=get_all').then(r=>r.json()).then(d=>{
        document.getElementById('uptime').innerText = d.system.uptime;
        document.getElementById('model').innerText = d.system.model;
        document.getElementById('soc').innerText = d.system.soc;
        
        document.getElementById('battLevel').innerText = d.battery.level + '%';
        document.getElementById('battStatus').innerText = d.battery.status;
        document.getElementById('battVolt').innerText = `${d.battery.voltage} / ${d.battery.temp}°C`;

        let sHtml = '';
        d.storage.forEach(p => {
            let c = 'var(--primary)'; if(p.pct>90) c = 'var(--danger)';
            sHtml += `<div class="prog-wrap"><div class="prog-info"><span class="val">${p.name}</span><span style="color:var(--text-sub)">${p.free} Free</span></div><div class="prog-bg"><div class="prog-bar" style="width:${p.pct}%; background:${c}"></div></div></div>`;
        });
        document.getElementById('storageList').innerHTML = sHtml;
        
        document.getElementById('totalCache').innerText = d.cache.total;
        let cHtml = '';
        d.cache.details.forEach(c => {
            cHtml += `<div class="cache-box"><div style="font-size:0.75rem; color:var(--text-sub); margin-bottom:4px;">${c.name}</div><div class="val">${c.size}</div></div>`;
        });
        document.getElementById('cacheDetails').innerHTML = cHtml;

        let nHtml = '';
        if(d.network.length > 0) {
            nHtml += `<thead><tr><th>Interface</th><th>IP Address</th><th align="right">Down</th><th align="right">Up</th></tr></thead><tbody>`;
            d.network.forEach(n => {
                nHtml += `<tr><td class="iface">${n.name}</td><td>${n.ip}</td><td align="right">${n.rx}</td><td align="right">${n.tx}</td></tr>`;
            });
            nHtml += `</tbody>`;
        } else {
            nHtml = `<tbody><tr><td colspan="4" align="center" style="color:var(--text-sub)">No active interfaces</td></tr></tbody>`;
        }
        document.getElementById('netTable').innerHTML = nHtml;

        const signals = Array.isArray(d.signal) ? d.signal : [d.signal];
        document.getElementById('simCount').innerText = signals.length > 1 ? 'Dual SIM' : 'Single SIM';
        
        let sigHtml = '';
        signals.forEach((sig, index) => {
            let vizHtml = `<div class="sig-viz">`;
            for(let i=0; i<4; i++) {
                let activeClass = (sig.type !== 'NO SIGNAL' && i < sig.level) ? 'active' : '';
                let bgStyle = (activeClass === '') ? 'background:rgba(122, 92, 67, 0.2)' : ''; 
                vizHtml += `<div class="s-bar ${activeClass}" style="height:${(i+1)*25}%; ${bgStyle}"></div>`;
            }
            vizHtml += `</div>`;

            sigHtml += `
            <div class="sim-header">
                <div>${sig.provider} <span class="sim-tech">${sig.type}</span></div>
                ${vizHtml}
            </div>`;
            
            if(sig.type !== 'NO SIGNAL' && sig.rsrp !== 'N/A') {
                const rsrp = parseInt(sig.rsrp), sinr = parseInt(sig.sinr), rsrq = parseInt(sig.rsrq), rssi = parseInt(sig.rssi);
                
                const wRSRP = getW(rsrp, -140, 100); 
                const wSINR = getW(sinr, -20, 50);   
                const wRSRQ = getW(rsrq, -20, 17);   
                const wRSSI = getW(rssi, -113, 62);  

                const cRSRP = getC(rsrp, 'rsrp');
                const cSINR = getC(sinr, 'sinr');
                const cRSRQ = getC(rsrq, 'rsrq');
                const cRSSI = getC(rssi, 'rssi');

                sigHtml += `
                    <div class="prog-wrap"><div class="prog-info"><span class="lbl">RSRP</span><span class="val" style="color:${cRSRP}">${sig.rsrp} dBm</span></div><div class="prog-bg"><div class="prog-bar" style="width:${wRSRP}; background:${cRSRP}"></div></div></div>
                    <div class="prog-wrap"><div class="prog-info"><span class="lbl">SINR</span><span class="val" style="color:${cSINR}">${sig.sinr} dB</span></div><div class="prog-bg"><div class="prog-bar" style="width:${wSINR}; background:${cSINR}"></div></div></div>
                    <div class="prog-wrap"><div class="prog-info"><span class="lbl">RSRQ</span><span class="val" style="color:${cRSRQ}">${sig.rsrq} dB</span></div><div class="prog-bg"><div class="prog-bar" style="width:${wRSRQ}; background:${cRSRQ}"></div></div></div>
                    <div class="prog-wrap"><div class="prog-info"><span class="lbl">RSSI</span><span class="val" style="color:${cRSSI}">${sig.rssi} dBm</span></div><div class="prog-bg"><div class="prog-bar" style="width:${wRSSI}; background:${cRSSI}"></div></div></div>
                `;
            } else {
                const gy = 'var(--text-sub)'; const bg = 'rgba(122, 92, 67, 0.2)';
                sigHtml += `
                     <div class="prog-wrap"><div class="prog-info"><span class="lbl">Status</span><span class="val" style="color:${gy}">Inactive / No Signal</span></div><div class="prog-bg"><div class="prog-bar" style="width:0%; background:${bg}"></div></div></div>
                `;
            }
            
            if(index < signals.length - 1) sigHtml += `<div style="margin-bottom:20px; border-bottom:1px dashed var(--border-dashed)"></div>`;
        });
        document.getElementById('sigDetails').innerHTML = sigHtml;

    }).catch(()=>{});
}

updateAll();
setInterval(updateAll, 3000);
</script>

</body>
</html>
