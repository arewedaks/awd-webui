<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
require_once '/data/adb/php8/files/www/utils.php';

if (isset($_GET['api']) && $_GET['api'] === 'get_all') {
    header('Content-Type: application/json');

    function getCmd($c) { return trim(run_root($c)); }
    function fmtB($b) { return format_bytes($b); }

    $data = [];

    // Mengambil data utama
    $lines = explode("\n", getCmd("cat /proc/uptime; getprop ro.product.model; getprop ro.build.version.release; getprop gsm.sim.operator.alpha; getprop ro.soc.model"));
    $up = (int)floatval(explode(' ', $lines[0]??'0')[0]);
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
    
    $rawNames = $lines[3] ?? '';
    $simNames = [];
    if (!empty($rawNames)) {
        $simNames = explode(',', $rawNames);
    }

    $fastJsonFile = '/data/adb/php8/files/tmp/awd_signal.json';
    if (!file_exists($fastJsonFile)) {
        $fastJsonFile = '/data/system/awd_signal.json';
    }

    $sim_blocks = [1 => []];
    $hasRealtime = false;
    
    if (file_exists($fastJsonFile)) {
        $fastJson = json_decode(file_get_contents($fastJsonFile), true);
        if (is_array($fastJson) && !empty($fastJson)) {
            foreach ($fastJson as $phoneId => $strData) {
                // strData berbentuk "SignalStrength:{...}"
                if (preg_match('/SignalStrength:\{(.+)\}$/s', trim($strData), $m)) {
                    $sim_blocks[1][] = $m[1];
                }
            }
            if (!empty($sim_blocks[1])) {
                $hasRealtime = true;
            }
        }
    }

    // 2. Jika tidak ada file Real-Time, Fallback ke dumpsys lambat
    if (!$hasRealtime) {
        $dump = getCmd('dumpsys telephony.registry');
        if (!empty($dump)) {
            preg_match_all('/mSignalStrength=SignalStrength:\{(.+)\}$/m', $dump, $sim_blocks);
        }
    }
    
    if (!empty($sim_blocks[1])) {
        $count = 0; 
        foreach($sim_blocks[1] as $block) {
            if ($count >= 2) break;
            
            // Cari signal mana yang sedang primary/aktif (contoh: primary=CellSignalStrengthLte)
            if (preg_match('/primary=(CellSignalStrength[A-Za-z]+)/', $block, $prim_match)) {
                $primary_type = $prim_match[1];
            } else {
                // Fallback jika tidak ada tulisan primary (ROM jadul)
                preg_match('/(CellSignalStrength[A-Za-z]+):/', $block, $prim_match);
                $primary_type = $prim_match[1] ?? 'CellSignalStrengthLte';
            }

            // Ambil nama tipe jaringan (LTE, GSM, WCDMA, dll)
            $type = strtoupper(str_replace('CellSignalStrength', '', $primary_type));
            
            // Ekstrak blok parameter untuk tipe yang aktif saja
            if (preg_match('/' . $primary_type . ':\s*(.+?)(?=(,[a-zA-Z0-9]+=CellSignalStrength|}$|,primary=))/s', $block, $ld_match)) {
                $ld = $ld_match[1];
                
                preg_match('/rssi=([-\d]+)/',$ld,$rssi); 
                preg_match('/rsrp=([-\d]+)/',$ld,$rsrp);
                preg_match('/rsrq=([-\d]+)/',$ld,$rsrq); 
                preg_match('/rssnr=([-\d]+)/',$ld,$sinr);
                preg_match('/(?:mLevel|level)=(\d)/i',$ld,$lvl);

                $i_lvl = (int)($lvl[1]??0);
                
                // Jika nilai tidak masuk akal (2147483647), berarti SIM tidak terdeteksi/kosong
                $is_empty = ($i_lvl === 0 && (!isset($rsrp[1]) || $rsrp[1] == '2147483647') && (!isset($rssi[1]) || $rssi[1] == '2147483647'));
                if ($is_empty) {
                    $count++;
                    continue; // Skip agar tidak muncul SIM Hantu
                }

                $v_rsrp = (isset($rsrp[1]) && abs($rsrp[1]) < 200) ? $rsrp[1] : 'N/A';
                $v_rssi = (isset($rssi[1]) && abs($rssi[1]) < 200) ? $rssi[1] : 'N/A';
                $v_sinr = (isset($sinr[1]) && abs($sinr[1]) < 200) ? $sinr[1] : 'N/A';
                $v_rsrq = (isset($rsrq[1]) && abs($rsrq[1]) < 200) ? $rsrq[1] : 'N/A';

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
                    'sinr' => $v_sinr, 
                    'rsrq' => $v_rsrq
                ];
            }
            $count++; 
        }
    }

    // Blok fallback empty signals telah dihapus sesuai permintaan agar UI bersih saat Airplane mode
    
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
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    /* Menggunakan variabel dari style.css dan menambahkan kustom untuk animasi/layout tingkat lanjut */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body { 
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
        background: transparent; /* TRANSPARAN TOTAL */
        color: var(--text-main); 
        padding: 20px; 
        font-size: 14px; 
        -webkit-font-smoothing: antialiased;
        position: relative; 
        max-width: 100%;
    }
    
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; max-width: 1200px; margin: 0 auto; position: relative; z-index: 1; }
    
    /* STYLE CARD GLASSMORPHISM ENHANCED */
    .card { 
        background: var(--card-bg); 
        backdrop-filter: var(--blur-val); 
        -webkit-backdrop-filter: var(--blur-val);
        border: 1px solid var(--border); 
        border-radius: 24px; 
        padding: 24px; 
        margin: 0; /* Reset global margin */
        box-shadow: var(--shadow); 
        position: relative;
        overflow: hidden;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease, border-color 0.4s ease;
    }

    .card:hover {
        transform: translateY(-6px);
        box-shadow: 0 15px 35px rgba(62, 42, 28, 0.15);
        border-color: var(--primary);
    }
    @media (prefers-color-scheme: dark) {
        .card:hover { box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6); }
    }

    .card::after {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        border-radius: 24px; box-shadow: inset 0 2px 5px rgba(255,255,255,0.2); pointer-events: none;
    }
    
    .head { display: flex; flex-direction: row; justify-content: space-between; align-items: center; margin: 0 0 20px 0; padding: 0 0 12px 0; border-bottom: 1px dashed var(--border-dashed); }
    .title { font-weight: 900; color: var(--primary); text-transform: uppercase; font-size: 1.05rem; letter-spacing: 1px; display: flex; align-items: center; margin: 0; padding: 0; }
    .title::before { content: ''; display: inline-block; width: 6px; height: 18px; background: var(--primary); border-radius: 4px; margin-right: 10px; box-shadow: 0 0 5px var(--accent); }
    .badge { background: var(--accent); color: var(--primary); padding: 5px 12px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; border: 1px solid var(--border); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val); box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: 0.3s; }
    .card:hover .badge { transform: scale(1.05); }

    .row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--border-dashed); transition: 0.3s ease; }
    .row:hover { background: rgba(0,0,0,0.02); padding-left: 6px; padding-right: 6px; border-radius: 8px; }
    @media (prefers-color-scheme: dark) { .row:hover { background: rgba(255,255,255,0.02); } }
    .row:last-child { border: none; }
    .lbl { color: var(--text-sub); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .val { font-weight: 800; color: var(--text-main); font-family: "SF Mono", monospace; font-size: 0.95rem; text-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    
    .prog-wrap { margin-bottom: 18px; }
    .prog-info { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 8px; color: var(--text-sub); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
    .prog-bg { background: rgba(0,0,0,0.08); border: 1px solid var(--border); height: 10px; border-radius: 6px; overflow: hidden; position: relative; }
    @media (prefers-color-scheme: dark) { .prog-bg { background: rgba(255,255,255,0.08); } }
    .prog-bar { 
        background: linear-gradient(90deg, var(--primary) 0%, rgba(255,255,255,0.4) 50%, var(--primary) 100%); 
        background-size: 200% 100%;
        animation: shimmer 2s infinite linear;
        height: 100%; width: 0%; transition: width 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275); border-radius: 6px; box-shadow: inset 0 1px 2px rgba(255,255,255,0.3); 
    }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    
    .cpu-viz { display: flex; align-items: flex-end; gap: 5px; height: 50px; margin: 15px 0 5px; background: rgba(0,0,0,0.04); padding: 8px; border-radius: 12px; border: 1px solid var(--border); box-shadow: inset 0 2px 5px rgba(0,0,0,0.05); }
    @media (prefers-color-scheme: dark) { .cpu-viz { background: rgba(255,255,255,0.03); } }
    .cpu-stick { flex: 1; background: var(--primary); opacity: 0.3; border-radius: 4px; transition: height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s; }
    .cpu-stick.active { opacity: 1; box-shadow: 0 0 8px var(--primary); }
    
    .sig-viz { display: flex; align-items: flex-end; gap: 4px; height: 20px; }
    .s-bar { width: 6px; background: rgba(0,0,0,0.1); border-radius: 3px; transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    @media (prefers-color-scheme: dark) { .s-bar { background: rgba(255,255,255,0.1); } }
    .s-bar.active { background: var(--success); box-shadow: 0 0 6px var(--success); }

    /* TABLE REDESIGN */
    table { width: 100%; border-collapse: separate; border-spacing: 0 8px; font-size: 0.85rem; margin-top: -8px; }
    th { text-align: left; color: var(--text-sub); padding: 5px 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.75rem; border: none; }
    td { padding: 14px; background: rgba(0,0,0,0.02); color: var(--text-main); border: none; transition: 0.3s; position: relative; }
    @media (prefers-color-scheme: dark) { td { background: rgba(255,255,255,0.03); } }
    tr:hover td { background: rgba(0,0,0,0.05); }
    @media (prefers-color-scheme: dark) { tr:hover td { background: rgba(255,255,255,0.06); } }
    
    td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; border-left: 2px solid transparent; }
    td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; border-right: 1px solid transparent; }
    tr:hover td:first-child { border-left-color: var(--primary); }

    .iface { color: var(--primary); font-weight: 800; font-family: "SF Mono", monospace; }

    .cache-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
    .cache-box { background: var(--accent); border: 1px solid var(--border); padding: 16px; border-radius: 16px; text-align: center; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .cache-box:hover { transform: translateY(-3px); border-color: var(--primary); box-shadow: 0 8px 15px rgba(184,115,51,0.15); }
    .cache-box .cache-name { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-sub); display: block; margin-bottom: 5px;}
    .cache-box .cache-val { font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: "SF Mono", monospace; }
    
    .divider { border-top: 1px dashed var(--border-dashed); margin: 25px 0 15px; padding-top: 15px; display: flex; align-items: center; justify-content: space-between; }
    .div-title { font-weight: 800; color: var(--primary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }

    .sim-header { 
        font-size: 0.95rem; 
        font-weight: 800; 
        color: var(--text-main); 
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px; 
        margin-top: 5px; 
    }
    .sim-tech { font-size: 0.7rem; font-weight: 800; background: var(--accent); padding: 4px 10px; border-radius: 8px; color: var(--primary); border: 1px solid var(--border); margin-left: 10px; letter-spacing: 0.5px;}
    
    .skeleton { color: transparent; background: linear-gradient(90deg, var(--border) 25%, rgba(255,255,255,0) 50%, var(--border) 75%); background-size: 200% 100%; animation: loading 1.5s infinite; border-radius: 6px; display: inline-block; min-width: 60px; }
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

    fetch('exec/helpers.php?_t=' + Date.now(), {cache: "no-store"}).then(r=>r.json()).then(d=>{
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

    fetch('?api=get_all&_t=' + Date.now(), {cache: "no-store"}).then(r=>r.json()).then(d=>{
        document.getElementById('uptime').innerText = d.system.uptime;
        document.getElementById('model').innerText = d.system.model;
        document.getElementById('soc').innerText = d.system.soc;
        
        document.getElementById('battLevel').innerText = d.battery.level + '%';
        document.getElementById('battStatus').innerText = d.battery.status;
        document.getElementById('battVolt').innerHTML = `${d.battery.voltage} / ${parseInt(d.battery.temp)}&deg;C`;

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

        const signals = Array.isArray(d.signal) ? d.signal : (d.signal ? [d.signal] : []);
        document.getElementById('simCount').innerText = signals.length > 1 ? 'Dual SIM' : (signals.length === 0 ? 'Offline' : 'Single SIM');
        
        let sigHtml = '';
        if (signals.length === 0) {
            sigHtml = `<div style="text-align:center; color:var(--danger); padding:15px; font-weight:bold;">Airplane Mode / No Signal</div>`;
        } else {
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
            
            if(sig.type !== 'NO SIGNAL' && (sig.rsrp !== 'N/A' || sig.rssi !== 'N/A' || sig.sinr !== 'N/A' || sig.rsrq !== 'N/A')) {
                const rsrp = parseInt(sig.rsrp), sinr = parseInt(sig.sinr), rsrq = parseInt(sig.rsrq), rssi = parseInt(sig.rssi);
                
                if (sig.rsrp !== 'N/A') {
                    const wRSRP = getW(rsrp, -140, 100); 
                    const cRSRP = getC(rsrp, 'rsrp');
                    sigHtml += `<div class="prog-wrap"><div class="prog-info"><span class="lbl">RSRP</span><span class="val" style="color:${cRSRP}">${sig.rsrp} dBm</span></div><div class="prog-bg"><div class="prog-bar" style="width:${wRSRP}; background:${cRSRP}"></div></div></div>`;
                }
                
                if (sig.sinr !== 'N/A') {
                    const wSINR = getW(sinr, -20, 50);   
                    const cSINR = getC(sinr, 'sinr');
                    sigHtml += `<div class="prog-wrap"><div class="prog-info"><span class="lbl">SINR</span><span class="val" style="color:${cSINR}">${sig.sinr} dB</span></div><div class="prog-bg"><div class="prog-bar" style="width:${wSINR}; background:${cSINR}"></div></div></div>`;
                }

                if (sig.rsrq !== 'N/A') {
                    const wRSRQ = getW(rsrq, -20, 17);   
                    const cRSRQ = getC(rsrq, 'rsrq');
                    sigHtml += `<div class="prog-wrap"><div class="prog-info"><span class="lbl">RSRQ</span><span class="val" style="color:${cRSRQ}">${sig.rsrq} dB</span></div><div class="prog-bg"><div class="prog-bar" style="width:${wRSRQ}; background:${cRSRQ}"></div></div></div>`;
                }

                if (sig.rssi !== 'N/A') {
                    const wRSSI = getW(rssi, -113, 62);  
                    const cRSSI = getC(rssi, 'rssi');
                    sigHtml += `<div class="prog-wrap"><div class="prog-info"><span class="lbl">RSSI</span><span class="val" style="color:${cRSSI}">${sig.rssi} dBm</span></div><div class="prog-bg"><div class="prog-bar" style="width:${wRSSI}; background:${cRSSI}"></div></div></div>`;
                }
            } else {
                const gy = 'var(--text-sub)'; const bg = 'rgba(122, 92, 67, 0.2)';
                sigHtml += `
                     <div class="prog-wrap"><div class="prog-info"><span class="lbl">Status</span><span class="val" style="color:${gy}">Inactive / No Signal</span></div><div class="prog-bg"><div class="prog-bar" style="width:0%; background:${bg}"></div></div></div>
                `;
            }
            
            if(index < signals.length - 1) sigHtml += `<div style="margin-bottom:20px; border-bottom:1px dashed var(--border-dashed)"></div>`;
        });
        }
        document.getElementById('sigDetails').innerHTML = sigHtml;

    }).catch(()=>{});
}

updateAll();
setInterval(updateAll, 3000);
</script>

<script src="/assets/js/main.js"></script>
</body>
</html>
