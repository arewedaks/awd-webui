<?php
// Function helper
function c($cmd) { return shell_exec("su -c \"$cmd\" 2>&1"); }

// Deteksi Interface Aktif
$iface = trim(c("ip route | grep default | awk '{print $5}'"));
if(empty($iface)) {
    // Fallback cari yang punya IP inet
    $iface = trim(c("ip addr | grep 'inet ' | grep -v '127.0.0.1' | awk '{print $7}' | head -n 1"));
}

// Baca Log
$logContent = "Log not found.";
if(file_exists(__DIR__ . '/debug.log')) {
    $logContent = file_get_contents(__DIR__ . '/debug.log');
}

// Cek TC Status
$tcClass = c("tc class show dev $iface");
$tcFilter = c("tc filter show dev $iface");
$tcIngress = c("tc filter show dev $iface parent ffff:");

// Cek ARP Table (Apakah HP klien terdeteksi?)
$arpTable = c("cat /proc/net/arp");
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Limiter Debugger</title>
    <style>
        body { background:#111; color:#eee; font-family:monospace; padding:20px; }
        h3 { color: #fb8c00; border-bottom: 1px solid #333; padding-bottom:10px; margin-top:30px; }
        pre { background:#222; padding:15px; border-radius:5px; overflow-x:auto; border:1px solid #444; color:#cfd8dc; font-size:12px; }
        .success { color:#4caf50; }
        .error { color:#f44336; }
        .warn { color:#ff9800; }
    </style>
</head>
<body>
    <h2 style="color:#fb8c00">SYSTEM DEBUGGER</h2>
    <div>Detected Interface: <b><?php echo $iface ? $iface : "<span class='error'>NONE</span>"; ?></b></div>
    
    <h3>1. DEBUG LOG (Dari core.sh)</h3>
    <pre><?php echo htmlspecialchars($logContent); ?></pre>

    <h3>2. ARP TABLE (Daftar Klien di Kernel)</h3>
    <div style="font-size:0.8rem; color:#888">Jika IP tidak ada di sini, script menganggap device OFFLINE.</div>
    <pre><?php echo htmlspecialchars($arpTable); ?></pre>

    <h3>3. TC CLASS (Limit Download)</h3>
    <div style="font-size:0.8rem; color:#888">Cari classid 1:xxx (User) atau 1:9999 (Global).</div>
    <pre><?php echo htmlspecialchars($tcClass); ?></pre>

    <h3>4. TC FILTER (Pengaturan IP)</h3>
    <pre><?php echo htmlspecialchars($tcFilter); ?></pre>

    <h3>5. TC INGRESS (Limit Upload)</h3>
    <pre><?php echo htmlspecialchars($tcIngress); ?></pre>
</body>
</html>