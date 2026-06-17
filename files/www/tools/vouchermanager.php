<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
require_once '/data/adb/php8/files/www/utils.php';

// Bypass SELinux & Root Prep
shell_exec("su -c 'setenforce 0' 2>&1");
shell_exec("su -c 'mkdir -p /data/data/com.awd.modemtools/databases' 2>&1");
shell_exec("su -c 'chmod 777 /data/data/com.awd.modemtools/databases' 2>&1");

$db_path = '/data/data/com.awd.modemtools/databases/vouchers.db';

// Initialize DB
function init_db() {
    global $db_path;
    $db = new SQLite3($db_path);
    $db->exec("CREATE TABLE IF NOT EXISTS vouchers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE,
        duration_hours INTEGER,
        status TEXT DEFAULT 'available',
        client_mac TEXT DEFAULT '',
        activated_at INTEGER DEFAULT 0
    )");
    shell_exec("su -c 'chmod 666 $db_path' 2>&1");
    return $db;
}

$db = init_db();
$notification = "";

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_pro_user()) {
        die("Akses ditolak: Fitur Pro.");
    }
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'generate') {
            $code = trim($_POST['code']);
            $duration = (int)$_POST['duration'];
            
            if (empty($code)) {
                // Generate random 5 chars (alphanumeric uppercase)
                $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $code = '';
                for ($i = 0; $i < 5; $i++) {
                    $code .= $chars[rand(0, strlen($chars) - 1)];
                }
            }
            
            $stmt = $db->prepare("INSERT INTO vouchers (code, duration_hours) VALUES (:code, :duration)");
            $stmt->bindValue(':code', strtoupper($code), SQLITE3_TEXT);
            $stmt->bindValue(':duration', $duration, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $notification = "Voucher $code berhasil dibuat!";
            } else {
                $notification = "Gagal: Kode sudah ada!";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $db->exec("DELETE FROM vouchers WHERE id = $id");
            $notification = "Voucher dihapus.";
        }
    }
}

$vouchers = [];
$res = $db->query("SELECT * FROM vouchers ORDER BY id DESC");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $vouchers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Voucher Manager</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .form-box { background: var(--card-bg); backdrop-filter: var(--blur); padding: 20px; border-radius: var(--radius); border: 1px solid var(--border); margin-bottom: 20px; box-shadow: var(--shadow); }
        .inp { width: 100%; height: 44px; padding: 0 15px; border: 1px solid var(--border); border-radius: 12px; background: var(--inp-bg); color: var(--text-main); font-size: 0.9rem; margin-bottom: 15px; font-weight: 600; }
        .v-card { background: var(--card-bg); backdrop-filter: var(--blur); border: 1px solid var(--border); border-radius: 18px; padding: 18px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow); transition: 0.2s; }
        .v-code { font-size: 1.3rem; font-weight: 800; color: var(--primary); letter-spacing: 2px; }
        .v-info { font-size: 0.75rem; color: var(--text-sub); margin-top: 6px; font-weight: 600; }
        .badge { font-size: 0.65rem; padding: 5px 10px; border-radius: 10px; text-transform: uppercase; font-weight: 800; border: 1px solid var(--border); }
        .bg-av { background: var(--success-bg); color: var(--suc); border-color: rgba(50, 215, 75, 0.3); }
        .bg-ac { background: var(--warning-bg); color: var(--warning); border-color: rgba(255, 152, 0, 0.3); }
        .bg-ex { background: var(--danger-bg); color: var(--dang); border-color: rgba(255, 69, 58, 0.3); }
        .notif { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(52, 199, 89, 0.9); color: #fff; padding: 10px 20px; border-radius: 20px; font-size: 0.85rem; z-index: 100; backdrop-filter: blur(10px); font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Voucher Hotspot</h2>
        <div class="sub-t">Manajemen Akses Klien</div>
    </div>

    <?php if($notification): ?>
        <div class="notif" id="notif"><?= htmlspecialchars($notification) ?></div>
    <?php endif; ?>

    <?php if (!is_pro_user()): ?>
        <?php render_pro_lock_screen("Voucher Hotspot Manager"); ?>
    <?php else: ?>

    <div class="form-box">
        <form method="POST">
            <input type="hidden" name="action" value="generate">
            <label style="font-size:0.85rem; font-weight:800; display:block; margin-bottom:8px; color:var(--text-sub);">Kode Custom (Kosongkan untuk acak):</label>
            <input type="text" name="code" class="inp" placeholder="Cth: VIP123 (Opsional)" maxlength="15" style="text-transform: uppercase;">
            
            <label style="font-size:0.85rem; font-weight:800; display:block; margin-bottom:8px; color:var(--text-sub);">Durasi Akses (Jam):</label>
            <input type="number" name="duration" class="inp" value="1" min="1" max="720" required>
            
            <button type="submit" class="btn btn-s" style="width:100%; height:44px; font-size:0.9rem;">+ Buat Voucher Baru</button>
        </form>
    </div>

    <div style="font-size:1.1rem; font-weight:800; margin-bottom:15px; padding-bottom:10px; color:var(--text-main); display:flex; align-items:center; gap:10px;">
        Daftar Voucher
        <span style="font-size:0.7rem; background:var(--primary-bg); color:var(--primary); padding:3px 8px; border-radius:10px;"><?= count($vouchers) ?></span>
    </div>

    <?php if(empty($vouchers)): ?>
        <div style="text-align:center; padding: 40px 20px; background:var(--card-bg); border-radius:var(--radius); border:1px dashed var(--border-dashed);">
            <p style="color:var(--text-sub); font-size:0.9rem; font-weight:600;">Belum ada voucher yang dibuat.</p>
        </div>
    <?php else: ?>
        <?php foreach($vouchers as $v): ?>
            <?php 
                $badgeCls = 'bg-av';
                $statusText = $v['status'];
                
                if($v['status'] == 'active') {
                    $badgeCls = 'bg-ac';
                    $expTime = $v['activated_at'] + ($v['duration_hours'] * 3600);
                    if(time() > $expTime) {
                        $statusText = 'expired';
                        $badgeCls = 'bg-ex';
                        $db->exec("UPDATE vouchers SET status='expired' WHERE id=".$v['id']);
                    }
                } elseif ($v['status'] == 'expired') {
                    $badgeCls = 'bg-ex';
                }
                
                $expStr = "-";
                if($statusText == 'active') {
                    $expStr = date('d M, H:i', $expTime);
                } elseif ($statusText == 'expired' && $v['activated_at'] > 0) {
                    $expStr = "Berakhir: " . date('d M, H:i', $v['activated_at'] + ($v['duration_hours'] * 3600));
                }
            ?>
            <div class="v-card">
                <div>
                    <div class="v-code"><?= htmlspecialchars($v['code']) ?></div>
                    <div class="v-info">Durasi: <?= $v['duration_hours'] ?> Jam <?= $v['client_mac'] ? ' • MAC: '.$v['client_mac'] : '' ?></div>
                    <div class="v-info">Selesai: <?= $expStr ?></div>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                    <span class="badge <?= $badgeCls ?>"><?= strtoupper($statusText) ?></span>
                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus voucher ini?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                        <button type="submit" class="btn btn-d btn-sm" style="font-size:0.6rem; padding:4px 10px;">Hapus</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>

    <script>
        setTimeout(() => { if(document.getElementById('notif')) document.getElementById('notif').style.display = 'none'; }, 3000);
    </script>
<script src="/assets/js/main.js"></script>
</body>
</html>
