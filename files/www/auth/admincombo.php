<?php
session_start();
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

if (isset($_SESSION['login_disabled']) && $_SESSION['login_disabled'] === true) {} else { checkUserLogin(); }

$credentials_file = __DIR__ . '/credentials.php';
$config_file = __DIR__ . '/config.json';
$credentials = include $credentials_file;
$stored_username = $credentials['username'];
$config = @json_decode(file_get_contents($config_file), true);
if (!is_array($config)) $config = [];
$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_creds') {
        $new_u = $_POST['new_username'];
        $new_p = $_POST['new_password'];
        $cnf_p = $_POST['confirm_new_password'];
        if ($new_p === $cnf_p) {
            $hash = password_hash($new_p, PASSWORD_DEFAULT);
            $content = "<?php\nif (basename(__FILE__) == basename(\$_SERVER['PHP_SELF'])) { header('Location: /'); exit; }\nreturn ['username' => '" . addslashes($new_u) . "', 'hashed_password' => '" . addslashes($hash) . "'];\n";
            if (@file_put_contents($credentials_file, $content) === false) {
                shell_exec("su -c 'echo \"".str_replace('"', '\"', $content)."\" > \"$credentials_file\"'");
            }
            $msg = 'Credentials Updated!'; $msg_type = 'success'; $stored_username = $new_u;
        } else {
            $msg = 'Passwords do not match!'; $msg_type = 'error';
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'update_config') {
        $config['LOGIN_ENABLED'] = isset($_POST['login_enabled']);
        $json_data = json_encode($config, JSON_PRETTY_PRINT);
        $write = @file_put_contents($config_file, $json_data);
        if ($write === false) {
            $safe_json = str_replace("'", "'\\''", $json_data);
            shell_exec("su -c 'echo \"$safe_json\" > \"$config_file\"'");
        }
        if(isset($_POST['ajax'])) { echo json_encode(['status'=>'success', 'state'=>$config['LOGIN_ENABLED']]); exit; }
        $msg = 'Settings Saved!'; $msg_type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Security Admin</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --suc: #32d74b; --dang: #ff3b30;
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
            padding: 20px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; -webkit-font-smoothing: antialiased;
        }
        .con { width: 100%; max-width: 500px; }
        .head { text-align: center; margin-bottom: 30px; }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        p { color: var(--text-sub); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .card { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; padding: 24px; box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 20px;
            position: relative; overflow: hidden;
        }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .lbl-main { font-size: 1rem; font-weight: 800; color: var(--text-main); }
        .lbl-sub { font-size: 0.75rem; color: var(--text-sub); display: block; margin-top: 4px; font-weight: 600; }
        .sw { position: relative; width: 50px; height: 28px; display: inline-block; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.1); transition: .4s; border-radius: 34px; border: 1px solid var(--border); }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .sl { background-color: var(--primary); }
        input:checked + .sl:before { transform: translateX(22px); }
        .div { border-top: 1px dashed rgba(122, 92, 67, 0.2); margin: 20px 0; }
        .grp { margin-bottom: 18px; }
        label { display: block; margin-bottom: 8px; font-size: 0.75rem; font-weight: 800; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; }
        input[type=text], input[type=password] { 
            width: 100%; padding: 14px; border-radius: 14px; border: 1px solid var(--border); 
            background: rgba(255, 255, 255, 0.05); color: var(--text-main); font-size: 1rem; 
            font-weight: 600; transition: 0.3s;
        }
        input:focus { border-color: var(--primary); background: rgba(255,255,255,0.1); }
        .btn { 
            width: 100%; padding: 16px; margin-top: 15px; border: none; border-radius: 16px; 
            background: var(--primary); color: white; font-weight: 800; font-size: 0.9rem; 
            cursor: pointer; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px; 
            box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2);
        }
        .btn:active { transform: scale(0.97); }
        #toast { 
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); 
            background: var(--primary); color: #fff; padding: 12px 25px; border-radius: 30px; 
            font-size: 0.85rem; font-weight: 800; opacity: 0; transition: 0.3s; z-index: 100; 
            backdrop-filter: blur(10px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); pointer-events: none;
        }
        #toast.show { opacity: 1; bottom: 45px; }
        #toast.err { background: var(--dang); } #toast.suc { background: var(--primary); }
    </style>
</head>
<body>
    <div class="con">
        <div class="head">
            <h1>Administration</h1>
            <p>Vault & Access Control</p>
        </div>
        <?php if ($msg): ?>
            <div id="php-msg" data-type="<?= $msg_type ?>" data-text="<?= $msg ?>"></div>
        <?php endif; ?>
        <div class="card">
            <div class="row">
                <div>
                    <span class="lbl-main">Authentication</span>
                    <span class="lbl-sub">Enable Login Security</span>
                </div>
                <label class="sw">
                    <input type="checkbox" id="loginToggle" <?= (isset($config['LOGIN_ENABLED']) && $config['LOGIN_ENABLED']) ? 'checked' : '' ?>>
                    <span class="sl"></span>
                </label>
            </div>
            <div class="div"></div>
            <form method="POST">
                <input type="hidden" name="action" value="update_creds">
                <div class="grp">
                    <label>Manager Username</label>
                    <input type="text" name="new_username" value="<?= htmlspecialchars($stored_username) ?>" required>
                </div>
                <div class="grp">
                    <label>Master Password</label>
                    <input type="password" name="new_password" required minlength="4">
                </div>
                <div class="grp">
                    <label>Confirm Master Password</label>
                    <input type="password" name="confirm_new_password" required minlength="4">
                </div>
                <button type="submit" class="btn">Update Credentials</button>
            </form>
        </div>
    </div>
    <div id="toast">Saved!</div>
    <script>
        const t = document.getElementById("toast");
        const pm = document.getElementById("php-msg");
        function show(m, type='suc') { 
            t.innerText = m; t.className = "show " + (type==='error'?'err':'suc'); 
            setTimeout(() => t.className = "", 3000); 
        }
        if (pm) show(pm.dataset.text, pm.dataset.type);
        document.getElementById('loginToggle').addEventListener('change', function() {
            const s = this.checked;
            const fd = new FormData();
            fd.append('action', 'update_config');
            fd.append('ajax', '1');
            if(s) fd.append('login_enabled', 'on');
            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') show(s ? 'Security Shield Enabled' : 'Security Shield Disabled');
                else { show('Write Error', 'error'); this.checked = !s; }
            })
            .catch(() => { show('System Error', 'error'); this.checked = !s; });
        });
    </script>
</body>
</html>