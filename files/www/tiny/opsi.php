<?php
$p = $_SERVER['HTTP_HOST'];
$x = explode(':', $p);
$host = $x[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <style>
        /* --- CSS VARIABLES (TRANSPARENT GLASSMORPHISM) --- */
        :root {
            /* LIGHT MODE */
            --card-bg: rgba(255, 248, 240, 0.15); /* Sangat transparan */
            --blur: blur(5px);
            --text-main: #3E2A1C;
            --text-muted: #7A5C43;
            --border: rgba(255, 255, 255, 0.5);
            --primary: #B87333;
            
            /* Background area Tab Container */
            --tab-cont-bg: rgba(62, 42, 28, 0.08);
            
            --shadow: 0 10px 30px rgba(62, 42, 28, 0.1);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                /* DARK MODE */
                --card-bg: rgba(10, 5, 2, 0.2); /* Sangat transparan */
                --blur: blur(5px);
                --text-main: #FDF5E6;
                --text-muted: #C0B2A2;
                --border: rgba(255, 255, 255, 0.15);
                --primary: #C19A6B;
                
                --tab-cont-bg: rgba(253, 245, 230, 0.08);
                
                --shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: transparent; /* TRANSPARAN TOTAL */
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- GLASSMORPHISM NAVBAR --- */
        .nav-bar {
            background: var(--card-bg);
            backdrop-filter: var(--blur);
            -webkit-backdrop-filter: var(--blur);
            padding: 14px 16px;
            box-shadow: var(--shadow);
            z-index: 10;
            display: flex;
            justify-content: center;
            border-bottom: 1px solid var(--border);
            position: relative;
        }
        /* Highlight atas efek kaca pada navbar */
        .nav-bar::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none;
        }

        /* --- GLASSMORPHISM TABS --- */
        .tabs {
            background: var(--tab-cont-bg);
            padding: 6px;
            border-radius: 18px; /* Lebih membulat */
            display: flex;
            gap: 8px;
            width: 100%;
            max-width: 400px; /* Sedikit dilebarkan */
            border: 1px solid var(--border);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
            position: relative;
            z-index: 2;
        }
        .tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            border-radius: 14px;
            transition: 0.3s ease;
            user-select: none;
            border: 1px solid transparent;
        }
        .tab:hover {
            color: var(--primary);
        }
        .tab.active {
            background: var(--card-bg);
            color: var(--primary);
            border-color: var(--border);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1), inset 0 1px 1px rgba(255,255,255,0.2);
        }

        /* --- CONTAINER & IFRAME --- */
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
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background-color: transparent; /* TRANSPARAN TOTAL AGAR DAUN TERLIHAT DI DALAM IFRAME */
        }
    </style>
</head>
<body>

<div class="nav-bar">
    <div class="tabs">
        <div class="tab active" onclick="sw('root')" id="b-root">System (Root)</div>
        <div class="tab" onclick="sw('storage')" id="b-storage">Internal Storage</div>
    </div>
</div>

<div class="container">
    <div id="v-root" class="view active">
        <iframe id="f-root"></iframe>
    </div>
    <div id="v-storage" class="view">
        <iframe id="f-storage"></iframe>
    </div>
</div>

<script>
    const uRoot = 'index.php';
    const uStor = 'http://<?php echo $host; ?>/tiny/index.php?p=sdcard';

    function sw(t) {
        document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(e => e.classList.remove('active'));
        
        document.getElementById('v-' + t).classList.add('active');
        document.getElementById('b-' + t).classList.add('active');

        const f = document.getElementById('f-' + t);
        if (!f.getAttribute('src')) {
            f.src = (t === 'root') ? uRoot : uStor;
        }
    }
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('f-root').src = uRoot;
    });
</script>

</body>
</html>
