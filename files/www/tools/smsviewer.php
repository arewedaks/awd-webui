<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

// Bypass SELinux & Root Prep
shell_exec("su -c 'setenforce 0' 2>&1");

// --- FUNCTIONS ---
function executeCmd($cmd) {
    return shell_exec("su -mm -c " . escapeshellarg($cmd) . " 2>&1");
}

function isTermuxApiAvailable() {
    $check = shell_exec("su -c 'pm path com.termux.api'");
    return (!empty($check) && strpos($check, 'package:') !== false);
}

function getSmsMessages() {
    $messages = [];
    if (isTermuxApiAvailable()) {
        $output = executeCmd("termux-sms-list -l 500");
        $data = json_decode($output, true);
        if (!empty($data) && is_array($data)) {
            foreach ($data as $sms) {
                $messages[] = [
                    'id'      => $sms['_id'] ?? $sms['id'] ?? 0,
                    'address' => $sms['number'] ?? $sms['address'] ?? 'Unknown',
                    'body'    => $sms['body'] ?? '',
                    'date'    => isset($sms['received']) ? strtotime($sms['received']) * 1000 : time() * 1000
                ];
            }
            return $messages;
        }
    }
    $cmd = "content query --uri content://sms --projection _id:address:date:body --sort 'date DESC LIMIT 500'";
    $raw = executeCmd($cmd);
    if (!empty($raw) && strpos($raw, 'Row:') !== false) {
        $rows = explode("Row:", $raw);
        foreach ($rows as $row) {
            $row = trim($row); if (empty($row)) continue;
            $id = $address = $date = $body = "";
            if (preg_match('/_id=(\d+)/', $row, $m)) $id = $m[1];
            if (preg_match('/address=(.*?),/', $row, $m)) $address = trim($m[1]);
            if (preg_match('/date=(\d+)/', $row, $m)) $date = $m[1];
            if (preg_match('/body=(.*)/s', $row, $m)) $body = $m[1];
            if ($id) {
                $messages[] = [
                    'id' => $id, 'address' => $address ?: 'Unknown',
                    'body' => $body, 'date' => (strlen($date) > 10) ? (int)$date : (int)$date * 1000
                ];
            }
        }
    }
    return $messages;
}

function sendSms($number, $message) {
    if (isTermuxApiAvailable()) {
        $res = executeCmd("termux-sms-send -n " . escapeshellarg($number) . " " . escapeshellarg($message));
        if (empty($res) || strpos($res, "not found") === false) return true;
    }
    $dest = escapeshellarg($number); $text = escapeshellarg($message); $pkg = "\"com.android.shell\"";
    executeCmd("service call isms 5 s16 $pkg s16 $dest s16 \"\" s16 $text i32 0 i32 0 i64 0 i32 1");
    return true;
}

function deleteSms($id) {
    return executeCmd("content delete --uri content://sms --where \"_id=" . intval($id) . "\"");
}

// --- LOGIC HANDLING ---
$activeTab = $_GET['tab'] ?? 'inbox';
$notification = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'send') {
            sendSms($_POST['number'], $_POST['message']);
            $notification = "Terkirim!";
        } elseif ($_POST['action'] === 'delete') {
            deleteSms($_POST['id']);
            $notification = "Dihapus.";
        }
    }
}

$allMessages = ($activeTab == 'inbox') ? getSmsMessages() : [];
$selectedSender = $_GET['sender'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$filtered = array_filter($allMessages, function($m) use ($selectedSender, $searchQuery) {
    $matchSender = empty($selectedSender) || $m['address'] === $selectedSender;
    $matchSearch = empty($searchQuery) || stripos($m['body'], $searchQuery) !== false;
    return $matchSender && $matchSearch;
});

$grouped = [];
foreach ($filtered as $m) { $grouped[$m['address']][] = $m; }
$uniqueSenders = array_unique(array_column($allMessages, 'address'));
sort($uniqueSenders);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SMS Manager</title>
    <style>
        /* --- CSS VARIABLES (VISIONOS CHOCOLATE) --- */
        :root {
            --card-bg: rgba(255, 248, 240, 0.15); 
            --blur: blur(5px);
            --text-main: #3E2A1C;
            --text-sub: #7A5C43;
            --border: rgba(255, 255, 255, 0.4);
            --border-dashed: rgba(122, 92, 67, 0.15);
            --inp-bg: rgba(62, 42, 28, 0.08); 
            --primary: #B87333; 
            --primary-bg: rgba(184, 115, 51, 0.15);
            --danger: #ff3b30;
            --danger-bg: rgba(255, 59, 48, 0.15);
            --accent: rgba(184, 115, 51, 0.15);
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --radius: 20px;
            --inner-radius: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); 
                --text-main: #FDF5E6;
                --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12);
                --inp-bg: rgba(253, 245, 230, 0.08); 
                --primary: #C19A6B; 
                --primary-bg: rgba(193, 154, 107, 0.2);
            }
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; background: transparent; color: var(--text-main); padding: 16px; max-width: 800px; margin: 0 auto; -webkit-font-smoothing: antialiased; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h2 { font-size: 1.1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .mode-badge { font-size: 0.65rem; background: var(--accent); color: var(--primary); padding: 4px 10px; border-radius: 8px; font-weight: 800; border: 1px solid var(--border); backdrop-filter: var(--blur); }

        /* --- TABS --- */
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; background: var(--card-bg); backdrop-filter: var(--blur); padding: 5px; border-radius: 18px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .tab-link { flex: 1; text-align: center; padding: 10px; border-radius: 14px; font-weight: 600; text-decoration: none; color: var(--text-sub); transition: 0.2s ease; font-size: 0.85rem; }
        .tab-link.active { background: var(--primary-bg); color: var(--primary); border: 1px solid rgba(184, 115, 51, 0.2); }

        /* --- FIXED FILTER BAR --- */
        .filter-bar { 
            background: var(--card-bg); 
            backdrop-filter: var(--blur);
            border: 1px solid var(--border); 
            border-radius: var(--radius); 
            padding: 10px; margin-bottom: 20px; 
            display: grid; 
            grid-template-columns: 1fr 1.5fr auto; /* Desktop layout */
            gap: 8px; 
            align-items: center;
        }

        @media (max-width: 500px) {
            .filter-bar { 
                grid-template-columns: 1fr 1fr; /* 2 kolom di HP */
            }
            .filter-bar select { grid-column: span 2; } /* Select full width di atas */
        }

        .filter-input { 
            width: 100%;
            height: 40px; /* Tinggi seragam */
            padding: 0 12px; 
            border: 1px solid var(--border); 
            border-radius: 10px; 
            background: var(--inp-bg); 
            color: var(--text-main); 
            font-size: 0.85rem; 
            font-weight: 500;
            appearance: none; /* Menghilangkan style default browser */
        }
        
        .filter-btn { 
            height: 40px;
            background: rgba(184, 115, 51, 0.85); 
            color: #fff; 
            border: 1px solid var(--border); 
            padding: 0 15px; 
            border-radius: 10px; 
            font-size: 0.85rem; 
            font-weight: 600;
            cursor: pointer; 
            transition: 0.2s;
        }
        .filter-btn:hover { background: var(--primary); }

        /* --- CONVERSATION CARD --- */
        .conv-card { background: var(--card-bg); backdrop-filter: var(--blur); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 12px; overflow: hidden; box-shadow: var(--shadow); }
        .conv-header { padding: 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .sender-info { font-weight: 800; color: var(--text-main); font-size: 0.95rem; }
        .msg-count { font-size: 0.7rem; background: var(--accent); color: var(--primary); padding: 3px 10px; border-radius: 10px; font-weight: 700; border: 1px solid var(--border); }
        .conv-body { display: none; padding: 0 15px 15px 15px; border-top: 1px dashed var(--border-dashed); }
        .conv-body.open { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        
        .sub-msg { padding: 12px 0; border-bottom: 1px dashed var(--border-dashed); }
        .sub-msg:last-child { border-bottom: none; }
        .sub-date { font-size: 0.65rem; color: var(--text-sub); display: block; margin-bottom: 5px; font-weight: 600; }
        .sub-text { font-size: 0.85rem; word-break: break-word; color: var(--text-main); margin-bottom: 8px; }
        .del-btn { color: var(--danger); font-size: 0.65rem; background: var(--danger-bg); border: 1px solid rgba(255, 59, 48, 0.2); font-weight: 700; cursor: pointer; padding: 5px 10px; border-radius: 6px; }

        .form-box { background: var(--card-bg); backdrop-filter: var(--blur); padding: 20px; border-radius: var(--radius); border: 1px solid var(--border); }
        .notif { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(52, 199, 89, 0.9); color: #fff; padding: 10px 20px; border-radius: 20px; font-size: 0.8rem; z-index: 100; backdrop-filter: blur(10px); }
    </style>
</head>
<body>

    <div class="header">
        <h2>SMS Viewer</h2>
        <span class="mode-badge"><?= isTermuxApiAvailable() ? 'API' : 'ROOT' ?> MODE</span>
    </div>

    <?php if($notification): ?> <div class="notif" id="notif"><?= $notification ?></div> <?php endif; ?>

    <nav class="tabs">
        <a href="?tab=inbox" class="tab-link <?= $activeTab=='inbox'?'active':'' ?>">Inbox</a>
        <a href="?tab=send" class="tab-link <?= $activeTab=='send'?'active':'' ?>">Kirim</a>
    </nav>

    <?php if ($activeTab == 'inbox'): ?>
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="inbox">
            <select name="sender" class="filter-input" onchange="this.form.submit()">
                <option value="">Semua Nomor</option>
                <?php foreach($uniqueSenders as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $selectedSender==$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="filter-input" placeholder="Cari pesan..." value="<?= htmlspecialchars($searchQuery) ?>">
            <button type="submit" class="filter-btn">Cari</button>
        </form>

        <?php if(empty($grouped)): ?>
            <p style="text-align:center; padding: 40px; color: var(--text-sub);">Inbox Kosong.</p>
        <?php else: ?>
            <?php foreach($grouped as $sender => $msgs): ?>
                <div class="conv-card">
                    <div class="conv-header" onclick="this.nextElementSibling.classList.toggle('open')">
                        <div>
                            <span class="sender-info"><?= htmlspecialchars($sender) ?></span>
                            <div style="font-size: 0.75rem; color: var(--text-sub);"><?= htmlspecialchars(substr($msgs[0]['body'], 0, 40)) ?>...</div>
                        </div>
                        <span class="msg-count"><?= count($msgs) ?></span>
                    </div>
                    <div class="conv-body">
                        <?php foreach($msgs as $m): ?>
                            <div class="sub-msg">
                                <span class="sub-date"><?= date('d M Y, H:i', $m['date']/1000) ?></span>
                                <div class="sub-text"><?= htmlspecialchars($m['body']) ?></div>
                                <form method="POST" onsubmit="return confirm('Hapus pesan ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="del-btn">Hapus</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php elseif ($activeTab == 'send'): ?>
        <div class="form-box">
            <form method="POST">
                <input type="hidden" name="action" value="send">
                <input type="text" name="number" class="filter-input" style="margin-bottom:12px;" placeholder="Nomor Tujuan" required>
                <textarea name="message" class="filter-input" style="height:120px; margin-bottom:15px; padding-top:10px;" placeholder="Pesan..." required></textarea>
                <button type="submit" class="filter-btn" style="width:100%;">Kirim Sekarang</button>
            </form>
        </div>
    <?php endif; ?>

    <script>
        setTimeout(() => { if(document.getElementById('notif')) document.getElementById('notif').style.display = 'none'; }, 2500);
    </script>
</body>
</html>
