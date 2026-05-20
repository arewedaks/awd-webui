<?php
function getSystemData($command, $default = 'N/A') {
    $output = @shell_exec($command);
    return trim($output) !== '' ? trim($output) : $default;
}

$signalInfo = getSystemData('dumpsys telephony.registry');
$operatorRaw = getSystemData('getprop gsm.sim.operator.alpha');
$simNames = explode(',', $operatorRaw);
$signalData = [];

if (!empty($signalInfo)) {
    if (preg_match_all('/CellSignalStrengthLte:(.+?)(?=CellSignalStrength|$)/s', $signalInfo, $lteMatches)) {
        foreach ($lteMatches[1] as $index => $lteData) {
            if ($index > 1) break; 
            preg_match('/rssi\s*=\s*([-\d]+)/i', $lteData, $rssi);
            preg_match('/rsrp\s*=\s*([-\d]+)/i', $lteData, $rsrp);
            preg_match('/rsrq\s*=\s*([-\d]+)/i', $lteData, $rsrq);
            preg_match('/rssnr\s*=\s*([-\d]+)/i', $lteData, $rssnr);
            preg_match('/level\s*=\s*(\d)/i', $lteData, $level);
            $lvl = (int)($level[1] ?? 0);
            $val_rsrp = $rsrp[1] ?? 0;
            if ($lvl == 0 && abs($val_rsrp) > 140) continue;
            $v_rsrq = (isset($rsrq[1]) && abs($rsrq[1]) < 1000) ? $rsrq[1] : 'N/A';
            $v_sinr = (isset($rssnr[1]) && abs($rssnr[1]) < 1000) ? $rssnr[1] : 'N/A';
            $providerName = isset($simNames[$index]) && !empty(trim($simNames[$index])) ? trim($simNames[$index]) : "SIM " . ($index + 1);
            $signalData[] = [
                'provider' => $providerName, 'type' => 'LTE', 'rssi' => $rssi[1] ?? 'N/A',
                'rsrp' => $rsrp[1] ?? 'N/A', 'rsrq' => $v_rsrq, 'sinr' => $v_sinr, 'level' => $lvl,
            ];
        }
    }
}

if (empty($signalData) && !empty($operatorRaw)) {
    foreach($simNames as $nm) {
        if(empty(trim($nm))) continue;
        $signalData[] = [ 'provider' => $nm, 'type' => 'NO SIGNAL', 'rssi' => 'N/A', 'rsrp' => 'N/A', 'rsrq' => 'N/A', 'sinr' => 'N/A', 'level' => 0 ];
    }
}

function getSignalColor($val, $type) {
    if ($val === 'N/A') return 'var(--text-sub)';
    $v = (int)$val;
    if ($type == 'rsrp') {
        if ($v > -95) return '#32d74b'; 
        if ($v > -105) return 'var(--primary)';
        return '#ff3b30';
    }
    if ($type == 'sinr') {
        if ($v >= 13) return '#32d74b';
        if ($v >= 0) return 'var(--primary)';
        return '#ff3b30';
    }
    return 'var(--primary)';
}

function getWidth($val, $type) {
    if ($val === 'N/A') return '0%';
    $v = (int)$val; $p = 0;
    if ($type == 'rsrp') $p = ($v + 140) * (100/100);
    elseif ($type == 'rssi') $p = ($v + 113) * (100/62);
    elseif ($type == 'sinr') $p = ($v + 10) * (100/40);
    elseif ($type == 'rsrq') $p = ($v + 20) * (100/17);
    return max(5, min(100, $p)) . '%';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Signal Monitor</title>
    <style>
        :root {
            --primary: #B87333; --accent: rgba(184, 115, 51, 0.15); --border: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px); --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C; --text-sub: #7A5C43; --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2); --text-main: #FDF5E6; --text-sub: #C0B2A2;
                --border: rgba(255, 255, 255, 0.12); --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important; color: var(--text-main);
            padding: 20px; -webkit-font-smoothing: antialiased;
        }
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); padding-bottom: 15px; margin-bottom: 25px; }
        h1 { font-size: 1.3rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
        .upd { font-size: 0.75rem; color: var(--text-sub); font-family: 'SF Mono', monospace; font-weight: 700; background: var(--accent); padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border); }
        .sec { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 25px;
            position: relative; overflow: hidden;
        }
        .sec::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .sec-hd { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px dashed rgba(122, 92, 67, 0.15); }
        .prov { font-size: 1.1rem; font-weight: 800; color: var(--text-main); }
        .type-badge { font-size: 0.7rem; background: var(--primary); color: #fff; padding: 3px 8px; border-radius: 6px; font-weight: 900; margin-left: 10px; letter-spacing: 0.5px; }
        .bars { display: flex; align-items: flex-end; gap: 4px; height: 18px; }
        .b { width: 4px; border-radius: 2px; background-color: rgba(0,0,0,0.1); transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .b:nth-child(1) { height: 25%; } .b:nth-child(2) { height: 50%; } .b:nth-child(3) { height: 75%; } .b:nth-child(4) { height: 100%; }
        .b.on { background-color: var(--primary); box-shadow: 0 0 8px var(--primary); }
        .list { display: flex; flex-direction: column; padding: 22px; }
        .item { display: flex; flex-direction: column; padding: 12px 0; border-bottom: 1px dashed rgba(122, 92, 67, 0.1); }
        .item:last-child { border: none; padding-bottom: 0; }
        .item:first-child { padding-top: 0; }
        .info { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.85rem; font-weight: 700; }
        .lbl { color: var(--text-sub); text-transform: uppercase; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px; } 
        .val { font-family: 'SF Mono', monospace; font-size: 0.95rem; }
        .pb { height: 6px; background-color: rgba(0,0,0,0.08); border-radius: 10px; overflow: hidden; width: 100%; border: 1px solid var(--border); }
        .pf { height: 100%; border-radius: 10px; transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1); }
        .no-data { text-align: center; padding: 50px 20px; color: var(--text-sub); font-weight: 700; border: 2px dashed var(--border); border-radius: 24px; text-transform: uppercase; letter-spacing: 1px; }
        @media (min-width: 768px) {
            .list { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
            .item { border-bottom: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle"><path d="M2 20h.01M7 20v-4M12 20v-8M17 20V8M22 20V4"/></svg>
            Signal <span style="color:var(--primary)">Monitor</span>
        </h1>
        <div class="upd"><?= date('H:i:s') ?></div>
    </div>
    <?php if (empty($signalData)): ?>
        <div class="no-data">Searching for active SIM...</div>
    <?php else: ?>
        <?php foreach ($signalData as $sig): ?>
            <div class="sec">
                <div class="sec-hd">
                    <div style="display:flex; align-items:center;">
                        <span class="prov"><?= htmlspecialchars($sig['provider']) ?></span>
                        <span class="type-badge"><?= $sig['type'] ?></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="font-size:0.7rem; font-weight:900; color:var(--text-sub); text-transform:uppercase;">Level <?= $sig['level'] ?></span>
                        <div class="bars">
                            <?php for($i=1; $i<=4; $i++): ?>
                                <div class="b <?= $i <= $sig['level'] ? 'on' : '' ?>"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <div class="list">
                    <?php if($sig['rsrp'] !== 'N/A'): ?>
                    <div class="item">
                        <div class="info"><span class="lbl">RSRP (Strength)</span><span class="val" style="color:<?= getSignalColor($sig['rsrp'], 'rsrp') ?>"><?= $sig['rsrp'] ?> dBm</span></div>
                        <div class="pb"><div class="pf" style="width:<?= getWidth($sig['rsrp'], 'rsrp') ?>; background:<?= getSignalColor($sig['rsrp'], 'rsrp') ?>"></div></div>
                    </div>
                    <div class="item">
                        <div class="info"><span class="lbl">SINR (Quality)</span><span class="val" style="color:<?= getSignalColor($sig['sinr'], 'sinr') ?>"><?= $sig['sinr'] ?> dB</span></div>
                        <div class="pb"><div class="pf" style="width:<?= getWidth($sig['sinr'], 'sinr') ?>; background:<?= getSignalColor($sig['sinr'], 'sinr') ?>"></div></div>
                    </div>
                    <div class="item">
                        <div class="info"><span class="lbl">RSRQ</span><span class="val" style="color:<?= getSignalColor($sig['rsrq'], 'rsrq') ?>"><?= $sig['rsrq'] ?> dB</span></div>
                        <div class="pb"><div class="pf" style="width:<?= getWidth($sig['rsrq'], 'rsrq') ?>; background:<?= getSignalColor($sig['rsrq'], 'rsrq') ?>"></div></div>
                    </div>
                    <div class="item">
                        <div class="info"><span class="lbl">RSSI</span><span class="val" style="color:var(--text-main)"><?= $sig['rssi'] ?> dBm</span></div>
                        <div class="pb"><div class="pf" style="width:<?= getWidth($sig['rssi'], 'rssi') ?>; background:var(--text-sub)"></div></div>
                    </div>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align:center; color:var(--text-sub); padding:10px; font-weight:700;">OFFLINE / IDLE</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <script>setInterval(() => location.reload(), 3000);</script>
</body>
</html>