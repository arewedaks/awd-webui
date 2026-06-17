<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set directory so we can read databases (using root via shell to ensure access)
shell_exec("su -c 'chmod 777 /data/data/com.awd.modemtools/databases' 2>&1");
$db_path = '/data/data/com.awd.modemtools/databases/vouchers.db';

$notification = "";
$status = "";

// Helper to get Client MAC address (Usually passed via iptables or arp)
function getClientMac($ip) {
    $arp = shell_exec("arp -n " . escapeshellarg($ip));
    if (preg_match('/([0-9a-fA-F]{2}:){5}[0-9a-fA-F]{2}/', $arp, $matches)) {
        return strtoupper($matches[0]);
    }
    return "UNKNOWN";
}

$client_ip = $_SERVER['REMOTE_ADDR'];
$client_mac = getClientMac($client_ip);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    
    if (!empty($code)) {
        // Cek apakah database ada
        if (file_exists($db_path)) {
            $db = new SQLite3($db_path);
            
            // Cek kode voucher
            $stmt = $db->prepare("SELECT * FROM vouchers WHERE code = :code");
            $stmt->bindValue(':code', $code, SQLITE3_TEXT);
            $res = $stmt->execute();
            $voucher = $res->fetchArray(SQLITE3_ASSOC);
            
            if ($voucher) {
                if ($voucher['status'] === 'available') {
                    // Aktifkan voucher
                    $upd = $db->prepare("UPDATE vouchers SET status = 'active', client_mac = :mac, activated_at = :time WHERE id = :id");
                    $upd->bindValue(':mac', $client_mac, SQLITE3_TEXT);
                    $upd->bindValue(':time', time(), SQLITE3_INTEGER);
                    $upd->bindValue(':id', $voucher['id'], SQLITE3_INTEGER);
                    $upd->execute();
                    
                    // TODO: Execute iptables rule here later
                    
                    $status = "success";
                    $notification = "Login Berhasil! Akses Internet Terbuka.";
                } elseif ($voucher['status'] === 'active') {
                    // Cek expired
                    $expTime = $voucher['activated_at'] + ($voucher['duration_hours'] * 3600);
                    if (time() > $expTime) {
                        $upd = $db->prepare("UPDATE vouchers SET status = 'expired' WHERE id = :id");
                        $upd->bindValue(':id', $voucher['id'], SQLITE3_INTEGER);
                        $upd->execute();
                        
                        $status = "error";
                        $notification = "Voucher sudah kadaluarsa.";
                    } else {
                        if ($voucher['client_mac'] === $client_mac || $voucher['client_mac'] === 'UNKNOWN' || $client_mac === 'UNKNOWN') {
                            // Re-login success
                            $status = "success";
                            $notification = "Anda sudah login. Akses terbuka.";
                        } else {
                            $status = "error";
                            $notification = "Voucher ini sedang digunakan di perangkat lain.";
                        }
                    }
                } else {
                    $status = "error";
                    $notification = "Voucher sudah kadaluarsa.";
                }
            } else {
                $status = "error";
                $notification = "Kode voucher tidak valid.";
            }
            $db->close();
        } else {
            $status = "error";
            $notification = "Sistem Voucher Belum Dikonfigurasi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hotspot Login</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        html, body {
            background-color: #F8F9FA !important; /* Latar belakang terang (Light Mode) */
        }
        @media (prefers-color-scheme: dark) {
            html, body {
                background-color: #0A0A0A !important; /* Latar belakang gelap (Dark Mode) */
            }
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            margin: 0;
            background-image: radial-gradient(circle at center, var(--primary-bg) 0%, transparent 70%) !important;
        }
        .portal-card {
            width: 100%;
            max-width: 400px;
            text-align: center;
            padding: 40px 30px;
            margin: auto;
        }
        .logo-circle {
            width: 80px;
            height: 80px;
            background: var(--primary-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            border: 1px solid var(--border-glass);
            color: var(--primary);
            box-shadow: inset 0 2px 10px rgba(255,255,255,0.1);
        }
        .inp-code {
            width: 100%;
            height: 55px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: var(--inp-bg);
            color: var(--text-main);
            font-size: 1.3rem;
            font-weight: 900;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        .inp-code:focus {
            border-color: var(--primary);
            box-shadow: 0 0 15px rgba(184, 115, 51, 0.2);
            outline: none;
        }
        .btn-login {
            width: 100%;
            height: 55px;
            border-radius: 16px;
            font-size: 1.05rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
        }
        .success-checkmark {
            width: 70px;
            height: 70px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            color: white;
            box-shadow: 0 8px 25px rgba(50, 215, 75, 0.4);
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        /* Decorative Background Elements */
        .bg-blob {
            position: fixed;
            filter: blur(60px);
            z-index: -1;
            opacity: 0.3;
            border-radius: 50%;
            pointer-events: none;
        }
        .blob-1 {
            width: 300px;
            height: 300px;
            background: var(--primary);
            top: -50px;
            left: -50px;
        }
        .blob-2 {
            width: 250px;
            height: 250px;
            background: var(--warning);
            bottom: -50px;
            right: -50px;
        }
    </style>
</head>
<body>

    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="card portal-card">
        <?php if($status === 'success'): ?>
            <div class="success-checkmark">
                <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <h1 style="font-size: 1.6rem; margin-bottom: 5px;">Terkoneksi!</h1>
            <div style="margin-bottom: 20px; color: var(--suc); font-weight: 800; font-size: 0.9rem;"><?= htmlspecialchars($notification) ?></div>
            <div style="font-size: 0.8rem; color: var(--text-sub); font-weight: 700; line-height: 1.5;">
                Anda sekarang sudah terhubung ke Internet.<br>Silakan tutup halaman ini.
            </div>
        <?php else: ?>
            <div class="logo-circle">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
            </div>
            <h1 style="font-size: 1.6rem; margin-bottom: 5px;">AWD Hotspot</h1>
            <div class="sub-t" style="margin-bottom: 30px;">Masukkan kode voucher akses</div>
            
            <?php if($status === 'error'): ?>
                <div class="alert" style="background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(255, 69, 58, 0.3);">
                    <?= htmlspecialchars($notification) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="text" name="code" class="inp-code" placeholder="KODE VOUCHER" required autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false">
                <button type="submit" class="btn btn-s btn-login">Mulai Akses</button>
            </form>
            
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 25px; font-weight: 700;">
                IP: <?= htmlspecialchars($client_ip) ?> <?= $client_mac !== 'UNKNOWN' ? ' • MAC: '.$client_mac : '' ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
