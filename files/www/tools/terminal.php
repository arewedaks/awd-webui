<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

function exec_root($cmd) {
    $cmd_escaped = str_replace("'", "'\\''", $cmd);
    return trim(shell_exec("su -c '$cmd_escaped' 2>&1"));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_key') {
    $key = $_POST['key'] ?? '';
    if (!empty($key)) {
        $key_escaped = escapeshellarg($key);
        $cmd = "/data/data/com.termux/files/usr/bin/tmux send-keys -t webui $key_escaped";
        exec_root($cmd);
    }
    exit;
}

$p = $_SERVER['HTTP_HOST'];
$x = explode(':', $p);
$host = $x[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Terminal</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            background: #000; color: #fff; height: 100vh; display: flex; flex-direction: column; overflow: hidden;
        }
        
        /* Iframe for TTYD */
        .terminal-container { flex-grow: 1; position: relative; width: 100%; height: 100%; }
        iframe { width: 100%; height: 100%; border: none; background: #000; display: block; }
        
        /* Extra Keyboard Bar */
        .extra-keys {
            display: flex; gap: 6px; padding: 6px 10px; background: #141414; border-top: 1px solid #2a2a2a;
            overflow-x: auto; white-space: nowrap; flex-shrink: 0;
            scrollbar-width: none; /* Firefox */
            padding-bottom: max(6px, env(safe-area-inset-bottom));
        }
        .extra-keys::-webkit-scrollbar { display: none; } /* Chrome */
        
        .ek-btn {
            background: rgba(255, 255, 255, 0.1); color: #e0e0e0; border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 8px; padding: 10px 14px;
            font-size: 0.85rem; font-weight: 700; cursor: pointer;
            transition: 0.2s; min-width: 45px; text-align: center; user-select: none;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .ek-btn:active { background: rgba(255, 255, 255, 0.2); transform: scale(0.95); }
        .ek-btn.active-modifier { background: #007aff; color: #fff; border-color: #007aff; box-shadow: 0 0 10px rgba(0,122,255,0.4); }
        
        /* Hidden input for capturing keys */
        #hidden-input { position: absolute; opacity: 0; top: -100px; width: 1px; height: 1px; border: none; }
    </style>
</head>
<body>

<div class="terminal-container">
    <iframe src="http://<?php echo $host; ?>:3001"></iframe>
</div>

<!-- Hidden input for catching modifier + key combos -->
<input type="text" id="hidden-input" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">

<div class="extra-keys" id="keyboard">
    <button class="ek-btn" id="btn-esc" onclick="send('Escape')">ESC</button>
    <button class="ek-btn" id="btn-ctrl" onclick="toggleMod('C')">CTRL</button>
    <button class="ek-btn" id="btn-alt" onclick="toggleMod('M')">ALT</button>
    <button class="ek-btn" onclick="send('Tab')">TAB</button>
    <button class="ek-btn" onclick="send('-')">-</button>
    <button class="ek-btn" onclick="send('/')">/</button>
    <button class="ek-btn" onclick="send('Up')">▲</button>
    <button class="ek-btn" onclick="send('Down')">▼</button>
    <button class="ek-btn" onclick="send('Left')">◀</button>
    <button class="ek-btn" onclick="send('Right')">▶</button>
    <button class="ek-btn" onclick="send('Home')">HOME</button>
    <button class="ek-btn" onclick="send('End')">END</button>
    <button class="ek-btn" onclick="send('PageUp')">PGUP</button>
    <button class="ek-btn" onclick="send('PageDown')">PGDN</button>
</div>

<script>
    let activeModifier = null;
    const hiddenInput = document.getElementById('hidden-input');
    const btnCtrl = document.getElementById('btn-ctrl');
    const btnAlt = document.getElementById('btn-alt');

    function toggleMod(mod) {
        if (activeModifier === mod) {
            // Disable
            activeModifier = null;
            updateUI();
            hiddenInput.blur();
        } else {
            // Enable
            activeModifier = mod;
            updateUI();
            hiddenInput.value = '';
            hiddenInput.focus();
        }
    }

    function updateUI() {
        btnCtrl.classList.toggle('active-modifier', activeModifier === 'C');
        btnAlt.classList.toggle('active-modifier', activeModifier === 'M');
    }

    hiddenInput.addEventListener('input', function(e) {
        if (!activeModifier) return;
        
        let char = hiddenInput.value;
        if (char.length > 0) {
            char = char.charAt(char.length - 1);
            
            // Format for tmux (e.g. C-c, M-x)
            let keySequence = activeModifier + '-' + char.toLowerCase();
            send(keySequence);
            
            // Reset
            hiddenInput.value = '';
            activeModifier = null;
            updateUI();
            hiddenInput.blur();
        }
    });
    
    hiddenInput.addEventListener('blur', function() {
        // If they click away (e.g., tap iframe), deactivate modifier
        setTimeout(() => {
            if(document.activeElement !== hiddenInput && activeModifier !== null) {
                activeModifier = null;
                updateUI();
            }
        }, 150);
    });

    function send(key) {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send("action=send_key&key=" + encodeURIComponent(key));
    }
</script>

</body>
</html>
