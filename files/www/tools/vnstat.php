<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
date_default_timezone_set('Asia/Jakarta');
$TERMUX_BIN_PATH = "/data/data/com.termux/files/usr/bin/";
$VNSTAT_DB_PATH  = "/data/data/com.termux/files/usr/var/lib/vnstat/vnstat.db";
function parseTrafficToMB($string) {
    $value = floatval($string);
    $unit  = strtoupper(preg_replace('/[^A-Z]/', '', $string));
    $multipliers = ['K' => 1/1024, 'M' => 1, 'G' => 1024, 'T' => 1048576];
    foreach ($multipliers as $key => $multiplier) {
        if (strpos($unit, $key) === 0) return $value * $multiplier;
    }
    return 0;
}

function getInterfaces($binPath) {
    $output = shell_exec($binPath . "ifconfig -a");
    preg_match_all('/(wlan|rmnet_data|ccmni|tun|eth|rndis)\d+/', $output, $matches);
    return array_unique($matches[0]);
}

if (isset($_GET['api']) && $_GET['api'] === 'get_stats') {
    header('Content-Type: application/json');
    $interfaces = getInterfaces($TERMUX_BIN_PATH);
    $dailyStats = [];
    foreach ($interfaces as $iface) {
        $output = shell_exec($TERMUX_BIN_PATH . "vnstat -d -i " . escapeshellarg($iface) . " 2>&1");
        if (!$output) continue;
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})\s+([\d.]+\s+\w+)\s+\|\s+([\d.]+\s+\w+)/', $line, $matches)) {
                $date = $matches[1];
                if (!isset($dailyStats[$date])) $dailyStats[$date] = ['dl' => 0, 'ul' => 0, 't' => 0];
                $dl = parseTrafficToMB($matches[2]);
                $ul = parseTrafficToMB($matches[3]);
                $dailyStats[$date]['dl'] += $dl;
                $dailyStats[$date]['ul'] += $ul;
                $dailyStats[$date]['t']  += ($dl + $ul);
            }
        }
    }
    krsort($dailyStats);
    $monthlyStats = [];
    foreach ($dailyStats as $date => $val) {
        $month = substr($date, 0, 7);
        if (!isset($monthlyStats[$month])) $monthlyStats[$month] = ['dl' => 0, 'ul' => 0, 't' => 0];
        $monthlyStats[$month]['dl'] += $val['dl'];
        $monthlyStats[$month]['ul'] += $val['ul'];
        $monthlyStats[$month]['t']  += $val['t'];
    }
    $chartData = ['l' => [], 'd' => [], 'u' => []];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chartData['l'][] = date('d/m', strtotime($d));
        $chartData['d'][] = isset($dailyStats[$d]) ? round($dailyStats[$d]['dl'] / 1024, 2) : 0;
        $chartData['u'][] = isset($dailyStats[$d]) ? round($dailyStats[$d]['ul'] / 1024, 2) : 0;
    }
    function getGranularStats($flag, $regex, $binPath, $interfaces) {
        $result = [];
        foreach ($interfaces as $iface) {
            $output = shell_exec($binPath . "vnstat $flag -i " . escapeshellarg($iface) . " 2>&1");
            if (!$output) continue;
            foreach (explode("\n", $output) as $line) {
                if (preg_match($regex, $line, $matches)) {
                    $timeKey = $matches[1];
                    if (!isset($result[$timeKey])) $result[$timeKey] = ['dl' => 0, 'ul' => 0];
                    $result[$timeKey]['dl'] += parseTrafficToMB($matches[2]);
                    $result[$timeKey]['ul'] += parseTrafficToMB($matches[3]);
                }
            }
        }
        ksort($result);
        return ['l' => array_keys($result), 'd' => array_column($result, 'dl'), 'u' => array_column($result, 'ul')];
    }
    $regexTime = '/(\d{2}:\d{2})\s+([\d.]+\s+\w+)\s+\|\s+([\d.]+\s+\w+)/';
    $hourlyStats = getGranularStats('-h', $regexTime, $TERMUX_BIN_PATH, $interfaces);
    $realtimeStats = getGranularStats('-5', $regexTime, $TERMUX_BIN_PATH, $interfaces);
    echo json_encode([
        'd' => $dailyStats, 'm' => $monthlyStats, 'c' => $chartData, 'h' => $hourlyStats, '5' => $realtimeStats,
        's' => [
            't'  => $dailyStats[date('Y-m-d')]['t'] ?? 0,
            'y'  => $dailyStats[date('Y-m-d', strtotime('-1 day'))]['t'] ?? 0,
            'tm' => $monthlyStats[date('Y-m')]['t'] ?? 0
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rst'])) { 
        if (file_exists($VNSTAT_DB_PATH)) unlink($VNSTAT_DB_PATH); 
    }
    
    if (isset($_POST['str'])) { 
        shell_exec($TERMUX_BIN_PATH . "vnstatd -d"); 
        sleep(1); 
    }
    
    if (isset($_POST['tgl'])) {
        $configFile = '/data/adb/php8/files/config/onboot.cfg';
        if (!file_exists($configFile)) {
            file_put_contents($configFile, "vnstat=0\n");
        }
        
        $content = file_get_contents($configFile);
        if (strpos($content, 'vnstat=1') !== false) {
            $newContent = str_replace('vnstat=1', 'vnstat=0', $content);
        } elseif (strpos($content, 'vnstat=0') !== false) {
            $newContent = str_replace('vnstat=0', 'vnstat=1', $content);
        } else {
            $newContent = $content . "\nvnstat=1";
        }
        file_put_contents($configFile, $newContent);
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$configContent = file_exists('/data/adb/php8/files/config/onboot.cfg') ? file_get_contents('/data/adb/php8/files/config/onboot.cfg') : '';
$isAutoStartEnabled = (strpos($configContent, 'vnstat=1') !== false);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Network Monitor</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --btn-reset: #5D4037;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
                --btn-reset: #3E2723;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 15px; max-width: 900px; margin: 0 auto; padding-bottom: 80px; -webkit-font-smoothing: antialiased;
        }
        header { text-align: center; margin-bottom: 25px; }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .sub { font-size: 0.8rem; color: var(--text-sub); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .g-stat { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .c-stat { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            padding: 20px 15px; border-radius: 20px; border: 1px solid var(--border); text-align: center; box-shadow: var(--shadow);
        }
        .v-stat { font-size: 1.4rem; font-weight: 800; display: block; margin-bottom: 5px; color: var(--text-main); }
        .l-stat { font-size: 0.7rem; color: var(--text-sub); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .hl .v-stat { color: var(--primary); }
        .c-box { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; padding: 20px; border: 1px solid var(--border); margin-bottom: 20px; box-shadow: var(--shadow);
        }
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; }
        .tab { 
            background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text-sub); 
            padding: 8px 16px; border-radius: 14px; cursor: pointer; font-size: 0.8rem; font-weight: 700; transition: 0.3s;
        }
        .tab.act { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 4px 10px rgba(184, 115, 51, 0.3); }
        .cvs { position: relative; height: 280px; width: 100%; }
        .tbl { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 20px; box-shadow: var(--shadow);
        }
        .thd { padding: 15px 20px; font-weight: 800; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); font-size: 0.9rem; text-transform: uppercase; color: var(--text-main); }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 12px 20px; text-align: left; border-bottom: 1px dashed rgba(122, 92, 67, 0.1); }
        th { color: var(--text-sub); font-weight: 700; font-size: 0.7rem; text-transform: uppercase; }
        .dl { color: var(--primary); font-weight: 700; }
        .ul { color: #32d74b; font-weight: 700; }

        /* --- PERBAIKAN TATA LETAK TOMBOL --- */
        .acts { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .tgl { 
            grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; 
            background: var(--card-bg); backdrop-filter: var(--blur-val); padding: 18px 20px; 
            border-radius: 20px; border: 1px solid var(--border); box-shadow: var(--shadow);
        }
        .tl { font-weight: 800; font-size: 0.9rem; text-transform: uppercase; color: var(--text-main); }
        .sw { position: relative; display: inline-block; width: 50px; height: 28px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.1); transition: .4s; border-radius: 34px; border: 1px solid var(--border); }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: #fff; transition: .4s; border-radius: 50%; }
        input:checked + .sl { background-color: var(--primary); }
        input:checked + .sl:before { transform: translateX(22px); }

        .btn { 
            padding: 16px; border-radius: 16px; border: 1px solid var(--border); font-weight: 800; cursor: pointer; 
            color: #fff; transition: 0.3s; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;
            display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
        }
        .btn-s { background: var(--primary); }
        .btn-r { background: var(--btn-reset); } /* Espresso Color */
        .btn:active { transform: scale(0.96); }

        .sk { background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0) 100%); background-size: 200% 100%; animation: ld 1.5s infinite; color: transparent !important; border-radius: 4px; display: inline-block; }
        @keyframes ld { 0% { background-position: 200% 0 } 100% { background-position: -200% 0 } }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Network Monitor</h1>
            <div class="sub">Traffic Analytics</div>
        </header>

        <div class="g-stat">
            <div class="c-stat hl"><span class="v-stat" id="stat_month"><span class="sk">...</span></span><span class="l-stat">Monthly</span></div>
            <div class="c-stat"><span class="v-stat" id="stat_today"><span class="sk">...</span></span><span class="l-stat">Today</span></div>
            <div class="c-stat"><span class="v-stat" id="stat_yesterday"><span class="sk">...</span></span><span class="l-stat">Yesterday</span></div>
        </div>

        <div class="c-box">
            <div class="tabs">
                <button class="tab act" onclick="changeChart('c', this)">Daily</button>
                <button class="tab" onclick="changeChart('h', this)">Hourly</button>
                <button class="tab" onclick="changeChart('5', this)">Realtime</button>
            </div>
            <div class="cvs"><canvas id="trafficChart"></canvas></div>
        </div>

        <div class="tbl">
            <div class="thd">Daily Logs</div>
            <div style="overflow-x:auto">
                <table id="table_daily">
                    <thead><tr><th>Date</th><th>Down</th><th>Up</th><th>Total</th></tr></thead>
                    <tbody><tr><td colspan="4" align="center">Fetching data...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="tbl">
            <div class="thd">Monthly Logs</div>
            <div style="overflow-x:auto">
                <table id="table_monthly">
                    <thead><tr><th>Month</th><th>Down</th><th>Up</th><th>Total</th></tr></thead>
                    <tbody><tr><td colspan="4" align="center">Fetching data...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="acts">
            <div class="tgl">
                <span class="tl">Auto-Start</span>
                <form method="post" id="form_autostart">
                    <input type="hidden" name="tgl" value="1">
                    <label class="sw">
                        <input type="checkbox" onchange="document.getElementById('form_autostart').submit()" <?php echo $isAutoStartEnabled ? 'checked' : ''; ?>>
                        <span class="sl"></span>
                    </label>
                </form>
            </div>
            <!-- Tombol sekarang sejajar rapi -->
            <form method="post" onsubmit="return confirm('Reset stats database?')" style="width:100%">
                <input type="hidden" name="rst" value="1">
                <button class="btn btn-r">Reset Stats</button>
            </form>
            <form method="post" style="width:100%">
                <input type="hidden" name="str" value="1">
                <button class="btn btn-s">Start Service</button>
            </form>
        </div>
    </div>

    <script>
        let chartData = {}, myChart;
        const ctx = document.getElementById('trafficChart').getContext('2d');
        function formatBytes(m) {
            if (m >= 1048576) return (m / 1048576).toFixed(2) + ' TB';
            if (m >= 1024) return (m / 1024).toFixed(2) + ' GB';
            return m.toFixed(2) + ' MB';
        }
        function initData() {
            fetch('?api=get_stats').then(r => r.json()).then(data => {
                document.getElementById('stat_month').innerText = formatBytes(data.s.tm);
                document.getElementById('stat_today').innerText = formatBytes(data.s.t);
                document.getElementById('stat_yesterday').innerText = formatBytes(data.s.y);
                const renderTable = (id, ds) => {
                    let h = '', c = 0;
                    for (let k in ds) {
                        if (c++ >= 5) break;
                        h += `<tr><td>${k}</td><td class="dl">${formatBytes(ds[k].dl)}</td><td class="ul">${formatBytes(ds[k].ul)}</td><td><b>${formatBytes(ds[k].t)}</b></td></tr>`;
                    }
                    document.getElementById(id).querySelector('tbody').innerHTML = h || '<tr><td colspan="4">No Data</td></tr>';
                };
                renderTable('table_daily', data.d);
                renderTable('table_monthly', data.m);
                chartData = { c: data.c, h: data.h, 5: data['5'] };
                drawChart('c');
            });
        }
        function drawChart(t) {
            if (myChart) myChart.destroy();
            const d = chartData[t];
            if (!d) return;
            const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const txtColor = isDark ? '#C0B2A2' : '#7A5C43';
            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: d.l,
                    datasets: [
                        { label: 'Down', data: d.d, borderColor: '#B87333', backgroundColor: 'rgba(184,115,51,0.2)', fill: true, tension: 0.4, pointRadius: 2 },
                        { label: 'Up', data: d.u, borderColor: '#32d74b', backgroundColor: 'rgba(50,215,75,0.1)', fill: true, tension: 0.4, pointRadius: 2 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: txtColor, font: { weight: 'bold' } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: txtColor } },
                        y: { beginAtZero: true, grid: { color: 'rgba(122,92,67,0.1)' }, ticks: { color: txtColor } }
                    }
                }
            });
        }
        function changeChart(t, el) {
            document.querySelectorAll('.tab').forEach(b => b.classList.remove('act'));
            el.classList.add('act');
            drawChart(t);
        }
        initData();
    </script>
</body>
</html>