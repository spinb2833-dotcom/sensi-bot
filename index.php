<?php
// ============================================
// SENSI MODS - ULTIMATE LOGIN
// ============================================

// ── CONNECTION CHECK ──
$online = false;
$connected = @fsockopen('8.8.8.8', 53, $errno, $errstr, 2);
if ($connected) { $online = true; fclose($connected); }

if (!$online) {
    $connected = @fsockopen('1.1.1.1', 53, $errno, $errstr, 2);
    if ($connected) { $online = true; fclose($connected); }
}

if (!$online) {
    $connected = @fsockopen('google.com', 80, $errno, $errstr, 2);
    if ($connected) { $online = true; fclose($connected); }
}

session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === 'sensei' && $password === 'sensei') {
        $_SESSION['admin'] = true;
        $_SESSION['username'] = $username;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSI MODS · Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap');
        
        * { margin:0; padding:0; box-sizing:border-box; }
        
        body {
            font-family: 'Orbitron', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #00ffff 0%, #0066aa 30%, #003366 60%, #001133 80%, #000000 100%);
            background-size: 400% 400%;
            animation: gradientMove 20s ease-in-out infinite;
        }
        @keyframes gradientMove { 0%,100%{background-position:0% 50%;} 50%{background-position:100% 50%;} }
        
        /* ── RAIN BUBBLE EFFECT ── */
        .rain-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .rain-drop {
            position: absolute;
            bottom: 100%;
            width: 3px;
            height: 20px;
            background: linear-gradient(180deg, rgba(0,255,255,0.6), rgba(0,200,255,0.1));
            border-radius: 50%;
            animation: rainFall linear infinite;
            opacity: 0.6;
        }
        @keyframes rainFall {
            0% { transform: translateY(0) scale(1); opacity: 0.6; }
            100% { transform: translateY(110vh) scale(0.5); opacity: 0; }
        }
        
        .bubble {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, rgba(0,255,255,0.15), rgba(0,200,255,0.02));
            border: 1px solid rgba(0,255,255,0.05);
            animation: bubbleRise linear infinite;
            pointer-events: none;
        }
        @keyframes bubbleRise {
            0% { transform: translateY(0) scale(0.5) rotate(0deg); opacity: 0; }
            10% { opacity: 0.8; }
            90% { opacity: 0.8; }
            100% { transform: translateY(-110vh) scale(1.5) rotate(720deg); opacity: 0; }
        }
        
        /* ── GLOW ORBS ── */
        .glow-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(150px);
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 15s ease-in-out infinite;
        }
        .glow-orb-1 { width:600px; height:600px; background:rgba(0,255,255,0.12); top:-200px; right:-150px; }
        .glow-orb-2 { width:450px; height:450px; background:rgba(0,200,255,0.08); bottom:-120px; left:-60px; animation-delay:-5s; }
        .glow-orb-3 { width:350px; height:350px; background:rgba(0,100,255,0.05); top:50%; left:50%; transform:translate(-50%,-50%); animation-delay:-10s; }
        .glow-orb-4 { width:200px; height:200px; background:rgba(0,255,255,0.06); bottom:20%; right:10%; animation-delay:-7s; }
        @keyframes orbFloat { 0%,100%{transform:translate(0,0) scale(1);} 33%{transform:translate(60px,-40px) scale(1.1);} 66%{transform:translate(-30px,50px) scale(0.9);} }
        
        /* ── FLOATING PARTICLES ── */
        .float-particles {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .float-particle {
            position: absolute;
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: rgba(0,255,255,0.15);
            animation: floatUp linear infinite;
            will-change: transform;
        }
        @keyframes floatUp {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }
        
        /* ── SCANNER LINE ── */
        .scanner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, #00ffff, transparent);
            z-index: 999;
            animation: scannerMove 3s ease-in-out infinite;
            box-shadow: 0 0 30px rgba(0,255,255,0.3);
        }
        @keyframes scannerMove {
            0% { top: 0; opacity: 0.3; }
            50% { top: 100%; opacity: 1; }
            100% { top: 0; opacity: 0.3; }
        }
        
        /* ── GRID LINES ── */
        .grid-lines {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
            pointer-events: none;
            background: 
                repeating-linear-gradient(0deg, transparent, transparent 59px, rgba(0,255,255,0.03) 59px, rgba(0,255,255,0.03) 60px),
                repeating-linear-gradient(90deg, transparent, transparent 59px, rgba(0,255,255,0.03) 59px, rgba(0,255,255,0.03) 60px);
        }
        
        /* ── CORNER GLOWS ── */
        .corner-glow {
            position: fixed;
            width: 150px;
            height: 150px;
            z-index: 0;
            pointer-events: none;
            opacity: 0.3;
        }
        .corner-glow-tl { top: -50px; left: -50px; background: radial-gradient(circle at 0% 0%, rgba(0,255,255,0.1), transparent 70%); }
        .corner-glow-tr { top: -50px; right: -50px; background: radial-gradient(circle at 100% 0%, rgba(0,255,255,0.1), transparent 70%); }
        .corner-glow-bl { bottom: -50px; left: -50px; background: radial-gradient(circle at 0% 100%, rgba(0,255,255,0.1), transparent 70%); }
        .corner-glow-br { bottom: -50px; right: -50px; background: radial-gradient(circle at 100% 100%, rgba(0,255,255,0.1), transparent 70%); }
        
        /* ── LOGIN CARD ── */
        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(50px);
            -webkit-backdrop-filter: blur(50px);
            padding: 50px 45px 40px;
            width: 420px;
            max-width: 92vw;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(0,255,255,0.15);
            box-shadow: 0 30px 100px rgba(0,255,255,0.08), 0 0 80px rgba(0,255,255,0.02);
            border-radius: 20px;
            animation: cardIn 0.8s ease;
            transition: all 0.5s ease;
        }
        .card:hover {
            border-color: rgba(0,255,255,0.3);
            box-shadow: 0 30px 120px rgba(0,255,255,0.12), 0 0 100px rgba(0,255,255,0.03);
        }
        @keyframes cardIn { 0%{opacity:0;transform:scale(0.95) rotateX(-10deg) translateY(20px);} 100%{opacity:1;transform:scale(1) rotateX(0deg) translateY(0);} }
        
        .card::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 22px;
            padding: 2px;
            background: linear-gradient(135deg, #00ffff, #0066aa, #00ffff, #003366, #00ffff);
            background-size: 400% 400%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: borderGlow 8s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes borderGlow { 0%,100%{background-position:0% 50%;} 50%{background-position:100% 50%;} }
        
        /* ── CARD SHINE EFFECT ── */
        .card-shine {
            position: absolute;
            top: -50%;
            left: -60%;
            width: 60%;
            height: 200%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            transform: rotate(35deg);
            animation: shineMove 6s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes shineMove { 0%{left:-60%;} 50%{left:120%;} 100%{left:120%;} }
        
        .logo {
            text-align: center;
            font-size: 34px;
            font-weight: 900;
            letter-spacing: 12px;
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            background: linear-gradient(135deg, #00ffff, #0088cc, #00ffff, #00ccff);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientMove 4s ease-in-out infinite;
            position: relative;
        }
        .logo::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #00ffff, transparent);
            animation: logoLine 3s ease-in-out infinite;
        }
        @keyframes logoLine { 0%,100%{width:60px;opacity:0.3;} 50%{width:120px;opacity:1;} }
        
        .logo-sub {
            text-align: center;
            color: rgba(255,255,255,0.2);
            font-size: 9px;
            letter-spacing: 16px;
            text-transform: uppercase;
            font-weight: 400;
            margin-top: 12px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .warning {
            text-align: center;
            color: rgba(255,255,255,0.15);
            font-size: 8px;
            letter-spacing: 6px;
            margin: 25px 0 30px;
            text-transform: uppercase;
            font-family: 'Segoe UI', sans-serif;
            font-weight: 400;
        }
        .warning .icon { 
            color: #00ffff; 
            animation: iconPulse 2s ease-in-out infinite; 
            display: inline-block;
        }
        @keyframes iconPulse { 0%,100%{opacity:0.3;transform:scale(0.8);} 50%{opacity:1;transform:scale(1.2);} }
        
        .input-group { margin-bottom: 20px; position: relative; }
        .input-group label {
            display: block;
            color: rgba(255,255,255,0.2);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            font-family: 'Segoe UI', sans-serif;
            transition: all 0.3s ease;
        }
        .input-group:focus-within label {
            color: #00ffff;
        }
        
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrapper .icon {
            position: absolute;
            left: 16px;
            color: rgba(0,255,255,0.2);
            font-size: 15px;
            transition: all 0.4s ease;
            z-index: 2;
            pointer-events: none;
        }
        .input-wrapper:focus-within .icon {
            color: #00ffff;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 16px 18px 16px 50px;
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(0,255,255,0.08);
            color: #ffffff;
            font-size: 14px;
            transition: all 0.4s ease;
            outline: none;
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: 1px;
            border-radius: 12px;
        }
        .input-wrapper input::placeholder {
            color: rgba(255,255,255,0.08);
            letter-spacing: 3px;
            font-size: 12px;
        }
        .input-wrapper input:focus {
            border-color: #00ffff;
            background: rgba(0,0,0,0.6);
            box-shadow: 0 0 50px rgba(0,255,255,0.05), inset 0 0 30px rgba(0,255,255,0.02);
        }
        .input-wrapper input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px rgba(0,0,0,0.6) inset !important;
            -webkit-text-fill-color: #ffffff !important;
        }
        
        .btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #00ffff, #0066aa, #00ccff, #00ffff);
            background-size: 400% 400%;
            border: none;
            color: #000000;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 8px;
            cursor: pointer;
            transition: all 0.4s ease;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            font-family: 'Orbitron', sans-serif;
            animation: btnGradient 4s ease-in-out infinite;
            box-shadow: 0 10px 40px rgba(0,255,255,0.08);
        }
        @keyframes btnGradient { 0%,100%{background-position:0% 50%;} 50%{background-position:100% 50%;} }
        .btn:hover { 
            transform: scale(1.02); 
            box-shadow: 0 15px 60px rgba(0,255,255,0.2);
        }
        .btn:active { transform: scale(0.97); }
        
        .btn .shine {
            position: absolute;
            top: -50%;
            left: -100%;
            width: 60%;
            height: 200%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transform: rotate(25deg);
            animation: shineMove 4s ease-in-out infinite;
        }
        @keyframes shineMove { 0%{left:-100%;} 100%{left:150%;} }
        
        .error {
            color: #ff3355;
            text-align: center;
            margin-top: 16px;
            font-size: 11px;
            letter-spacing: 3px;
            font-weight: 600;
            font-family: 'Segoe UI', sans-serif;
            text-shadow: 0 0 30px rgba(255,51,85,0.1);
            animation: errorShake 0.5s ease;
        }
        @keyframes errorShake { 0%,100%{transform:translateX(0);} 25%{transform:translateX(-5px);} 75%{transform:translateX(5px);} }
        .error i { margin-right: 8px; }
        
        .footer {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid rgba(0,255,255,0.05);
        }
        .footer a {
            color: rgba(255,255,255,0.12);
            text-decoration: none;
            font-size: 9px;
            letter-spacing: 6px;
            text-transform: uppercase;
            transition: all 0.4s ease;
            font-family: 'Segoe UI', sans-serif;
            font-weight: 400;
        }
        .footer a:hover { color: #00ffff; }
        .footer a i { margin-right: 6px; }
        
        /* ── VERSION BADGE ── */
        .version-badge {
            position: fixed;
            bottom: 20px;
            right: 20px;
            color: rgba(255,255,255,0.04);
            font-size: 9px;
            letter-spacing: 4px;
            font-family: 'Orbitron', monospace;
            z-index: 999;
        }
        
        /* ── LOADING DOTS ── */
        .loading-dots {
            display: none;
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            color: #00ffff;
            font-size: 14px;
            letter-spacing: 6px;
            font-family: 'Orbitron', monospace;
        }
        .loading-dots.active { display: block; }
        .loading-dots span { animation: dotPulse 1.5s ease-in-out infinite; }
        .loading-dots span:nth-child(2) { animation-delay: 0.2s; }
        .loading-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes dotPulse { 0%,100%{opacity:0.2;} 50%{opacity:1;} }
        
        @media (max-width:480px) {
            .card { padding: 30px 20px 25px; }
            .logo { font-size: 24px; letter-spacing: 6px; }
            .logo-sub { letter-spacing: 10px; font-size: 8px; }
            .input-wrapper input { padding: 14px 16px 14px 44px; font-size: 13px; }
            .btn { font-size: 10px; letter-spacing: 5px; padding: 14px; }
            .footer { gap: 20px; }
            .footer a { letter-spacing: 4px; font-size: 8px; }
            .glow-orb-1 { width:300px; height:300px; }
            .glow-orb-2 { width:250px; height:250px; }
            .corner-glow { width:80px; height:80px; }
        }
    </style>
</head>
<body>
    
    <!-- ── SCANNER LINE ── -->
    <div class="scanner"></div>
    
    <!-- ── RAIN BUBBLE CONTAINER ── -->
    <div class="rain-container" id="rainContainer"></div>
    
    <!-- ── GLOW ORBS ── -->
    <div class="glow-orb glow-orb-1"></div>
    <div class="glow-orb glow-orb-2"></div>
    <div class="glow-orb glow-orb-3"></div>
    <div class="glow-orb glow-orb-4"></div>
    
    <!-- ── CORNER GLOWS ── -->
    <div class="corner-glow corner-glow-tl"></div>
    <div class="corner-glow corner-glow-tr"></div>
    <div class="corner-glow corner-glow-bl"></div>
    <div class="corner-glow corner-glow-br"></div>
    
    <!-- ── FLOATING PARTICLES ── -->
    <div class="float-particles" id="floatParticles"></div>
    
    <!-- ── GRID LINES ── -->
    <div class="grid-lines"></div>
    
    <!-- ── LOADING DOTS ── -->
    <div class="loading-dots" id="loadingDots">
        <span>●</span> <span>●</span> <span>●</span>
    </div>
    
    <!-- ── LOGIN CARD ── -->
    <div class="card">
        <div class="card-shine"></div>
        <div class="logo">SENSI MODS</div>
        <div class="logo-sub">Premium · Mods · Panel</div>
        <div class="warning"><span class="icon">⚡</span> &nbsp;restricted access&nbsp; <span class="icon">⚡</span></div>
        
        <form method="POST" id="loginForm">
            <div class="input-group">
                <label><i class="fas fa-user" style="margin-right:6px;"></i> Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user-circle icon"></i>
                    <input type="text" name="username" placeholder="Enter username" required autocomplete="username">
                </div>
            </div>
            <div class="input-group">
                <label><i class="fas fa-lock" style="margin-right:6px;"></i> Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-key icon"></i>
                    <input type="password" name="password" placeholder="Enter password" required autocomplete="current-password">
                </div>
            </div>
            <button type="submit" class="btn" id="loginBtn">
                Authenticate
                <span class="shine"></span>
            </button>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-times-circle"></i> <?= $error ?></div>
            <?php endif; ?>
        </form>
        
        <div class="footer">
            <a href="#"><i class="fab fa-telegram"></i> Telegram</a>
            <a href="#"><i class="fas fa-users"></i> Community</a>
            <a href="#"><i class="fas fa-question-circle"></i> Help</a>
        </div>
    </div>
    
    <!-- ── VERSION BADGE ── -->
    <div class="version-badge">v2.0 · SENSI MODS</div>
    
    <script>
        // ── RAIN BUBBLE EFFECT ──
        (function() {
            const container = document.getElementById('rainContainer');
            
            // Rain drops
            for (let i = 0; i < 120; i++) {
                const drop = document.createElement('div');
                drop.className = 'rain-drop';
                drop.style.left = Math.random() * 100 + '%';
                drop.style.height = (Math.random() * 15 + 8) + 'px';
                drop.style.width = (Math.random() * 2 + 1) + 'px';
                drop.style.animationDuration = (Math.random() * 1.5 + 0.8) + 's';
                drop.style.animationDelay = (Math.random() * 5) + 's';
                drop.style.opacity = Math.random() * 0.5 + 0.1;
                container.appendChild(drop);
            }
            
            // Bubbles
            for (let i = 0; i < 20; i++) {
                const bubble = document.createElement('div');
                bubble.className = 'bubble';
                const size = Math.random() * 35 + 10;
                bubble.style.width = size + 'px';
                bubble.style.height = size + 'px';
                bubble.style.left = Math.random() * 100 + '%';
                bubble.style.bottom = (Math.random() * 30) + '%';
                bubble.style.animationDuration = (Math.random() * 18 + 10) + 's';
                bubble.style.animationDelay = (Math.random() * 20) + 's';
                bubble.style.opacity = Math.random() * 0.3 + 0.05;
                container.appendChild(bubble);
            }
        })();
        
        // ── FLOATING PARTICLES ──
        (function() {
            const container = document.getElementById('floatParticles');
            for (let i = 0; i < 50; i++) {
                const dot = document.createElement('div');
                dot.className = 'float-particle';
                dot.style.left = Math.random() * 100 + '%';
                dot.style.width = (Math.random() * 3 + 1) + 'px';
                dot.style.height = dot.style.width;
                dot.style.animationDuration = (Math.random() * 22 + 15) + 's';
                dot.style.animationDelay = (Math.random() * 20) + 's';
                dot.style.opacity = Math.random() * 0.2 + 0.02;
                container.appendChild(dot);
            }
        })();
        
        // ── LOADING DOTS ON SUBMIT ──
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('loadingDots').classList.add('active');
            document.getElementById('loginBtn').disabled = true;
            document.getElementById('loginBtn').style.opacity = '0.7';
        });
        
        // ── KEYBOARD SHORTCUT ──
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form) form.submit();
            }
        });
        
        console.log('⚡ SENSI MODS · Login Page Loaded');
        console.log('🛡️ Security: Active');
        console.log('👑 Welcome back, Master.');
    </script>
    
</body>
</html>