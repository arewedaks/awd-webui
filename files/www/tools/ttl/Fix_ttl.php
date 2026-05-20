<?php
$message = "";
require_once '/data/adb/php8/files/www/auth/auth_functions.php'; 
define('IPT', '/system/bin/iptables');
define('IP6T', '/system/bin/ip6tables');
$cfgFile = '/data/adb/php8/files/config/onboot.cfg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ttl_val = isset($_POST['ttl_val']) ? (int)$_POST['ttl_val'] : 64;
    if ($ttl_val < 1 || $ttl_val > 255) $ttl_val = 64;

    if (isset($_POST['action']) && $_POST['action'] === 'toggle_boot') {
        $configFile = '/data/adb/php8/files/config/onboot.cfg';
        if (!file_exists($configFile)) {
            file_put_contents($configFile, "ttl=0\n");
        }
        $content = file_get_contents($configFile);
        if (strpos($content, 'ttl=1') !== false) {
            $newContent = str_replace('ttl=1', 'ttl=0', $content);
        } elseif (strpos($content, 'ttl=0') !== false) {
            $newContent = str_replace('ttl=0', 'ttl=1', $content);
        } else {
            $newContent = $content . "\nttl=1";
        }
        file_put_contents($configFile, $newContent);
        chmod($configFile, 0666);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Config Updated']);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'apply_now') {
        shell_exec("su -c '/data/adb/php8/scripts/onboot/ttl.sh $ttl_val'");
        $message = "TTL $ttl_val Applied.";
    }

    if (isset($_POST['action']) && $_POST['action'] === 'reset_now') {
        shell_exec("su -c \"" . IPT . " -t mangle -F POSTROUTING 2>/dev/null\"");
        $message = "TTL Rules Cleared.";
    }
}

$cfgContent = file_exists($cfgFile) ? file_get_contents($cfgFile) : '';
$is_boot_enabled = (strpos($cfgContent, 'ttl=1') !== false);
$iptables_dump = shell_exec("su -c \"" . IPT . " -t mangle -S POSTROUTING\"");
$current_active_ttl = 'N/A';
$is_active = false;
if (preg_match('/--ttl-set (\d+)/', $iptables_dump, $matches)) {
    $current_active_ttl = $matches[1];
    $is_active = true;
}
$input_value = ($is_active && is_numeric($current_active_ttl)) ? $current_active_ttl : 64;
if (isset($_POST['ttl_val'])) $input_value = $_POST['ttl_val'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TTL Manager Pro</title>
    <style>
        :root {
            --primary: #B87333;
            --accent: rgba(184, 115, 51, 0.15);
            --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px);
            --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C;
            --text-sub: #7A5C43;
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
            --green: #32d74b;
            --red: #ff3b30;
            --yellow: #ffd60a;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2);
                --text-main: #FDF5E6;
                --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12);
                --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: 0; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", sans-serif; background: transparent !important; color: var(--text-main); padding: 20px; max-width: 1100px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 25px; }
        h1 { font-size: 1.2rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }

        /* Layout wrapper: side-by-side di desktop, stacked di mobile */
        .layout-wrapper {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .col-monitor { flex: 1.4; min-width: 0; }
        .col-control  { flex: 1;   min-width: 0; }

        @media (max-width: 768px) {
            .layout-wrapper { flex-direction: column; }
            .col-monitor, .col-control { width: 100%; flex: none; }
        }

        .card { background: var(--card-bg); backdrop-filter: var(--blur-val); padding: 24px; border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 20px; }
        .alert { background: var(--accent); color: var(--primary); padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-size: 0.8rem; border: 1px solid var(--primary); font-weight: 700; }
        .stat-box { display: flex; flex-direction: column; align-items: center; padding: 18px; background: rgba(0,0,0,0.05); border-radius: 18px; border: 1px solid var(--border); }
        .big-stat { font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; }
        .c-on { color: var(--green); } .c-off { color: var(--text-sub); }
        .grp { margin: 20px 0; }
        label { display: block; margin-bottom: 10px; font-size: 0.7rem; font-weight: 800; color: var(--text-sub); text-transform: uppercase; }
        .inp { width: 100%; padding: 14px; border-radius: 14px; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: var(--text-main); font-size: 1.2rem; font-weight: 800; text-align: center; }
        .sw-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; margin-bottom: 15px; }
        .sw { position: relative; width: 50px; height: 28px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.1); transition: .4s; border-radius: 34px; border: 1px solid var(--border); }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: #fff; transition: .4s; border-radius: 50%; }
        input:checked + .sl { background-color: var(--primary); }
        input:checked + .sl:before { transform: translateX(22px); }
        .btn { width: 100%; padding: 16px; border: 1px solid var(--border); border-radius: 16px; font-weight: 800; cursor: pointer; transition: 0.3s; font-size: 0.85rem; text-transform: uppercase; margin-top: 10px; }
        .bp { background: var(--primary); color: #fff; }
        .bd { background: rgba(255, 59, 48, 0.1); color: #ff3b30; }
        #toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: var(--primary); color: #fff; padding: 12px 25px; border-radius: 30px; opacity: 0; transition: 0.3s; z-index: 100; }
        #toast.show { opacity: 1; bottom: 45px; }

        /* ===== MONITOR STYLES ===== */
        .monitor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .monitor-title {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-sub);
        }
        .pulse-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--green);
            animation: pulse 1.5s infinite;
            display: inline-block;
            margin-right: 6px;
        }
        .pulse-dot.inactive { background: var(--text-sub); animation: none; }
        @keyframes pulse {
            0%   { box-shadow: 0 0 0 0 rgba(50, 215, 75, 0.5); }
            70%  { box-shadow: 0 0 0 6px rgba(50, 215, 75, 0); }
            100% { box-shadow: 0 0 0 0 rgba(50, 215, 75, 0); }
        }
        .last-check {
            font-size: 0.65rem;
            color: var(--text-sub);
            opacity: 0.7;
        }
        .monitor-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }
        .mon-stat {
            background: rgba(0,0,0,0.06);
            border-radius: 14px;
            padding: 14px;
            border: 1px solid var(--border);
            text-align: center;
        }
        .mon-stat-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-sub);
            margin-bottom: 6px;
        }
        .mon-stat-val {
            font-size: 1.1rem;
            font-weight: 800;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .badge-green { background: rgba(50,215,75,0.15); color: var(--green); border: 1px solid rgba(50,215,75,0.3); }
        .badge-red   { background: rgba(255,59,48,0.12); color: var(--red); border: 1px solid rgba(255,59,48,0.25); }
        .badge-yellow{ background: rgba(255,214,10,0.15); color: #cc9900; border: 1px solid rgba(255,214,10,0.3); }
        .rules-box {
            background: rgba(0,0,0,0.06);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .rules-box-header {
            padding: 10px 14px;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text-sub);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .rules-list {
            padding: 10px 14px;
            font-family: "SF Mono", "Fira Code", monospace;
            font-size: 0.65rem;
            line-height: 1.7;
            color: var(--text-main);
            word-break: break-all;
            max-height: 100px;
            overflow-y: auto;
        }
        .rules-list.empty { color: var(--text-sub); font-style: italic; font-family: inherit; }
        .history-bar {
            display: flex;
            gap: 3px;
            align-items: flex-end;
            height: 36px;
            margin-top: 14px;
        }
        .history-bar span {
            flex: 1;
            border-radius: 3px;
            transition: height 0.3s;
            min-height: 4px;
        }
        .bar-on  { background: var(--green); opacity: 0.7; }
        .bar-off { background: var(--text-sub); opacity: 0.3; }
        .history-label {
            font-size: 0.55rem;
            color: var(--text-sub);
            text-align: center;
            margin-top: 4px;
            opacity: 0.7;
        }
        .v4v6-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        .proto-chip {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 8px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(0,0,0,0.04);
            gap: 4px;
        }
        .proto-label { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; color: var(--text-sub); }
        .proto-val   { font-size: 0.9rem; font-weight: 800; }
    </style>
</head>
<body>
    <header><h1>TTL Manager Pro</h1></header>
    <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="layout-wrapper">

    <!-- COL KIRI: MONITOR -->
    <div class="col-monitor">
    <!-- REALTIME MONITOR CARD -->
    <div class="card" id="monitorCard">
        <div class="monitor-header">
            <span class="monitor-title">
                <span class="pulse-dot" id="pulseDot"></span>
                Live Monitor
            </span>
            <span class="last-check" id="lastCheck">Checking...</span>
        </div>

        <div class="monitor-grid">
            <div class="mon-stat">
                <div class="mon-stat-label">Status</div>
                <div class="mon-stat-val" id="monStatus">—</div>
            </div>
            <div class="mon-stat">
                <div class="mon-stat-label">Active TTL</div>
                <div class="mon-stat-val" id="monTTL">—</div>
            </div>
            <div class="mon-stat">
                <div class="mon-stat-label">On Boot</div>
                <div class="mon-stat-val" id="monBoot">—</div>
            </div>
            <div class="mon-stat">
                <div class="mon-stat-label">Rules Count</div>
                <div class="mon-stat-val" id="monRules">—</div>
            </div>
        </div>

        <div class="v4v6-row">
            <div class="proto-chip">
                <span class="proto-label">IPv4 TTL</span>
                <span class="proto-val" id="monV4">—</span>
            </div>
            <div class="proto-chip">
                <span class="proto-label">IPv6 TTL</span>
                <span class="proto-val" id="monV6">—</span>
            </div>
        </div>

        <div class="rules-box" style="margin-top: 14px;">
            <div class="rules-box-header">
                <span>iptables rules (POSTROUTING)</span>
                <span id="rulesCount" style="font-size:0.6rem;">0 rules</span>
            </div>
            <div class="rules-list empty" id="rulesList">Loading...</div>
        </div>

        <div class="history-bar" id="historyBar">
            <?php for ($i = 0; $i < 20; $i++): ?>
            <span class="bar-off" style="height:8px;"></span>
            <?php endfor; ?>
        </div>
        <div class="history-label">Last 20 checks (3s interval)</div>
    </div>

    </div><!-- /.col-monitor -->

    <!-- COL KANAN: CONTROL -->
    <div class="col-control">
    <!-- CONTROL CARD -->
    <div class="card">
        <div class="stat-box">
            <span style="font-size:0.65rem; font-weight:800; opacity:0.6; margin-bottom:5px;">STATUS</span>
            <?php if ($is_active): ?>
            <span class="big-stat c-on">TTL <?= $current_active_ttl ?> ACTIVE</span>
            <?php else: ?>
            <span class="big-stat c-off">NOT CONFIGURED</span>
            <?php endif; ?>
        </div>
        <form method="POST" id="mainForm">
            <div class="grp">
                <label>Target TTL Value</label>
                <input type="number" name="ttl_val" id="ttlInput" class="inp" value="<?= $input_value ?>" min="1" max="255">
            </div>
            <div class="sw-row">
                <span>Apply on Boot</span>
                <label class="sw"><input type="checkbox" id="bt" <?= $is_boot_enabled ? 'checked' : '' ?>><span class="sl"></span></label>
            </div>
            <button type="submit" name="action" value="apply_now" class="btn bp">Apply Now</button>
            <button type="submit" name="action" value="reset_now" class="btn bd" onclick="return confirm('Clear?')">Clear Rules</button>
        </form>
    </div><!-- /.card control -->
    </div><!-- /.col-control -->

    </div><!-- /.layout-wrapper -->

    <div id="toast">Saved!</div>

    <script>
    const t = document.getElementById("toast");
    function msg(m) { t.innerText = m; t.className = "show"; setTimeout(() => t.className = "", 3000); }

    // ===== REALTIME MONITOR =====
    const history = [];
    const MAX_HIST = 20;
    const INTERVAL = 3000; // 3 detik

    function updateHistoryBar(isActive) {
        history.push(isActive);
        if (history.length > MAX_HIST) history.shift();
        const bar = document.getElementById('historyBar');
        const spans = bar.querySelectorAll('span');
        const padded = Array(MAX_HIST - history.length).fill(null).concat(history);
        spans.forEach((s, i) => {
            const val = padded[i];
            if (val === null) { s.className = 'bar-off'; s.style.height = '8px'; return; }
            s.className = val ? 'bar-on' : 'bar-off';
            s.style.height = val ? '28px' : '8px';
        });
    }

    function fetchStatus() {
        fetch('ttl_status.php?_=' + Date.now())
            .then(r => r.json())
            .then(d => {
                const isActive = d.status === 'active';
                const dot = document.getElementById('pulseDot');
                dot.className = 'pulse-dot' + (isActive ? '' : ' inactive');

                // Status badge
                document.getElementById('monStatus').innerHTML = isActive
                    ? '<span class="badge badge-green">Active</span>'
                    : '<span class="badge badge-red">Inactive</span>';

                // TTL value
                document.getElementById('monTTL').innerHTML = isActive
                    ? `<span style="color:var(--green)">${d.ttl}</span>`
                    : '<span style="color:var(--text-sub)">N/A</span>';

                // Boot
                document.getElementById('monBoot').innerHTML = d.boot_enabled
                    ? '<span class="badge badge-green">ON</span>'
                    : '<span class="badge badge-red">OFF</span>';

                // Rules count
                const rc = (d.rules_v4 ? d.rules_v4.length : 0);
                document.getElementById('monRules').textContent = rc;
                document.getElementById('rulesCount').textContent = rc + ' rule' + (rc !== 1 ? 's' : '');

                // IPv4 / IPv6
                document.getElementById('monV4').innerHTML = d.ttl !== null
                    ? `<span style="color:var(--green)">${d.ttl}</span>`
                    : `<span style="color:var(--text-sub)">—</span>`;
                document.getElementById('monV6').innerHTML = d.ttl_v6 !== null
                    ? `<span style="color:var(--green)">${d.ttl_v6}</span>`
                    : `<span style="color:var(--text-sub)">—</span>`;

                // Rules list
                const list = document.getElementById('rulesList');
                if (d.rules_v4 && d.rules_v4.length > 0) {
                    list.className = 'rules-list';
                    list.textContent = d.rules_v4.join('\n');
                } else {
                    list.className = 'rules-list empty';
                    list.textContent = 'No rules found in POSTROUTING';
                }

                // Timestamp
                document.getElementById('lastCheck').textContent = 'Updated: ' + d.timestamp;

                // History bar
                updateHistoryBar(isActive);
            })
            .catch(() => {
                document.getElementById('lastCheck').textContent = 'Error connecting...';
                document.getElementById('pulseDot').className = 'pulse-dot inactive';
                updateHistoryBar(false);
            });
    }

    fetchStatus();
    setInterval(fetchStatus, INTERVAL);

    // ===== CONTROLS =====
    document.getElementById('bt').addEventListener('change', function() {
        const state = this.checked;
        const ttl = document.getElementById('ttlInput').value;
        const fd = new FormData();
        fd.append('action', 'toggle_boot'); fd.append('state', state); fd.append('ttl_val', ttl);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            msg(d.message);
            if (d.status === 'error') this.checked = !state;
            setTimeout(fetchStatus, 500);
        });
    });

    document.querySelector('button[value="apply_now"]').addEventListener('click', function(e) {
        e.preventDefault();
        const ttl = document.getElementById('ttlInput').value;
        const fd = new FormData(document.getElementById('mainForm'));
        fd.append('action', 'apply_now'); fd.append('ttl_val', ttl);
        fetch('', { method: 'POST', body: fd }).then(() => {
            msg("TTL " + ttl + " Applied!");
            setTimeout(() => { fetchStatus(); location.reload(); }, 1000);
        });
    });

    document.querySelector('button[value="reset_now"]').addEventListener('click', function(e) {
        if (!confirm('Clear?')) { e.preventDefault(); return; }
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'reset_now');
        fetch('', { method: 'POST', body: fd }).then(() => {
            msg("Rules Cleared!");
            setTimeout(() => { fetchStatus(); location.reload(); }, 1000);
        });
    });
    </script>
</body>
</html>