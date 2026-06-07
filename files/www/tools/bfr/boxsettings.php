<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$ini_file_path = '/data/adb/box/settings.ini';

function parse_settings_ini($file_path) {
    if (!file_exists($file_path)) return [];
    $lines = file($file_path, FILE_IGNORE_NEW_LINES);
    $settings = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] == ';' || $line[0] == '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            $settings[$key] = $value;
        }
    }
    return $settings;
}

$settings = parse_settings_ini($ini_file_path);

$bool_list = ['port_detect', 'ipv6', 'cgroup_cpuset', 'cgroup_blkio', 'cgroup_memcg', 'run_crontab', 'update_geo', 'renew', 'update_subscription'];
$form_list = ['tproxy_port', 'redir_port', 'memcg_limit', 'subscription_url_clash', 'name_clash_config', 'name_sing_config'];
$dropdown_list = [
    'bin_name' => ['clash', 'sing-box', 'xray', 'v2fly'],
    'xclash_option' => ['mihomo', 'premium'],
    'network_mode' => ['redirect', 'tproxy', 'mixed', 'enhance', 'tun'],
    'proxy_mode' => ['blacklist', 'whitelist']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lines = file($ini_file_path, FILE_IGNORE_NEW_LINES);
    $new_content = [];
    
    foreach ($lines as $line) {
        $trimLine = trim($line);
        if ($trimLine === '' || $trimLine[0] == ';' || $trimLine[0] == '#') {
            $new_content[] = $line;
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            
            if (isset($_POST[$key])) {
                $orig_val = trim($val);
                $new_val = $_POST[$key];
                
                if (preg_match('/^".*"$/', $orig_val)) $new_val = '"' . $new_val . '"';
                elseif (preg_match("/^'.*'$/", $orig_val)) $new_val = "'" . $new_val . "'";
                
                $new_content[] = "$key=$new_val";
            } else {
                $new_content[] = $line;
            }
        } else {
            $new_content[] = $line;
        }
    }

    file_put_contents($ini_file_path, implode("\n", $new_content) . "\n");
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Box Config</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* --- TEMA VISIONOS CHOCOLATE GLASSMORPHISM (LOCKED) --- */
        :root {
            --primary-hover: #8B5A2B;
            --inp-bg: rgba(62, 42, 28, 0.05);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --inp-bg: rgba(253, 245, 230, 0.08);
            }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
            background: transparent !important; /* Tembus ke daun index.php */
            color: var(--text-main); 
            padding: 16px; 
            max-width: 800px; 
            margin: 0 auto; 
            padding-bottom: 80px; 
            -webkit-font-smoothing: antialiased;
        }
        
        /* --- GLASSMORPHISM CARD --- */
        .card { 
            background: var(--card-bg); 
            backdrop-filter: var(--blur-val); 
            -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; 
            box-shadow: var(--shadow); 
            border: 1px solid var(--border); 
            padding: 24px; 
            position: relative;
            overflow: hidden;
        }

        .card::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            border-radius: 24px; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none;
        }

        .head { margin-bottom: 24px; padding-bottom: 15px; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
        p { color: var(--text-sub); font-size: 0.85rem; font-weight: 500; }

        .grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
        @media (min-width: 600px) { .grid { grid-template-columns: 1fr 1fr; } }
        .full { grid-column: 1 / -1; }

        .grp { margin-bottom: 5px; }
        label { display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 8px; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; }
        
        input, select { 
            width: 100%; 
            padding: 12px 16px; 
            border-radius: 12px; 
            border: 1px solid var(--border); 
            background: var(--inp-bg); 
            color: var(--text-main); 
            font-size: 0.95rem; 
            font-weight: 500;
            transition: 0.3s ease; 
            backdrop-filter: blur(2px);
        }
        
        input:focus, select:focus { 
            border-color: var(--primary); 
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 3px rgba(184, 115, 51, 0.2); 
        }

        /* --- BUTTON CHOCOLATE --- */
        .btn { 
            width: 100%; 
            background: var(--primary); 
            color: #fff; 
            border: 1px solid var(--border); 
            padding: 16px; 
            border-radius: 14px; 
            font-size: 0.95rem; 
            font-weight: 700; 
            cursor: pointer; 
            margin-top: 25px; 
            transition: 0.3s ease; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
        .btn:active { transform: translateY(0); }
        .btn:disabled { opacity: 0.5; cursor: wait; }

        /* --- TOAST GLASS --- */
        #toast { 
            visibility: hidden; 
            min-width: 250px; 
            background: rgba(52, 199, 89, 0.85); 
            color: #fff; 
            text-align: center; 
            border-radius: 50px; 
            padding: 14px; 
            position: fixed; 
            z-index: 100; 
            bottom: 30px; 
            left: 50%; 
            transform: translateX(-50%); 
            box-shadow: var(--shadow); 
            font-weight: 700; 
            opacity: 0; 
            transition: 0.4s; 
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
    </style>
</head>
<body>

    <div class="card">
        <div class="head">
            <h1>Configuration</h1>
            <p>Settings for Box (Core, Network, & Proxy)</p>
        </div>

        <form id="cfgForm">
            <div class="grid">
                <?php foreach ($dropdown_list as $key => $options): ?>
                <div class="grp">
                    <label for="<?= $key ?>"><?= str_replace('_', ' ', $key) ?></label>
                    <select name="<?= $key ?>" id="<?= $key ?>">
                        <?php foreach ($options as $opt): 
                            $sel = (trim($settings[$key] ?? '') === $opt); ?>
                            <option value="<?= $opt ?>" <?= $sel ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>

                <?php foreach ($bool_list as $key): ?>
                <div class="grp">
                    <label for="<?= $key ?>"><?= str_replace('_', ' ', $key) ?></label>
                    <select name="<?= $key ?>" id="<?= $key ?>">
                        <option value="true" <?= ($settings[$key] ?? '') === 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($settings[$key] ?? '') === 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <?php endforeach; ?>

                <?php foreach ($form_list as $key): ?>
                <div class="grp <?= (strpos($key, 'url') !== false || strpos($key, 'name') !== false) ? 'full' : '' ?>">
                    <label for="<?= $key ?>"><?= str_replace('_', ' ', $key) ?></label>
                    <input type="text" name="<?= $key ?>" id="<?= $key ?>" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn">Save Configuration</button>
        </form>
    </div>

    <div id="toast">Settings Saved Successfully!</div>

    <script>
        document.getElementById('cfgForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.querySelector('.btn');
            const txt = btn.innerText;
            btn.innerText = 'Applying...'; btn.disabled = true;

            fetch('', { method: 'POST', body: new FormData(this) })
            .then(r => r.json())
            .then(d => { if(d.status === 'success') showToast(); })
            .catch(err => alert('Error saving settings'))
            .finally(() => { btn.innerText = txt; btn.disabled = false; });
        });

        function showToast() {
            const t = document.getElementById("toast");
            t.className = "show";
            setTimeout(() => t.className = t.className.replace("show", ""), 3000);
        }
    </script>

<script src="/assets/js/main.js"></script>
</body>
</html>