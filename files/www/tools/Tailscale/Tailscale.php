<?php
// --- 1. CONFIGURATION ---
error_reporting(0); 
$message = "";
$authUrl = ""; 

require_once '/data/adb/php8/files/www/auth/auth_functions.php'; 

define('TS_CMD', '/system/bin/tailscale');

// --- 2. LOGIKA PROSES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. AJAX STATUS CHECKER
    if (isset($_POST['action']) && $_POST['action'] === 'get_status') {
        ob_clean();
        header('Content-Type: application/json');

        // Ambil Data
        $status_raw = shell_exec("su -c \"" . TS_CMD . " status 2>&1\"");
        $real_ip = trim(shell_exec("su -c \"" . TS_CMD . " ip -4 2>/dev/null\""));
        
        // Analisa Status
        $is_stopped = (strpos($status_raw, 'stopped') !== false) || (strpos($status_raw, 'Tailscale is stopped') !== false);
        $needs_login = strpos($status_raw, 'Log in at') !== false || strpos($status_raw, 'Logged out') !== false || strpos($status_raw, 'NeedsLogin') !== false;

        // Default Response
        $response = [
            'is_connected' => false,
            'is_stopped' => $is_stopped,
            'needs_login' => $needs_login,
            'ip_display' => '...',
            'desc' => '...',
            'css_class' => 'inactive',
            'pill_text' => 'Unknown',
            'pill_class' => 'st-off',
            'icon' => '🚫',
            'log_data' => $status_raw 
        ];

        // Logika Status UI
        if ($is_stopped) {
            $response['ip_display'] = "Offline";
            $response['desc'] = "Engine is currently down";
            $response['css_class'] = "inactive";
            $response['pill_text'] = "Stopped";
            $response['pill_class'] = "st-off";
            $response['icon'] = "🚫";
        } elseif (!empty($real_ip)) {
            $response['is_connected'] = true;
            $response['ip_display'] = $real_ip;
            $response['desc'] = "Tailscale IPv4 Address";
            $response['css_class'] = "active";
            $response['pill_text'] = "Online";
            $response['pill_class'] = "st-on";
            $response['icon'] = "🌐";
        } elseif ($needs_login) {
            $response['desc'] = "Login needed to connect";
            $response['ip_display'] = "Auth Required";
            $response['css_class'] = "warning";
            $response['pill_text'] = "Auth Needed";
            $response['pill_class'] = "st-warn";
            $response['icon'] = "🔑";
        } else {
            $response['desc'] = "Service Starting...";
            $response['ip_display'] = "...";
            $response['pill_text'] = "Starting";
        }

        echo json_encode($response);
        exit;
    }

    // B. TOGGLE STATE
    if (isset($_POST['action']) && $_POST['action'] === 'set_state') {
        ob_clean(); 
        header('Content-Type: application/json');
        $targetState = $_POST['state']; 
        if ($targetState === 'up') {
            shell_exec("su -c \"nohup " . TS_CMD . " up > /dev/null 2>&1 &\"");
            echo json_encode(['status' => 'success', 'message' => 'Starting Tailscale...']);
        } else {
            shell_exec("su -c \"" . TS_CMD . " down > /dev/null 2>&1\"");
            shell_exec("su -c \"rm -f /data/adb/tailscale/tailscaled.state 2>/dev/null\""); 
            echo json_encode(['status' => 'success', 'message' => 'Service Stopped.']);
        }
        exit;
    }

    // C. LOGOUT
    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        shell_exec("su -c \"" . TS_CMD . " logout\"");
        $message = "Device logged out.";
    }

    // D. GET AUTH URL
    if (isset($_POST['action']) && $_POST['action'] === 'get_auth') {
        $cmd = "su -c \"timeout 5 " . TS_CMD . " login 2>&1\"";
        $output = shell_exec($cmd);
        if ($output && preg_match('/https:\/\/login\.tailscale\.com\/a\/[a-zA-Z0-9]+/', $output, $matches)) {
            $authUrl = $matches[0];
            $message = "Auth Link Generated!";
        } else {
            $message = "Service Stopped or Already Connected.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tailscale Dashboard</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root {
            --primary-h: #8B5A2B;
            --dang: #ff3b30; --dang-soft: rgba(255, 59, 48, 0.15); 
            --succ: #34c759; --succ-soft: rgba(52, 199, 89, 0.15);
            --warn: #ff9f0a; --warn-soft: rgba(255, 159, 10, 0.15);
            --term-bg: rgba(30, 18, 10, 0.4); --term-txt: #FDF5E6;
            --rad: 24px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --term-bg: rgba(0, 0, 0, 0.4);
            }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
            background: transparent !important; 
            color: var(--text-main); 
            padding: 20px; 
            max-width: 500px; 
            margin: 0 auto; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            -webkit-font-smoothing: antialiased;
        }
        
        .head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
        .logo { font-size: 1.1rem; font-weight: 800; color: var(--text-main); letter-spacing: 0.5px; text-transform: uppercase; }
        .logo span { color: var(--primary); }

        .status-pill { font-size: 0.7rem; font-weight: 800; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; border: 1px solid var(--border); backdrop-filter: var(--blur-val); }
        .st-on { background: var(--succ-soft); color: var(--succ); }
        .st-off { background: var(--dang-soft); color: var(--dang); }
        .st-warn { background: var(--warn-soft); color: var(--warn); }

        /* --- HERO CARD --- */
        .hero { 
            background: var(--card-bg); 
            backdrop-filter: var(--blur-val); 
            -webkit-backdrop-filter: var(--blur-val);
            border-radius: var(--rad); 
            padding: 35px 20px; 
            text-align: center; 
            box-shadow: var(--shadow); 
            border: 1px solid var(--border);
            position: relative;
        }
        .hero::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            border-radius: var(--rad); box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none;
        }

        .pulse-ring { width: 90px; height: 90px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; position: relative; }
        .pulse-ring.active { background: var(--succ-soft); }
        .pulse-ring.inactive { background: var(--dang-soft); }
        .pulse-ring.warning { background: var(--warn-soft); }
        .icon { font-size: 2.2rem; z-index: 2; }

        .pulse-ring.active::after { content: ''; position: absolute; width: 100%; height: 100%; border-radius: 50%; background: var(--succ); opacity: 0.4; animation: pulse 2s infinite; }
        .pulse-ring.warning::after { content: ''; position: absolute; width: 100%; height: 100%; border-radius: 50%; background: var(--warn); opacity: 0.4; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.7; } 70% { transform: scale(1.5); opacity: 0; } 100% { transform: scale(0.95); opacity: 0; } }

        .ip-display { font-family: 'SF Mono', monospace; font-size: 1.5rem; font-weight: 800; margin: 10px 0 5px; color: var(--text-main); }
        .lbl { font-size: 0.85rem; color: var(--text-sub); font-weight: 600; letter-spacing: 0.3px; }

        /* --- CONTROLS --- */
        .ctrl-area { margin-top: 25px; }
        .btn-main { 
            width: 100%; border: 1px solid var(--border); padding: 18px; 
            border-radius: 16px; font-weight: 800; font-size: 0.95rem; 
            cursor: pointer; transition: 0.3s ease; text-transform: uppercase; 
            display: flex; align-items: center; justify-content: center; gap: 12px;
            letter-spacing: 1px; backdrop-filter: var(--blur-val);
        }
        .btn-main:active { transform: scale(0.96); }
        .btn-connect { background: var(--primary); color: #fff; box-shadow: 0 5px 15px rgba(184, 115, 51, 0.25); }
        .btn-stop { background: var(--dang-soft); color: var(--dang); border-color: rgba(255, 59, 48, 0.3); }

        .auth-box { 
            background: var(--card-bg); 
            margin-top: 25px; 
            border-radius: var(--rad); 
            padding: 24px; 
            border: 1px solid var(--border); 
            box-shadow: var(--shadow); 
            backdrop-filter: var(--blur-val);
        }
        .auth-head { font-size: 0.9rem; font-weight: 800; color: var(--text-main); margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .term { 
            background: var(--term-bg); padding: 16px; border-radius: 12px; 
            color: var(--term-txt); font-family: 'SF Mono', monospace; 
            font-size: 0.8rem; word-break: break-all; 
            border: 1px solid var(--border);
            cursor: copy;
        }
        .term-btn { 
            margin-top: 15px; width: 100%; background: var(--accent); 
            border: 1px dashed var(--primary); color: var(--primary); 
            padding: 12px; border-radius: 12px; font-weight: 700; cursor: pointer; 
        }

        .btn-logout { width: 100%; margin-top: 25px; background: transparent; color: var(--dang); border: none; font-size: 0.8rem; font-weight: 700; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }

        /* --- LOGS --- */
        .log-container { margin-top: 25px; padding-top: 15px; }
        .log-toggle { font-size: 0.75rem; color: var(--text-sub); cursor: pointer; text-align: center; display: block; margin-bottom: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .log-box { 
            background: var(--term-bg); color: var(--text-sub); 
            padding: 15px; border-radius: 12px; font-family: 'SF Mono', monospace; 
            font-size: 0.7rem; height: 160px; overflow-y: auto; 
            white-space: pre-wrap; display: none; border: 1px solid var(--border); 
        }
        .log-box.show { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        #toast { 
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); 
            background: var(--primary); color: white; padding: 12px 25px; 
            border-radius: 30px; font-size: 0.85rem; font-weight: 700; 
            opacity: 0; pointer-events: none; transition: 0.3s ease; z-index: 100;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
        }
        #toast.show { opacity: 1; bottom: 45px; }
    </style>
</head>
<body>

    <div class="head">
        <div class="logo">TS<span>DASHBOARD</span></div>
        <div id="pill-box" class="status-pill st-off">Wait...</div>
    </div>

    <div class="hero">
        <div id="pulse-ring" class="pulse-ring inactive">
            <div id="hero-icon" class="icon">⌛</div>
        </div>
        <div id="ip-text" class="ip-display">Checking...</div>
        <div id="status-desc" class="lbl">Initializing Monitor</div>
    </div>

    <div class="ctrl-area">
        <div id="btn-wrapper">
            <button class="btn-main btn-connect" disabled>Connecting...</button>
        </div>
    </div>

    <div id="auth-container" class="auth-box hidden">
        <div class="auth-head">🔑 Authentication</div>
        
        <?php if (isset($authUrl) && !empty($authUrl)): ?>
            <div class="term" onclick="copyText(this)"><?= htmlspecialchars($authUrl) ?></div>
            <div style="text-align:center; font-size:0.75rem; color:var(--succ); margin-top:10px; font-weight: 700;">Link Generated! Click to Copy</div>
        <?php else: ?>
            <div style="font-size:0.8rem; color:var(--text-sub); margin-bottom:15px; font-weight: 500;">Device requires authentication to join network.</div>
            <form method="POST">
                <button type="submit" name="action" value="get_auth" class="term-btn">GENERATE LOGIN URL</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="log-container">
        <span class="log-toggle" onclick="toggleLog()">Show System Logs</span>
        <div id="log-content" class="log-box">Loading console logs...</div>
    </div>

    <div id="logout-container" class="hidden">
        <form method="POST">
            <button type="submit" name="action" value="logout" class="btn-logout" onclick="return confirm('Disconnect and logout from Tailscale?')">Logout from Network</button>
        </form>
    </div>

    <div id="toast">Processing...</div>

    <script>
        const t = document.getElementById("toast");
        function msg(m) { t.innerText = m; t.className = "show"; setTimeout(() => t.className = "", 3000); }

        function copyText(el) {
            const text = el.innerText;
            const ta = document.createElement("textarea");
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand("copy");
            document.body.removeChild(ta);
            msg("Auth Link Copied!");
        }

        function toggleLog() {
            const log = document.getElementById('log-content');
            log.classList.toggle('show');
            const toggle = document.querySelector('.log-toggle');
            toggle.innerText = log.classList.contains('show') ? "Hide System Logs" : "Show System Logs";
        }

        function updateDashboard() {
            const fd = new FormData();
            fd.append('action', 'get_status');

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                document.getElementById('pill-box').className = 'status-pill ' + d.pill_class;
                document.getElementById('pill-box').innerText = d.pill_text;

                document.getElementById('pulse-ring').className = 'pulse-ring ' + d.css_class;
                document.getElementById('hero-icon').innerText = d.icon;

                document.getElementById('ip-text').innerText = d.ip_display;
                document.getElementById('status-desc').innerText = d.desc;

                document.getElementById('log-content').innerText = d.log_data;

                const btnWrap = document.getElementById('btn-wrapper');
                if (d.is_connected) {
                    btnWrap.innerHTML = `<button onclick="toggleTailscale('down')" class="btn-main btn-stop">⏹ Stop Service</button>`;
                } else {
                    btnWrap.innerHTML = `<button onclick="toggleTailscale('up')" class="btn-main btn-connect">🚀 Connect Network</button>`;
                }

                const showAuth = (!d.is_connected && !d.is_stopped);
                document.getElementById('auth-container').classList.toggle('hidden', !showAuth);
                document.getElementById('logout-container').classList.toggle('hidden', !d.is_connected);
            })
            .catch(e => console.log("Status check failed"));
        }

        function toggleTailscale(state) {
            msg(state === 'up' ? "Starting Service..." : "Stopping Service...");
            const fd = new FormData(); 
            fd.append('action', 'set_state'); 
            fd.append('state', state);

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                msg(d.message);
                updateDashboard();
            });
        }

        updateDashboard();
        setInterval(updateDashboard, 4000); 
    </script>
<script src="/assets/js/main.js"></script>
</body>
</html>