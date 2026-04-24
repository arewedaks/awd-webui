<?php
session_start();

// Load credentials
$credentials = include 'credentials.php';
$stored_username = $credentials['username'];
$stored_hashed_password = $credentials['hashed_password'];

$config_file = __DIR__ . '/config.json';
if (!file_exists($config_file)) {
    die('Error: Configuration file not found.');
}
$config = json_decode(file_get_contents($config_file), true);

define('LOGIN_ENABLED', $config['LOGIN_ENABLED']);

if (!LOGIN_ENABLED) {
    $_SESSION['login_disabled'] = true;
}

// Remember Me functionality
if (isset($_COOKIE['remember_me']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_COOKIE['remember_me'];
    $_SESSION['username'] = $stored_username;
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === $stored_username && password_verify($password, $stored_hashed_password)) {
        $_SESSION['user_id'] = session_id();
        $_SESSION['username'] = $username;

        header("Location: /");
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#E6D7C3">
    <link rel="icon" href="../webui/assets/luci.ico" type="image/x-icon">
    <title>RameeShop Login</title>
    <style>
        /* --- CSS VARIABLES (CHOCOLATE FALLING LEAVES VISIONOS THEME) --- */
        :root {
            /* LIGHT MODE */
            --bg-gradient: radial-gradient(circle at center, #FAF0E6 0%, #E6D7C3 100%);
            --card-bg: rgba(255, 248, 240, 0.45);
            --blur: blur(28px) saturate(160%);
            --text-main: #3E2A1C;
            --text-muted: #665A51;
            --input-bg: rgba(62, 42, 28, 0.08); 
            --input-border: rgba(255, 255, 255, 0.3);
            --btn-bg: #4B3621; 
            --btn-text: #FFFFFF;
            --btn-hover: #5D432D;
            --primary-accent: #C19A6B;
            --shadow: 0 8px 32px rgba(62, 42, 28, 0.1);
            --border-width: 1.5px;
            --error-bg: rgba(255, 59, 48, 0.1);
            --error-text: #ff3b30;
        }

        /* DARK MODE */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-gradient: radial-gradient(circle at center, #1E120A 0%, #080502 100%);
                --card-bg: rgba(30, 18, 10, 0.4);
                --blur: blur(28px) saturate(160%);
                --text-main: #FDF5E6;
                --text-muted: #AAA399;
                --input-bg: rgba(253, 245, 230, 0.1); 
                --input-border: rgba(255, 255, 255, 0.15); 
                --btn-bg: rgba(253, 245, 230, 0.2);
                --btn-text: #FDF5E6;
                --btn-hover: rgba(253, 245, 230, 0.35);
                --primary-accent: #C19A6B; 
                --shadow: 0 10px 32px rgba(0, 0, 0, 0.6);
                --error-bg: rgba(255, 69, 58, 0.15);
                --error-text: #ff453a;
            }
        }

        /* --- RESET BASE --- */
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Segoe UI", Roboto, sans-serif;
            background: var(--bg-gradient);
            width: 100vw;
            height: 100vh;
            overflow: hidden; /* Mencegah scroll layar */
            position: relative; 
        }

        /* --- CANVAS (LEAVES) --- */
        #leaves-canvas {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none; 
            z-index: 1; /* Di lapisan paling bawah */
        }

        /* --- WRAPPER (SOLUSI POSISI TENGAH) --- */
        .login-wrapper {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 10; /* Di atas canvas */
        }

        /* --- ANIMATIONS LOGIN --- */
        @keyframes fadeInUpApple {
            from { opacity: 0; transform: translateY(15px); scale: 0.98; }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* --- LOGIN CARD (GLASSMORPHISM) --- */
        .login-card {
            background: var(--card-bg);
            backdrop-filter: var(--blur);
            -webkit-backdrop-filter: var(--blur);
            width: 100%;
            max-width: 360px;
            padding: 2.5rem;
            border-radius: 28px;
            box-shadow: var(--shadow);
            border: var(--border-width) solid var(--input-border);
            animation: fadeInUpApple 0.8s cubic-bezier(0.2, 0.9, 0.3, 1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            border-radius: 28px;
            box-shadow: inset 0 2px 5px rgba(255,255,255,0.1);
            pointer-events: none;
        }

        /* --- BRANDING MINIMALIS CHOCOLATE --- */
        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-main);
            display: inline-block;
            letter-spacing: -0.8px;
        }

        .subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- FORM ELEMENTS --- */
        .input-group { margin-bottom: 1.2rem; position: relative; text-align: left; }
        
        .input-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px;
            fill: var(--text-muted); transition: 0.3s ease; pointer-events: none;
        }

        input {
            width: 100%;
            padding: 14px 14px 14px 48px;
            border: var(--border-width) solid var(--input-border);
            background-color: var(--input-bg);
            border-radius: 12px;
            font-size: 0.9rem;
            color: var(--text-main);
            transition: all 0.2s ease-in-out;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        input:focus {
            border-color: rgba(255, 255, 255, 0.3);
            background-color: rgba(255, 255, 255, 0.03); 
        }

        input:focus + .input-icon { fill: var(--text-main); }

        /* --- CHECKBOX --- */
        .options { display: flex; align-items: center; margin-bottom: 1.5rem; justify-content: space-between; }
        .checkbox-wrapper { display: flex; align-items: center; cursor: pointer; user-select: none; }
        .checkbox-wrapper input { display: none; }
        
        .checkmark {
            width: 20px; height: 20px;
            border: 2px solid var(--input-border);
            border-radius: 6px;
            margin-right: 10px;
            position: relative;
            transition: 0.2s ease;
            background-color: var(--input-bg);
        }
        
        .checkbox-wrapper input:checked + .checkmark {
            background-color: var(--primary-accent); border-color: var(--primary-accent);
        }
        
        .checkmark::after {
            content: ''; position: absolute; left: 6px; top: 2px; width: 4px; height: 9px;
            border: solid white; border-width: 0 2px 2px 0;
            transform: rotate(45deg) scale(0); transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .checkbox-wrapper input:checked + .checkmark::after { transform: rotate(45deg) scale(1); }
        .label-text { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }

        /* --- BUTTON --- */
        .btn-login {
            width: 100%; padding: 15px;
            background-color: var(--btn-bg);
            color: var(--btn-text); 
            border: var(--border-width) solid var(--input-border);
            border-radius: 12px;
            font-size: 1rem; font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease-in-out;
            position: relative; overflow: hidden;
            letter-spacing: -0.2px;
            box-shadow: inset 0 1px 1px rgba(255,255,255,0.05);
        }

        .btn-login:hover { background-color: var(--btn-hover); transform: scale(1.01); }
        .btn-login:active { transform: scale(0.97); }

        /* --- ERROR --- */
        .error-msg {
            background-color: var(--error-bg);
            color: var(--error-text);
            padding: 12px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-top: 1.5rem;
            font-weight: 600;
            border: 1px solid rgba(255, 59, 48, 0.15);
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }
    </style>
</head>
<body>

    <canvas id="leaves-canvas"></canvas>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="brand-logo">RameeShop</div>
            <div class="subtitle">Access Your Dashboard</div>

            <form method="POST">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required autocomplete="off">
                    <svg class="input-icon" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>

                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <svg class="input-icon" viewBox="0 0 24 24">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                </div>

                <div class="options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" id="showPassword">
                        <span class="checkmark"></span>
                        <span class="label-text">Show Password</span>
                    </label>
                </div>

                <button type="submit" class="btn-login">
                    Sign In
                </button>

                <?php if (isset($error)): ?>
                    <div class="error-msg">
                        <svg style="width:14px; height:14px; display:inline-block; vertical-align:middle; margin-right:5px; fill:currentColor;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // LOGIKA INTERAKSI FORM
        const passwordInput = document.getElementById('password');
        const showPasswordCheckbox = document.getElementById('showPassword');

        showPasswordCheckbox.addEventListener('change', function() {
            passwordInput.type = this.checked ? 'text' : 'password';
        });

        // ==========================================
        // LOGIKA FALLING LEAVES (JAVASCRIPT CANVASS API)
        // ==========================================
        const canvas = document.getElementById('leaves-canvas');
        const ctx = canvas.getContext('2d');

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        const leavesCount = 50; 
        const leaves = [];
        const leafColors = ['#C19A6B', '#A47B5A', '#8B4513', '#D2B48C', '#6B4423'];

        class Leaf {
            constructor() {
                this.init();
            }

            init() {
                this.x = Math.random() * canvas.width; 
                this.y = Math.random() * canvas.height * -1 - 20; 
                this.size = Math.random() * 8 + 4; 
                this.speed = Math.random() * 1.5 + 0.5; 
                this.color = leafColors[Math.floor(Math.random() * leafColors.length)];
                this.rotation = Math.random() * Math.PI * 2; 
                this.rotationSpeed = Math.random() * 0.02 - 0.01; 
                this.swing = Math.random() * 1.5; 
                this.swingSpeed = Math.random() * 0.02;
                this.swingOffset = Math.random() * Math.PI * 2;
                this.opacity = Math.random() * 0.5 + 0.2; 
            }

            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.rotation);
                ctx.globalAlpha = this.opacity; 
                
                ctx.fillStyle = this.color;
                ctx.beginPath();
                ctx.moveTo(0, -this.size);
                ctx.bezierCurveTo(this.size/2, -this.size/2, this.size/2, this.size/2, 0, this.size);
                ctx.bezierCurveTo(-this.size/2, this.size/2, -this.size/2, -this.size/2, 0, -this.size);
                ctx.fill();
                
                ctx.restore();
            }

            update() {
                this.y += this.speed; 
                this.rotation += this.rotationSpeed; 
                this.x += Math.sin(this.swingOffset) * this.swing; 
                this.swingOffset += this.swingSpeed;

                if (this.y > canvas.height + 20) {
                    this.init();
                }
            }
        }

        function initLeaves() {
            for (let i = 0; i < leavesCount; i++) {
                leaves.push(new Leaf());
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height); 
            
            for (let i = 0; i < leaves.length; i++) {
                leaves[i].update();
                leaves[i].draw();
            }
            requestAnimationFrame(animate); 
        }

        initLeaves();
        animate();
    </script>

</body>
</html>
