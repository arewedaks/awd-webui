<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

if (isset($_POST['action']) && $_POST['action'] === 'run_fix') {
    header('Content-Type: application/json');

    $source = '/data/adb/php8/files/backup/config.yaml';
    $dest = '/data/adb/box/clash/config.yaml';

    if (!file_exists($source)) {
        echo json_encode(['status' => 'error', 'message' => 'File Backup tidak ditemukan di: ' . $source]);
        exit;
    }

    $commands = [
        "box_stop" => "/data/adb/box/scripts/box.iptables disable || true",
        "srv_stop" => "/data/adb/box/scripts/box.service stop || true",
        "wait"     => "sleep 3",
        "copy"     => "cp -f '$source' '$dest'",
        "perm"     => "chmod 644 '$dest'"
    ];

    $full_cmd = implode('; ', $commands);
    $final_exec = "su -c \"$full_cmd\" 2>&1";
    
    exec($final_exec, $output, $return_var);
    $log_output = implode("\n", $output);

    $md5_source = md5_file($source);
    $md5_dest = file_exists($dest) ? md5_file($dest) : 'not_found';

    if ($md5_source === $md5_dest) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'BERHASIL! Service stop & Config terganti.',
            'debug' => "Source MD5: $md5_source\nDest MD5: $md5_dest"
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'GAGAL COPY! Hash file tidak sama.', 
            'debug' => "Output Shell:\n$log_output\n\nMD5 Source: $md5_source\nMD5 Dest: $md5_dest"
        ]);
    }
    exit;
}

$p = $_SERVER['HTTP_HOST'];
$x = explode(':', $p);
$host = $x[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Box For Root Manager</title>
    <style>
        /* --- TEMA VISIONOS CHOCOLATE GLASSMORPHISM (LOCKED) --- */
        :root {
            --primary: #B87333; 
            --accent: rgba(184, 115, 51, 0.15);
            --border-glass: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px);
            
            /* Light Mode */
            --card-bg: rgba(255, 248, 240, 0.15);
            --nav-cont-bg: rgba(62, 42, 28, 0.08);
            --text-main: #3E2A1C;
            --text-muted: #7A5C43;
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            
            --fix-color: #ff3b30;
            --fix-bg: rgba(255, 59, 48, 0.15);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2);
                --nav-cont-bg: rgba(253, 245, 230, 0.05);
                --text-main: #FDF5E6;
                --text-muted: #C0B2A2;
                --border-glass: rgba(255, 255, 255, 0.12);
                --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
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
            overflow: hidden; 
            -webkit-font-smoothing: antialiased;
        }

        /* --- HEADER & NAVIGATION --- */
        .head { 
            padding: 16px; 
            display: flex; 
            justify-content: center; 
            background: transparent; 
            flex-shrink: 0; 
            z-index: 10;
        }

        .nav-pill {
            background: var(--card-bg); 
            backdrop-filter: var(--blur-val); 
            -webkit-backdrop-filter: var(--blur-val);
            padding: 6px; 
            border-radius: 20px;
            display: flex; 
            gap: 6px; 
            overflow-x: auto; 
            max-width: 100%;
            scrollbar-width: none; 
            border: 1px solid var(--border-glass);
            box-shadow: var(--shadow);
        }
        .nav-pill::-webkit-scrollbar { display: none; }

        .tab {
            padding: 10px 20px; 
            border-radius: 14px; 
            font-size: 0.85rem; 
            font-weight: 700;
            color: var(--text-muted); 
            white-space: nowrap; 
            cursor: pointer;
            transition: all 0.3s ease; 
            user-select: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }

        .tab.active {
            background: var(--card-bg); 
            color: var(--primary);
            border-color: var(--border-glass);
            box-shadow: inset 0 1px 1px rgba(255,255,255,0.2), 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Khusus Tab FIX */
        .tab-fix { 
            color: var(--fix-color) !important; 
            background: var(--fix-bg);
            font-weight: 900; 
        }
        .tab-fix:hover { background: rgba(255, 59, 48, 0.25); }
        .tab-fix:active { transform: scale(0.95); opacity: 0.7; }

        /* --- CONTENT AREA --- */
        .main { 
            flex-grow: 1; 
            position: relative; 
            width: 100%; 
            height: 100%; 
        }

        .view { 
            display: none; 
            width: 100%; 
            height: 100%; 
        }
        
        .view.active { 
            display: block; 
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        iframe { 
            width: 100%; 
            height: 100%; 
            border: none; 
            background: transparent !important; /* Wajib transparan agar daun terlihat */
        }
    </style>
</head>
<body>

<div class="head">
    <div class="nav-pill">
        <div class="tab tab-fix" onclick="fx()" id="b-FIX">⚡ FIX</div>
        <div class="tab active" onclick="sw('BFR')" id="b-BFR">Dashboard</div>
        <div class="tab" onclick="sw('SRV')" id="b-SRV">Settings</div>
        <div class="tab" onclick="sw('EDT')" id="b-EDT">Accounts</div>
        <div class="tab" onclick="sw('YML')" id="b-YML">YAML</div>
    </div>
</div>

<div class="main">
    <div id="v-BFR" class="view active"><iframe id="f-BFR"></iframe></div>
    <div id="v-SRV" class="view"><iframe id="f-SRV"></iframe></div>
    <div id="v-EDT" class="view"><iframe id="f-EDT"></iframe></div>
    <div id="v-YML" class="view"><iframe id="f-YML"></iframe></div>
</div>

<script>
    const u = {
        'BFR': 'bfr/executed.php',
        'SRV': 'bfr/boxsettings.php',
        'EDT': 'convert/link2yaml.php',
        'YML': 'http://<?php echo $host; ?>/tiny/index.php?p=data%2Fadb%2Fbox%2Fclash&view=config.yaml',
    };

    function sw(k) {
        document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab:not(.tab-fix)').forEach(e => e.classList.remove('active'));
        
        document.getElementById('v-'+k).classList.add('active');
        document.getElementById('b-'+k).classList.add('active');
        
        const f = document.getElementById('f-'+k);
        if (!f.getAttribute('src')) f.src = u[k];
    }

    function fx() {
        if (!confirm("⚠️ Peringatan: Stop Box & Restore Config Default?")) return;
        const b = document.getElementById('b-FIX');
        const originalText = b.innerText;
        b.innerText = "⏳...";
        
        const fd = new FormData(); 
        fd.append('action', 'run_fix');
        
        fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                alert(d.message);
                if(d.status === 'success') location.reload();
            })
            .catch(() => alert("✖ Gagal koneksi ke sistem."))
            .finally(() => b.innerText = originalText);
    }

    document.addEventListener("DOMContentLoaded", () => {
        // Load default tab
        document.getElementById('f-BFR').src = u['BFR'];
    });
</script>

</body>
</html>