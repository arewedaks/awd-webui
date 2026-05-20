<?php
$fileTab1 = "/tools/StartUp/Services.php"; 
$fileTab2 = "/tools/StartUp/ModuleEnabler.php";   
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>StartUp Manager</title>
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
        .head {
            padding: 20px;
            display: flex;
            justify-content: center;
            background: transparent;
            flex-shrink: 0;
            z-index: 10;
        }
        .nav-box {
            background: var(--card-bg);
            backdrop-filter: var(--blur-val);
            -webkit-backdrop-filter: var(--blur-val);
            padding: 6px;
            border-radius: 24px;
            display: flex;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-glass);
        }
        .tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--text-muted);
            border-radius: 18px;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid transparent;
        }
        .tab.active {
            color: white;
            background: var(--primary);
            border-color: var(--border-glass);
            box-shadow: 0 4px 12px rgba(184, 115, 51, 0.3);
            transform: scale(1.02);
        }
        .main {
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
            animation: slideUp 0.4s ease-out;
        }
        @keyframes slideUp { 
            from { opacity: 0; transform: translateY(10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent !important;
        }
    </style>
</head>
<body>

<div class="head">
    <div class="nav-box">
        <div class="tab active" onclick="sw('t1')" id="b-t1">Services</div>
        <div class="tab" onclick="sw('t2')" id="b-t2">Fix Module</div>
    </div>
</div>

<div class="main">
    <div id="v-t1" class="view active">
        <iframe src="<?php echo $fileTab1; ?>"></iframe>
    </div>
    <div id="v-t2" class="view">
        <iframe src="<?php echo $fileTab2; ?>"></iframe>
    </div>
</div>

<script>
    function sw(t) {
        document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(e => e.classList.remove('active'));
        document.getElementById('v-' + t).classList.add('active');
        document.getElementById('b-' + t).classList.add('active');
    }
</script>

</body>
</html>