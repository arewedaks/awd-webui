<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$fileTab1 = "/tools/StartUp/Services.php"; 
$fileTab2 = "/tools/StartUp/ModuleEnabler.php";   
$fileTab3 = "/tools/StartUp/OnBootConfig.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>StartUp Manager</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            height: 100vh;
            margin: 0;
            padding: 12px;
            max-width: 100%;
            overflow: hidden;
        }
        .head { margin-bottom: 10px; flex-shrink: 0; }
        .main { flex-grow: 1; position: relative; width: 100%; height: 100%; }
        .view { display: none; width: 100%; height: 100%; }
        .view.active { display: block; animation: slideUp 0.4s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        iframe { width: 100%; height: 100%; border: none; background: transparent !important; }
    </style>
</head>
<body>

<div class="head">
    <div class="nav-box">
        <div class="tab active" onclick="sw('t1')" id="b-t1">Services</div>
        <div class="tab" onclick="sw('t2')" id="b-t2">Fix Module</div>
        <div class="tab" onclick="sw('t3')" id="b-t3">OnBoot Config</div>
    </div>
</div>

<div class="main">
    <div id="v-t1" class="view active">
        <iframe src="<?php echo $fileTab1; ?>"></iframe>
    </div>
    <div id="v-t2" class="view">
        <iframe src="<?php echo $fileTab2; ?>"></iframe>
    </div>
    <div id="v-t3" class="view">
        <iframe src="<?php echo $fileTab3; ?>"></iframe>
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

<script src="/assets/js/main.js"></script>
</body>
</html>