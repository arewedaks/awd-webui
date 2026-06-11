<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
function getDescText($f) {
    $p = __DIR__ . '/codes/' . $f;
    if(!file_exists($p)) return "File $f not found.";
    return file_get_contents($p);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>About Project & Developer</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root {
            --code-bg: rgba(30, 18, 10, 0.4); --code-tx: #FDF5E6;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --code-bg: rgba(0, 0, 0, 0.4); --code-tx: #E8D3C3;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
            background: transparent !important; color: var(--text-main); 
            padding: 20px; max-width: 900px; margin: 0 auto; line-height: 1.5; -webkit-font-smoothing: antialiased; 
        }
        
        .head { text-align: center; margin-bottom: 25px; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); padding-bottom: 20px; }
        h1 { font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
        p { font-size: 0.85rem; color: var(--text-sub); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .box { 
            background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border: 1px solid var(--border); border-radius: 20px; padding: 20px 15px; cursor: pointer; 
            display: flex; flex-direction: column; align-items: center; gap: 12px; box-shadow: var(--shadow); 
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); text-align: center; position: relative; overflow: hidden;
        }
        .box::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .box.active { background: var(--accent); border-color: var(--primary); }
        .box:hover { background: var(--accent); border-color: var(--primary); }
        .box:active { transform: scale(0.96); }
        .box h4 { font-size: 0.75rem; font-weight: 800; margin: 0; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; }
        .box svg { width: 28px; height: 28px; fill: var(--primary); transition: 0.3s; }

        .item { 
            display: none; background: var(--card-bg); backdrop-filter: var(--blur-val); -webkit-backdrop-filter: var(--blur-val);
            border: 1px solid var(--border); padding: 25px; border-radius: 24px; margin-bottom: 20px; 
            box-shadow: var(--shadow); animation: slideUp 0.4s ease-out; position: relative; overflow: hidden;
        }
        .item::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-shadow: inset 0 2px 5px rgba(255,255,255,0.15); pointer-events: none; }
        .item.show { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        
        .ti { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-sub); font-weight: 800; border-bottom: 1px dashed rgba(122, 92, 67, 0.2); padding-bottom: 10px; margin-bottom: 15px; }
        h3 { font-size: 1.1rem; font-weight: 800; margin: 0 0 15px; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .p-text { color: var(--text-main); font-size: 0.9rem; text-transform: none; line-height: 1.6; font-weight: 500; letter-spacing: 0; }
        .p-text strong { color: var(--primary); font-weight: 700; }

        .links { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 25px; }
        .lnk { 
            display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: rgba(255, 255, 255, 0.05); 
            border-radius: 16px; border: 1px solid var(--border); text-decoration: none; color: var(--text-main); 
            font-weight: 800; font-size: 0.85rem; transition: 0.3s; position: relative; overflow: hidden;
        }
        .lnk:hover { border-color: var(--primary); background: var(--accent); color: var(--primary); }
        .lnk:active { transform: scale(0.98); }
        .lnk svg { width: 22px; height: 22px; fill: var(--primary); }
        .lnk p { margin: 0; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .lnk h6 { margin: 0; font-size: 0.65rem; color: var(--text-sub); font-weight: 700; margin-left: auto; text-transform: uppercase; letter-spacing: 0.5px; background: rgba(0,0,0,0.05); padding: 4px 8px; border-radius: 8px; border: 1px solid var(--border); }

        .cat-title { font-size: 0.75rem; color: var(--text-sub); text-transform: uppercase; font-weight: 800; margin: 25px 0 10px; display: block; letter-spacing: 1px; }
    </style>
</head>
<body>

<div class="head">
    <h1>Tentang Kami</h1>
    <p>AWD-WebUI & AreweDaks</p>
</div>

<div class="grid">
    <div class="box active" id="b1" onclick="tg('c1', 'b1')">
        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        <h4>AreweDaks</h4>
    </div>
    <div class="box" id="b2" onclick="tg('c2', 'b2')">
        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        <h4>Info Proyek</h4>
    </div>
    <div class="box" id="b3" onclick="tg('c3', 'b3')">
        <svg viewBox="0 0 24 24"><path d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
        <h4>Instalasi</h4>
    </div>
    <div class="box" id="b4" onclick="tg('c4', 'b4')">
        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM4 18V6h16v12H4zM6 9l4 3-4 3V9zm6 5h6v2h-6v-2z"/></svg>
        <h4>Resources</h4>
    </div>
</div>

<div id="c1" class="item show">
    <div class="ti">Profil Developer</div>
    <h3>AreweDaks</h3>
    <p class="p-text">
        <?= getDescText('developer.txt') ?>
    </p>
</div>

<div id="c2" class="item">
    <div class="ti">Tentang AWD-WebUI</div>
    <h3>Cara Kerja Proyek</h3>
    <p class="p-text">
        <?= getDescText('project.txt') ?>
    </p>
</div>

<div id="c3" class="item">
    <div class="ti">Panduan Instalasi</div>
    <h3>Cara Pemasangan</h3>
    <p class="p-text">
        <?= getDescText('install.txt') ?>
    </p>
</div>

<div id="c4" class="item">
    <div class="ti">Resources</div>
    
    <div class="cat-title">Termux Environment</div>
    <div class="links">
        <a href="https://f-droid.org/id/packages/com.termux/" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM4 18V6h16v12H4zM6 9l4 3-4 3V9zm6 5h6v2h-6v-2z"/></svg>
            <p>Termux</p><h6>Stable</h6>
        </a>
        <a href="https://f-droid.org/id/packages/com.termux.boot/" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM4 18V6h16v12H4zM6 9l4 3-4 3V9zm6 5h6v2h-6v-2z"/></svg>
            <p>Termux:Boot</p><h6>Addon</h6>
        </a>
    </div>

    <div class="cat-title">Magisk Framework</div>
    <div class="links">
        <a href="https://github.com/taamarin/box_for_magisk/releases" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18c-.21 0-.41-.06-.57-.18l-7.9-4.44A.991.991 0 0 1 3 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44A.996.996 0 0 1 12 2c.21 0 .41.06.57.18l7.9 4.44c.32.17.53.5.53.88v9zM12 4.15L6.04 7.5L12 10.85l5.96-3.35L12 4.15zM5 15.91l6 3.38v-6.71L5 9.21v6.7zm14 0v-6.7l-6 3.37v6.71l6-3.38z"/></svg>
            <p>Box For Root</p><h6>Core</h6>
        </a>
        <a href="https://github.com/taamarin/ClashforMagisk/releases" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5z"/></svg>
            <p>Clash For Magisk</p><h6>Net</h6>
        </a>
        <a href="https://github.com/Magisk-Modules-Alt-Repo/Magisk-Tailscaled/releases/" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5a2.5 2.5 0 1 0-5 0V5H4c-1.1 0-2 .9-2 2v3.8h1.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5c0-1.49 1.21-2.7 2.7-2.7s2.7 1.21 2.7 2.7V22H17c1.1 0 2-.9 2-2v-4h1.5a2.5 2.5 0 1 0 0-5z"/></svg>
            <p>Tailscale</p><h6>VPN</h6>
        </a>
    </div>

    <div class="cat-title">Root Utilities</div>
    <div class="links">
        <a href="https://github.com/LSPosed/LSPosed/releases" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5a2.5 2.5 0 1 0-5 0V5H4c-1.1 0-2 .9-2 2v3.8h1.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5c0-1.49 1.21-2.7 2.7-2.7s2.7 1.21 2.7 2.7V22H17c1.1 0 2-.9 2-2v-4h1.5a2.5 2.5 0 1 0 0-5z"/></svg>
            <p>LSPosed</p><h6>Root</h6>
        </a>
        <a href="https://github.com/XhyEax/SoftApHelper/releases" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85a.637.637 0 0 0-.83.22l-1.88 3.24a11.46 11.46 0 0 0-8.94 0L5.65 5.67a.643.643 0 0 0-.87-.2c-.28.18-.37.54-.22.83L6.4 9.48A10.78 10.78 0 0 0 1 18h22a10.78 10.78 0 0 0-5.4-8.52M7 15.25a1.25 1.25 0 1 1 0-2.5a1.25 1.25 0 0 1 0 2.5m10 0a1.25 1.25 0 1 1 0-2.5a1.25 1.25 0 0 1 0 2.5"/></svg>
            <p>SoftApHelper</p><h6>Apk</h6>
        </a>
    </div>

    <div class="cat-title">Support Channels</div>
    <div class="links">
        <a href="https://t.me/+_IyXS4aBNeE5OGM1" class="lnk" target="_blank"><svg viewBox="0 0 24 24"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.48-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/></svg> <p>Telegram</p></a>
        <a href="https://shopee.co.id/bstrongshop" class="lnk" target="_blank"><svg viewBox="0 0 24 24"><path d="M12 0C8.86 0 6.67 2.21 6.31 4.84H2.25v18.75h19.5V4.84h-4.06C17.34 2.21 15.14 0 12 0zm0 2.17c2.07 0 3.32 1.65 3.48 2.68H8.53c.16-1.03 1.41-2.68 3.47-2.68z"/></svg> <p>Shopee</p></a>
    </div>
</div>

<script>
function tg(c_id, b_id) {
    document.querySelectorAll('.item').forEach(e => { if(e.id) e.classList.remove('show') });
    document.querySelectorAll('.box').forEach(e => { e.classList.remove('active') });
    
    const c = document.getElementById(c_id);
    if(c) c.classList.add('show');
    
    const b = document.getElementById(b_id);
    if(b) b.classList.add('active');
}
</script>

<script src="/assets/js/main.js"></script>
</body>
</html>