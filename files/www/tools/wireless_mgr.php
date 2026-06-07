<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$p = $_SERVER['HTTP_HOST'];
$x = explode(':', $p);
$host = $x[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wireless Manager</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent !important;
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            max-width: 100%;
            padding: 12px;
        }
        .nav-bar {
            padding: 16px;
            display: flex;
            justify-content: center;
            background: transparent;
            flex-shrink: 0;
            z-index: 10;
        }
        .tabs { border: 1px solid var(--border); box-shadow: var(--shadow); }
        .tab { color: var(--text-sub); }
        .tab.active { border-color: var(--border); }
        .container {
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
            background: transparent !important;
        }
    </style>
</head>
<body>

<div class="nav-bar">
    <div class="tabs">
        <div class="tab active" onclick="sw('hotspot')" id="b-hotspot">Hotspot</div>
        <div class="tab" onclick="sw('limiter')" id="b-limiter">Limiter</div>
    </div>
</div>

<div class="container">
    <div id="v-hotspot" class="view active"><iframe id="f-hotspot"></iframe></div>
    <div id="v-limiter" class="view"><iframe id="f-limiter"></iframe></div>
</div>

<script>
    const uHost = '/tools/wireless/hotspot.php';
    const uLimit = '/tools/wireless/limiter';

    function sw(t) {
        document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(e => e.classList.remove('active'));
        document.getElementById('v-' + t).classList.add('active');
        document.getElementById('b-' + t).classList.add('active');
        const f = document.getElementById('f-' + t);
        if (!f.getAttribute('src')) f.src = (t === 'hotspot') ? uHost : uLimit;
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('f-hotspot').src = uHost;
    });
</script>

<script src="/assets/js/main.js"></script>
</body>
</html>