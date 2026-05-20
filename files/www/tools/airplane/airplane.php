<?php
$p = $_SERVER['HTTP_HOST'];
$x = explode(':', $p);
$host = $x[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Network Manager</title>
    <style>
        :root {
            --primary: #B87333; 
            --accent: rgba(184, 115, 51, 0.15);
            --border-glass: rgba(255, 255, 255, 0.4);
            --blur-val: blur(5px);
            --card-bg: rgba(255, 248, 240, 0.15);
            --text-main: #3E2A1C;
            --text-muted: #7A5C43;
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(10, 5, 2, 0.2);
                --text-main: #FDF5E6;
                --text-muted: #C0B2A2;
                --border-glass: rgba(255, 255, 255, 0.12);
                --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }
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
        }
        .nav-bar {
            padding: 16px;
            display: flex;
            justify-content: center;
            background: transparent;
            flex-shrink: 0;
            z-index: 10;
        }
        .tabs {
            background: var(--card-bg);
            backdrop-filter: var(--blur-val);
            -webkit-backdrop-filter: var(--blur-val);
            padding: 6px;
            border-radius: 20px;
            display: flex;
            gap: 6px;
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border-glass);
            box-shadow: var(--shadow);
        }
        .tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            border-radius: 14px;
            transition: 0.3s ease;
            user-select: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }
        .tab.active {
            background: var(--card-bg);
            color: var(--primary);
            border-color: var(--border-glass);
            box-shadow: inset 0 1px 1px rgba(255,255,255,0.2), 0 4px 10px rgba(0,0,0,0.1);
        }
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
        <div class="tab active" onclick="sw('iphunter')" id="b-iphunter">IP Hunter</div>
        <div class="tab" onclick="sw('modpes')" id="b-modpes">Airplane</div>
    </div>
</div>

<div class="container">
    <div id="v-iphunter" class="view active"><iframe id="f-iphunter"></iframe></div>
    <div id="v-modpes" class="view"><iframe id="f-modpes"></iframe></div>
</div>

<script>
    const uIpHunter = 'iphunter.php';
    const uModpes = 'modpes.php';

    function sw(t) {
        document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(e => e.classList.remove('active'));
        
        document.getElementById('v-' + t).classList.add('active');
        document.getElementById('b-' + t).classList.add('active');
        
        const f = document.getElementById('f-' + t);
        if (!f.getAttribute('src')) {
            f.src = (t === 'iphunter') ? uIpHunter : uModpes;
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('f-iphunter').src = uIpHunter;
    });
</script>

</body>
</html>