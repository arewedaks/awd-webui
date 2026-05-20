<?php
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $act = $_GET['action'];
    function fmt($bytes) {
        $bytes = (float)$bytes;
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes/pow(1024, $i), 2).' '.$units[$i];
    }
    function get_df($path) {
        $raw = shell_exec("df $path 2>/dev/null | tail -n1");
        $s = preg_split('/\s+/', trim($raw));
        if (count($s) >= 6) {
            $total = (float)$s[1] * 1024;
            $used  = (float)$s[2] * 1024;
            $free  = (float)$s[3] * 1024;
            $pct   = (int)str_replace('%', '', $s[4]);
            return [
                'path' => $path, 'total_raw' => $total, 'used_raw' => $used,
                'total' => fmt($total), 'used' => fmt($used), 'free' => fmt($free), 'pct' => $pct
            ];
        }
        return null;
    }
    if ($act === 'get_data') {
        $data = [];
        $data['main'] = get_df('/data'); 
        $parts = [];
        $list = ['System' => '/system', 'Internal' => '/sdcard', 'Root' => '/'];
        foreach($list as $name => $path) {
            $info = get_df($path);
            if($info) { $info['label'] = $name; $parts[] = $info; }
        }
        $data['partitions'] = $parts;
        $cachePaths = ['Dalvik' => '/data/dalvik-cache', 'App Cache' => '/data/data/*/cache', 'Temp' => '/data/local/tmp'];
        $totalCache = 0;
        $cacheDetails = [];
        foreach ($cachePaths as $name => $path) {
            $sizeKB = (int)shell_exec("timeout 1s du -sk $path 2>/dev/null | awk '{print $1}'");
            $bytes = $sizeKB * 1024;
            $cacheDetails[] = ['name' => $name, 'size' => fmt($bytes)];
            $totalCache += $bytes;
        }
        $data['cache'] = ['total_fmt' => fmt($totalCache), 'total_raw' => $totalCache, 'details' => $cacheDetails];
        echo json_encode($data);
    } elseif ($act === 'clean_cache') {
        shell_exec("su -c 'rm -rf /data/dalvik-cache/* /data/data/*/cache/* /data/local/tmp/*' 2>&1");
        echo json_encode(['status' => 'success']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Storage Monitor</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --dang: #ff3b30; --warn: #ff9f0a;
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
        .gauge-val { fill: none; stroke: var(--primary); stroke-width: 10; stroke-dasharray: 440; stroke-dashoffset: 440; transition: 1.5s cubic-bezier(0.4, 0, 0.2, 1); stroke-linecap: round; }
        .gauge-text { position: absolute; text-align: center; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .gt-val { font-size: 2.2rem; font-weight: 800; color: var(--text-main); display: block; line-height: 1; }
        .gt-lbl { font-size: 0.7rem; color: var(--text-sub); font-weight: 800; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px; }
        .main-stats { display: flex; justify-content: space-between; width: 100%; margin-top: 25px; text-align: center; }
        .ms-item { flex: 1; border-right: 1px dashed rgba(122, 92, 67, 0.2); }
        .ms-item:last-child { border: none; }
        .ms-val { font-weight: 800; color: var(--text-main); display: block; font-family: 'SF Mono', monospace; font-size: 1rem; }
        .ms-lbl { font-size: 0.65rem; color: var(--text-sub); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
        .sec-title { font-size: 1rem; font-weight: 800; margin-bottom: 20px; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; display: flex; align-items: center; gap: 8px; }
        .part-item { padding-bottom: 18px; border-bottom: 1px dashed rgba(122, 92, 67, 0.15); margin-bottom: 18px; }
        .part-item:last-child { border: none; padding-bottom: 0; margin-bottom: 0; }
        .part-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .part-name { font-weight: 800; font-size: 0.95rem; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        .part-path { font-size: 0.7rem; color: var(--text-sub); background: rgba(0,0,0,0.05); padding: 2px 8px; border-radius: 6px; font-family: 'SF Mono', monospace; }
        .part-pct { font-weight: 800; font-size: 0.9rem; color: var(--primary); }
        .progress-bg { height: 10px; background: rgba(0,0,0,0.1); border-radius: 5px; overflow: hidden; margin-bottom: 8px; border: 1px solid var(--border); }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 1s ease; border-radius: 5px; }
        .part-meta { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-sub); font-weight: 600; }
        .part-meta strong { color: var(--text-main); font-family: 'SF Mono', monospace; }
        .junk-card { background: rgba(30, 18, 10, 0.4); border: 1px solid var(--border); border-radius: 20px; padding: 25px; text-align: center; margin-bottom: 20px; }
        .junk-total { font-size: 2.5rem; font-weight: 800; color: #FDF5E6; line-height: 1; margin: 15px 0; font-family: 'SF Mono', monospace; text-shadow: 0 0 20px rgba(184, 115, 51, 0.4); }
        .junk-lbl { font-size: 0.75rem; text-transform: uppercase; color: var(--text-sub); font-weight: 800; letter-spacing: 1px; }
        .cache-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 10px; }
        .cache-box { background: rgba(255,255,255,0.05); padding: 12px; border-radius: 14px; text-align: center; border: 1px solid var(--border); }
        .cb-val { font-weight: 800; color: #FDF5E6; font-size: 0.85rem; display: block; font-family: 'SF Mono', monospace; }
        .cb-lbl { font-size: 0.65rem; color: var(--text-sub); font-weight: 700; text-transform: uppercase; }
        .btn-clean { background: var(--primary); color: white; border: none; width: 100%; padding: 16px; border-radius: 16px; font-weight: 800; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.3); display: flex; align-items: center; justify-content: center; gap: 12px; text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem; }
        .btn-clean:active { transform: scale(0.97); }
        @media (max-width: 768px) { .dashboard { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            Storage <span class="badge">Engine</span>
        </h1>
        <span class="last-up" id="time">Check...</span>
    </div>
    <div class="dashboard">
        <div class="card">
            <div class="sec-title">Primary Drive</div>
            <div class="gauge-wrapper">
                <div style="position:relative; width:160px; height:160px;">
                    <svg class="gauge-svg" viewBox="0 0 160 160">
                        <circle class="gauge-bg" cx="80" cy="80" r="70"></circle>
                        <circle class="gauge-val" cx="80" cy="80" r="70" id="bar"></circle>
                    </svg>
                    <div class="gauge-text">
                        <span class="gt-val" id="main-pct">0%</span>
                        <span class="gt-lbl">Occupied</span>
                    </div>
                </div>
            </div>
            <div class="main-stats">
                <div class="ms-item"><span class="ms-val" id="main-used">-</span><span class="ms-lbl">Used</span></div>
                <div class="ms-item"><span class="ms-val" id="main-free">-</span><span class="ms-lbl">Free</span></div>
                <div class="ms-item"><span class="ms-val" id="main-total">-</span><span class="ms-lbl">Total</span></div>
            </div>
        </div>
        <div class="card">
            <div class="sec-title">Partition Mapping</div>
            <div class="part-list" id="part-list"></div>
        </div>
        <div class="card" style="grid-column: 1 / -1;">
            <div class="sec-title" style="color:var(--primary)">System Maintenance</div>
            <div style="display:flex; flex-wrap:wrap; gap:30px; align-items:center;">
                <div style="flex:1; min-width:280px;">
                    <div class="junk-card">
                        <span class="junk-lbl">Analyzed Junk Volume</span>
                        <div class="junk-total" id="junk-total">0 B</div>
                        <div class="cache-grid" id="cache-grid"></div>
                    </div>
                </div>
                <div style="flex:1; min-width:280px;">
                    <p style="color:var(--text-sub); font-size:0.85rem; margin-bottom:20px; line-height:1.6; font-weight:600;">
                        Membersihkan cache dalvik dan aplikasi membantu memulihkan ruang penyimpanan sistem yang berharga tanpa menghapus data personal Anda.
                    </p>
                    <button class="btn-clean" onclick="cleanCache()">Clean All Junk Files</button>
                </div>
            </div>
        </div>
    </div>
<script>
function updateStats() {
    fetch('?action=get_data').then(r => r.json()).then(d => {
        if(d.main) {
            const p = d.main.pct;
            const offset = 440 - (440 * p / 100);
            const bar = document.getElementById('bar');
            bar.style.strokeDashoffset = offset;
            document.getElementById('main-pct').innerText = p + '%';
            bar.style.stroke = (p > 90) ? 'var(--dang)' : (p > 75) ? 'var(--warn)' : 'var(--primary)';
            document.getElementById('main-used').innerText = d.main.used;
            document.getElementById('main-free').innerText = d.main.free;
            document.getElementById('main-total').innerText = d.main.total;
        }
        let ph = '';
        d.partitions.forEach(pt => {
            let col = (pt.pct > 90) ? 'var(--dang)' : (pt.pct > 75) ? 'var(--warn)' : 'var(--primary)';
            ph += `<div class="part-item">
                <div class="part-head"><span class="part-name">${pt.label} <span class="part-path">${pt.path}</span></span><span class="part-pct" style="color:${col}">${pt.pct}%</span></div>
                <div class="progress-bg"><div class="progress-fill" style="width:${pt.pct}%; background:${col}"></div></div>
                <div class="part-meta"><span>Used: <strong>${pt.used}</strong></span><span>Free: <strong>${pt.free}</strong></span></div>
            </div>`;
        });
        document.getElementById('part-list').innerHTML = ph;
        document.getElementById('junk-total').innerText = d.cache.total_fmt;
        let ch = '';
        d.cache.details.forEach(c => { ch += `<div class="cache-box"><span class="cb-val">${c.size}</span><span class="cb-lbl">${c.name}</span></div>`; });
        document.getElementById('cache-grid').innerHTML = ch;
        document.getElementById('time').innerText = "LIVE: " + new Date().toLocaleTimeString();
    }).catch(e => console.log(e));
}
function cleanCache() {
    if(confirm("Hapus Cache Sistem dan Aplikasi?")) {
        fetch('?action=clean_cache').then(r => r.json()).then(d => { if(d.status === 'success') { alert('Done!'); updateStats(); } });
    }
}
setInterval(updateStats, 10000);
updateStats();
</script>
</body>
</html>