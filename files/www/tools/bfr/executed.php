<?php
$commands = [
    'start'   => "/data/adb/box/scripts/box.service start && /data/adb/box/scripts/box.iptables enable",
    'stop'    => "/data/adb/box/scripts/box.iptables disable && /data/adb/box/scripts/box.service stop",
    'restart' => "/data/adb/box/scripts/box.service restart"
];
$clashlogs = "/data/adb/box/run/runs.log";

if (isset($_GET['action']) && isset($_GET['stream'])) {
    $act = $_GET['action'];
    if (array_key_exists($act, $commands)) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        if(function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) ob_end_flush();
        ob_implicit_flush(1);

        $proc = popen($commands[$act] . " 2>&1", 'r');
        echo "data: ⏳ System: Menjalankan perintah " . strtoupper($act) . "...\n\n";
        flush();

        if ($proc) {
            while (!feof($proc)) {
                $line = fgets($proc);
                if ($line) {
                    echo "data: " . trim($line) . "\n\n";
                    flush();
                }
            }
            pclose($proc);
        }
        echo "data: ✅ Proses Selesai.\n\n";
        echo "event: close\ndata: end\n\n";
        flush();
    }
    exit;
}
$host = explode(':', $_SERVER['HTTP_HOST'])[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Box For Root</title>
    <style>
        /* --- TEMA VISIONOS CHOCOLATE GLASSMORPHISM (LOCKED) --- */
        :root {
            --primary: #B87333; 
            --primary-hover: #8B5A2B;
            --accent: rgba(184, 115, 51, 0.15);
            --border-glass: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px);
            
            /* Light Mode */
            --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C;
            --text-sub: #7A5C43;
            --log-bg: rgba(62, 42, 28, 0.4); /* Mocha Glass */
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2);
                --text-main: #FDF5E6;
                --text-sub: #C0B2A2;
                --log-bg: rgba(0, 0, 0, 0.4);
                --border-glass: rgba(255, 255, 255, 0.12);
            }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
            background: transparent !important; 
            color: var(--text-main); 
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
            padding: 15px; 
            gap: 15px; 
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- GLASS BUTTONS --- */
        .dash-btn { 
            display: block; width: 100%; padding: 14px; text-align: center; 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border: 1px solid var(--border-glass); color: var(--text-main); 
            font-weight: 700; border-radius: 16px; text-decoration: none; 
            transition: 0.3s ease; text-transform: uppercase; letter-spacing: 1px; flex-shrink: 0;
            box-shadow: var(--shadow);
        }
        .dash-btn:hover { background: var(--accent); transform: translateY(-2px); }

        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 20px; padding: 15px; box-shadow: var(--shadow); 
            border: 1px solid var(--border-glass); flex-shrink: 0; 
            position: relative;
        }
        .card::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            border-radius: 20px; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none;
        }

        .btn-grp { display: flex; width: 100%; border-radius: 14px; overflow: hidden; border: 1px solid var(--border-glass); }
        .btn { 
            flex: 1; padding: 15px; border: none; color: white; font-weight: 800; 
            cursor: pointer; font-size: 12px; transition: 0.2s; 
            text-transform: uppercase; letter-spacing: 1px;
            backdrop-filter: blur(10px);
        }
        .btn:active { opacity: 0.8; transform: scale(0.95); }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; filter: grayscale(100%); }
        
        .btn-s { background: rgba(52, 199, 89, 0.7); } /* Green Glass */
        .btn-r { background: rgba(184, 115, 51, 0.8); } /* Bronze Glass */
        .btn-x { background: rgba(255, 59, 48, 0.7); } /* Red Glass */

        /* --- LOG CONSOLE --- */
        .log-wrap { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 0; min-height: 0; }
        .log-head { 
            padding: 12px 18px; font-weight: 800; font-size: 12px; 
            text-transform: uppercase; letter-spacing: 1px;
            border-bottom: 1px dashed rgba(122, 92, 67, 0.2); 
            color: var(--text-sub); display: flex; align-items: center; 
            background: transparent; flex-shrink: 0; 
        }
        
        .log-box { 
            flex: 1; overflow-y: auto; background: var(--log-bg); 
            padding: 15px; font-family: 'SF Mono', monospace; font-size: 11px; 
            line-height: 1.6; color: #FDF5E6; white-space: pre-wrap; 
            word-break: break-all; scroll-behavior: smooth; 
        }
        
        .log-line { border-bottom: 1px solid rgba(255,255,255,0.05); padding: 4px 0; } 
        .log-success { color: #00e676; font-weight: bold; border-top: 1px dashed rgba(0,230,118,0.3); margin-top: 8px; padding-top: 8px; }

        .indicator { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.2); display: inline-block; margin-right: 10px; transition: 0.3s; }
        .indicator.active { background: #00e676; box-shadow: 0 0 10px #00e676; animation: pulse 1.5s infinite; }
        
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

        /* Scrollbar Halus Chocolate */
        .log-box::-webkit-scrollbar { width: 4px; }
        .log-box::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
    </style>
</head>
<body>

    <a href="http://<?php echo $host; ?>:9090/ui/?hostname=<?php echo $host; ?>&port=9090" target="_blank" class="dash-btn">Open Dashboard</a>

    <div class="card" style="padding: 0; border: none; background: transparent; box-shadow: none;">
        <div class="btn-grp">
            <button onclick="exec('start')" class="btn btn-s">Start</button>
            <button onclick="exec('restart')" class="btn btn-r">Restart</button>
            <button onclick="exec('stop')" class="btn btn-x">Stop</button>
        </div>
    </div>

    <div class="card log-wrap">
        <div class="log-head">
            <div><span id="st" class="indicator"></span>System Console</div>
        </div>
        <div class="log-box" id="logs"><?php
            if (file_exists($clashlogs)) {
                $lines = @file($clashlogs, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines !== false) {
                    $lines = array_slice($lines, -50); 
                    foreach ($lines as $l) {
                        $clean = preg_replace('/\x1b\[[0-9;]*m/', '', $l);
                        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $clean);
                        if (trim($clean) !== '') {
                            echo '<div class="log-line">' . htmlspecialchars($clean) . '</div>';
                        }
                    }
                }
            } else {
                echo '<div class="log-line" style="text-align:center; opacity:0.5">Ready to execute.</div>';
            }
        ?></div>
    </div>

    <script>
        const box = document.getElementById('logs');
        const st = document.getElementById('st');
        let es = null;
        box.scrollTop = box.scrollHeight;

        function stripAnsi(str) {
            return str.replace(/[\u001b\u009b][[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-ORZcf-nqry=><]/g, '');
        }

        function exec(act) {
            if(es) es.close();
            
            st.classList.add('active');
            box.innerHTML = '<div class="log-line">⏳ Memulai layanan...</div>'; 
            
            const btns = document.querySelectorAll('.btn');
            btns.forEach(b => b.disabled = true);

            es = new EventSource('?action=' + act + '&stream=true');

            es.onmessage = function(e) {
                if(e.data === 'end') {
                    es.close();
                    st.classList.remove('active');
                    btns.forEach(b => b.disabled = false);
                    return;
                }
                
                const cleanText = stripAnsi(e.data);
                const div = document.createElement('div');
                
                if(cleanText.includes('Selesai')) {
                    div.className = 'log-line log-success';
                } else {
                    div.className = 'log-line';
                }
                
                div.innerText = cleanText;
                box.appendChild(div);
                box.scrollTop = box.scrollHeight;
            };

            es.onerror = function() {
                const div = document.createElement('div');
                div.className = 'log-line';
                div.style.color = '#ff3b30';
                div.innerText = "✖ Error: Koneksi sistem terputus.";
                box.appendChild(div);
                
                es.close();
                st.classList.remove('active');
                btns.forEach(b => b.disabled = false);
                box.scrollTop = box.scrollHeight;
            };
        }
    </script>

</body>
</html>