<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$save_path = '/data/adb/box/clash/proxy_provider/AKUN-VPN.yaml';

// --- DEFINISI VARIABEL ---
$php_bin = PHP_BINARY;
$backend_script = __DIR__ . "/backend.php"; 
// --------------------------

$result = "";
$message = "";
$raw_link = "";
$edit_mode = false;
$edit_content = "";
$edit_target_name = "";
$account_list = [];
$current_file_content = ""; 

if (file_exists($save_path)) {
    $current_file_content = file_get_contents($save_path);
    preg_match_all('/- name:\s*(.*)/', $current_file_content, $matches);
    if (!empty($matches[1])) {
        $account_list = $matches[1];
    }
} else {
    $current_file_content = "# File belum ada.";
}

// 1. PROSES CONVERT / ADD
if (isset($_POST['process_and_save'])) {
    $raw_link = $_POST['link'];
    if (!empty($raw_link)) {
        $trimmed_input = trim($raw_link);
        $yaml_result = "";
        
        if (strpos($trimmed_input, '- name:') === 0 || strpos(strtolower($trimmed_input), 'proxies:') === 0) {
            $yaml_result = $raw_link; 
        } else {
            $escaped_link = escapeshellarg($raw_link);
            $command = "$php_bin $backend_script convert $escaped_link 2>&1";
            $yaml_result = shell_exec($command);
        }

        if ($yaml_result && strlen(trim($yaml_result)) > 5) {
            $existing_content = file_exists($save_path) ? file_get_contents($save_path) : "";
            $combined_content = $existing_content . "\n" . $yaml_result;
            
            $escaped_combined = escapeshellarg($combined_content);
            $clean_cmd = "$php_bin $backend_script clean $escaped_combined 2>&1";
            $final_cleaned = shell_exec($clean_cmd);

            if ($final_cleaned !== null && strlen(trim($final_cleaned)) >= 8) {
                file_put_contents($save_path, $final_cleaned);
                $message = "Sukses! Akun disimpan.";
                $raw_link = "";
                header("Refresh:1"); 
            } else {
                $message = "Gagal memproses file.";
            }
        } else {
            $message = "Gagal. Format tidak valid.";
        }
    }
}

// 2. PROSES DELETE
if (isset($_POST['delete_account_direct'])) {
    $target_name = $_POST['delete_account_direct'];
    if (!empty($target_name) && file_exists($save_path)) {
        $current_content = file_get_contents($save_path);
        $escaped_content = escapeshellarg($current_content);
        $escaped_name = escapeshellarg($target_name);
        
        $command = "$php_bin $backend_script delete $escaped_content $escaped_name 2>&1";
        $new_content = shell_exec($command);
        
        if ($new_content !== null && strlen(trim($new_content)) >= 8) {
            file_put_contents($save_path, $new_content);
            $message = "Akun '$target_name' dihapus.";
            header("Refresh:1");
        }
    }
}

// 3. PROSES GET (EDIT)
if (isset($_POST['account_selector'])) {
    $target_name = $_POST['account_selector'];
    if (!empty($target_name) && file_exists($save_path)) {
        $current_content = file_get_contents($save_path);
        $escaped_content = escapeshellarg($current_content);
        $escaped_name = escapeshellarg($target_name);
        $command = "$php_bin $backend_script get $escaped_content $escaped_name 2>&1";
        $fetched_content = shell_exec($command);
        
        if (trim($fetched_content)) {
            $edit_mode = true;
            $edit_content = $fetched_content;
            $edit_target_name = $target_name;
        }
    }
}

// 4. PROSES UPDATE
if (isset($_POST['update_single_account'])) {
    $old_name = $_POST['target_old_name'];
    $new_content_block = $_POST['edited_account_content'];
    
    if (!empty($old_name) && !empty($new_content_block) && file_exists($save_path)) {
        $current_content = file_get_contents($save_path);
        $new_content_block = str_replace("\r\n", "\n", $new_content_block);
        $escaped_content = escapeshellarg($current_content);
        $escaped_name = escapeshellarg($old_name);
        $escaped_new_block = escapeshellarg($new_content_block);

        $command = "$php_bin $backend_script replace $escaped_content $escaped_name $escaped_new_block 2>&1";
        $updated_full_file = shell_exec($command);

        if ($updated_full_file !== null && strlen(trim($updated_full_file)) >= 8) {
            file_put_contents($save_path, $updated_full_file);
            $message = "Akun diperbarui!";
            $edit_mode = false;
            header("Refresh:1");
        }
    }
}

// 5. PROSES CLEAN
if (isset($_POST['clean_file'])) {
    if (file_exists($save_path)) {
        $current_content = file_get_contents($save_path);
        $escaped_content = escapeshellarg($current_content);
        $command = "$php_bin $backend_script clean $escaped_content 2>&1";
        $cleaned_content = shell_exec($command);
        if ($cleaned_content !== null && strlen(trim($cleaned_content)) >= 8) {
            file_put_contents($save_path, $cleaned_content);
            $message = "Format dirapikan.";
            header("Refresh:1");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Account Editor</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
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
            background: transparent !important; 
            color: var(--text-main); 
            padding: 15px; 
            max-width: 900px; 
            margin: 0 auto; 
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        .con { 
            background: var(--card-bg); 
            backdrop-filter: var(--blur-val); 
            -webkit-backdrop-filter: var(--blur-val);
            padding: 25px; 
            border-radius: 24px; 
            border: 1px solid var(--border); 
            box-shadow: var(--shadow); 
        }

        h2 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .sub-t { font-size: 0.75rem; font-weight: 700; color: var(--text-sub); text-transform: uppercase; letter-spacing: 1.5px; border-bottom: 1px dashed rgba(122,92,67,0.2); padding-bottom: 15px; margin-bottom: 20px; display: block; }

        /* --- FORM ELEMENTS --- */
        textarea { 
            width: 100%; 
            background: var(--inp-bg); 
            border: 1px solid var(--border); 
            color: var(--text-main); 
            padding: 15px; 
            border-radius: 14px; 
            font-family: 'SF Mono', monospace; 
            font-size: 0.85rem; 
            line-height: 1.5; 
            resize: none; 
            margin-bottom: 15px;
            backdrop-filter: blur(2px);
        }
        textarea:focus { border-color: var(--primary); }

        .btn-grp { display: flex; gap: 10px; flex-wrap: wrap; }
        button { 
            flex: 1; 
            min-width: 120px;
            padding: 14px; 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 0.9rem; 
            transition: 0.3s ease; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-pri { background: var(--primary); color: #fff; box-shadow: 0 4px 15px rgba(184, 115, 51, 0.2); }
        .btn-pri:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-sec { background: var(--inp-bg); color: var(--text-main); }
        .btn-sec:hover { background: rgba(255,255,255,0.1); }

        /* --- GRID ACCOUNTS --- */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
            gap: 12px; 
            max-height: 400px; 
            overflow-y: auto; 
            padding: 5px;
            margin-top: 20px;
        }

        .item { 
            display: flex; 
            background: var(--inp-bg); 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            overflow: hidden; 
            height: 42px; 
            align-items: center; 
            transition: 0.2s;
        }
        .item:hover { border-color: var(--primary); transform: translateX(3px); }

        .item-n { 
            flex: 1; 
            background: transparent; 
            color: var(--text-main); 
            text-align: left; 
            padding: 0 15px; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            font-family: 'SF Mono', monospace; 
            font-size: 0.8rem; 
            height: 100%; 
            border: none; 
            font-weight: 600;
            cursor: pointer;
        }
        .item-d { 
            width: 40px; 
            background: rgba(239, 68, 68, 0.1); 
            color: #ef4444; 
            border: none;
            border-left: 1px solid var(--border); 
            font-size: 1.2rem; 
            height: 100%; 
            cursor: pointer;
        }
        .item-d:hover { background: #ef4444; color: #fff; }

        .alert { 
            padding: 12px 20px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-weight: 700; 
            font-size: 0.85rem; 
            backdrop-filter: blur(10px);
        }
        .alert.s { background: rgba(52, 199, 89, 0.15); color: #32d74b; border: 1px solid rgba(52,199,89,0.3); }
        .alert.e { background: rgba(239, 68, 68, 0.15); color: #ff453a; border: 1px solid rgba(239,68,68,0.3); }

        /* --- MODAL GLASS --- */
        .modal { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(30, 18, 10, 0.6); 
            backdrop-filter: blur(15px); 
            z-index: 999; display: flex; flex-direction: column; padding: 20px; 
            animation: slide 0.3s ease-out; 
        }
        @keyframes slide { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between; /* Menendang satu ke kiri, satu ke kanan */
            padding: 12px 20px;
            background: var(--log-head); /* Opsional: sedikit lebih gelap agar judul terbaca */
        }
        
        .modal-head button {
            flex: none !important;     /* Melarang tombol melebar */
            width: auto !important;    /* Lebar sesuai teks */
            padding: 8px 16px !important;
            font-size: 0.8rem !important;
        }
        
        .modal-t {
            flex: none !important;
            text-align: right;        /* Memastikan teks judul rata kanan */
        }
        .modal-t { font-size: 1.1rem; font-weight: 800; color: #fff; text-transform: uppercase; }
        .modal-area { flex-grow: 1; background: rgba(0,0,0,0.3); color: #FDF5E6; border: 1px solid rgba(255,255,255,0.1); }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
    </style>
</head>
<body>

<?php if ($edit_mode): ?>
<div class="modal">
    <form method="post" style="display:flex;flex-direction:column;height:100%">
        <div class="modal-head">
            <div class="btn-grp" style="flex:none">
                <button type="button" class="btn-sec" style="min-width:80px" onclick="location.href=location.pathname">Back</button>
                <button type="submit" name="update_single_account" class="btn-pri" style="min-width:80px">Save</button>
            </div>
            <span class="modal-t">Edit Proxy</span>
        </div>
        <div style="margin-bottom:10px; font-size:0.8rem; font-weight:700; color:var(--primary)">TARGET: <?php echo htmlspecialchars($edit_target_name); ?></div>
        <textarea name="edited_account_content" class="modal-area"><?php echo htmlspecialchars($edit_content); ?></textarea>
        <input type="hidden" name="target_old_name" value="<?php echo htmlspecialchars($edit_target_name); ?>">
    </form>
</div>
<?php endif; ?>

<div id="advModal" class="modal" style="display:none; padding: 0;">
    <div class="modal-head">
        <button type="button" class="btn-sec" onclick="closeAdvEditor()">Close</button>
        <span class="modal-t">Advanced Editor</span>
    </div>
    <iframe id="tinyFrame" style="flex-grow:1; border:none; background:transparent" src=""></iframe>
</div>

<div class="con">
    <h2>Akun Editor</h2>
    <span class="sub-t">Great yaml editor</span>

    <?php if ($message): ?>
        <div class="alert <?php echo (strpos($message, 'Sukses')!==false || strpos($message, 'berhasil')!==false) ? 's' : 'e'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <textarea id="inLink" name="link" rows="3" placeholder="Paste link Vmess/Trojan/Vless di sini..."><?php echo htmlspecialchars($raw_link); ?></textarea>
        <div class="btn-grp">
            <button id="btnAction" type="submit" name="process_and_save" class="btn-pri">Convert</button>
        </div>
    </form>

    <form method="post">
        <?php if (!empty($account_list)): ?>
            <div class="grid">
                <?php foreach ($account_list as $acc): $acc=trim($acc); ?>
                    <div class="item">
                        <button type="submit" name="account_selector" value="<?php echo htmlspecialchars($acc); ?>" class="item-n">
                            <?php echo htmlspecialchars($acc); ?>
                        </button>
                        <button type="submit" name="delete_account_direct" value="<?php echo htmlspecialchars($acc); ?>" class="item-d" onclick="return confirm('Hapus akun: <?php echo htmlspecialchars($acc); ?>?');">
                            &times;
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:30px;border:1px dashed var(--border);color:var(--text-sub);border-radius:14px;font-style:italic;margin-top:20px">No accounts found.</div>
        <?php endif; ?>
    </form>

    <div class="btn-grp" style="margin-top:25px">
        <form method="post" style="flex:1"><button type="submit" name="clean_file" class="btn-sec" style="width:100%">Fix Format</button></form>
        <button type="button" class="btn-sec" style="flex:1" onclick="openAdvEditor()">Full Editor</button>
    </div>
</div>

<script>
    function openAdvEditor() {
        document.getElementById('advModal').style.display = 'flex';
        document.getElementById('tinyFrame').src = '/tiny/index.php?p=data/adb/box/clash/proxy_provider&edit=AKUN-VPN.yaml';
    }
    function closeAdvEditor() {
        document.getElementById('advModal').style.display = 'none';
        document.getElementById('tinyFrame').src = '';
    }

    const ta = document.getElementById('inLink');
    const btn = document.getElementById('btnAction');

    function checkInput() {
        if (!ta || !btn) return;
        const v = ta.value.trim();
        btn.innerHTML = (v.startsWith('- name:') || v.startsWith('proxies:')) ? 'Save' : 'Convert';
    }

    if (ta) {
        ta.addEventListener('input', checkInput);
        checkInput();
    }
</script>

<script src="/assets/js/main.js"></script>
</body>
</html>