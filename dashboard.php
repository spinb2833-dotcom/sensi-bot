<?php
// ============================================
// INTERNET CONNECTION CHECK
// ============================================
$online = false;
if (@fsockopen('8.8.8.8', 53, $errno, $errstr, 2)) { $online = true; }
if (!$online && @fsockopen('1.1.1.1', 53, $errno, $errstr, 2)) { $online = true; }
if (!$online && @fsockopen('google.com', 80, $errno, $errstr, 2)) { $online = true; }

$online_status = $online ? 'online' : 'offline';

if ($online_status === 'online') {
    date_default_timezone_set('UTC');
    require_once 'db.php';
    
    if (!isset($_SESSION['admin'])) {
        header('Location: index.php');
        exit;
    }
    
    try {
        $pdo = db();
    } catch (Exception $e) {
        $online_status = 'offline';
    }
}

// ── ONLY RUN DATABASE QUERIES IF ONLINE ──
if ($online_status === 'online' && isset($pdo)) {
    $user_timezone = 'Africa/Lagos';
    
    try {
        $user_tz = new DateTimeZone($user_timezone);
        $now_user = new DateTime('now', $user_tz);
        $active_keys = $pdo->query("SELECT id, license_key, expires_at FROM licenses WHERE status = 'active'")->fetchAll();
        foreach ($active_keys as $key) {
            try {
                $expiry_utc = new DateTime($key['expires_at'], new DateTimeZone('UTC'));
                $expiry_user = clone $expiry_utc;
                $expiry_user->setTimezone($user_tz);
                if ($now_user > $expiry_user) {
                    $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE id = ?")->execute([$key['id']]);
                }
            } catch (Exception $e) {
                if (strtotime($key['expires_at']) < time()) {
                    $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE id = ?")->execute([$key['id']]);
                }
            }
        }
        $now_utc = new DateTime('now', new DateTimeZone('UTC'));
        $now_utc_str = $now_utc->format('Y-m-d H:i:s');
        $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE status != 'expired' AND expires_at <= ?")->execute([$now_utc_str]);
    } catch (Exception $e) {
        $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE status != 'expired' AND expires_at <= NOW()")->execute();
    }
    
    $message = '';
    $error = '';
    $generated_key = '';
    $generated_expiry = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
        $count = min(20, max(1, (int)$_POST['count']));
        $expiry_value = (int)$_POST['expiry_value'];
        $expiry_unit = $_POST['expiry_unit'];
        $custom_key = trim($_POST['custom_key'] ?? '');
        $max_devices = (int)$_POST['max_devices'] ?? 1;
        $generated = [];
        for ($i = 0; $i < $count; $i++) {
            if (!empty($custom_key)) {
                $key = strtoupper($custom_key);
                $check = $pdo->prepare("SELECT id FROM licenses WHERE license_key = ?");
                $check->execute([$key]);
                if ($check->fetch()) {
                    $error = "Key '$key' already exists!";
                    break;
                }
            } else {
                $key = 'SENSI-' . strtoupper(substr(md5(uniqid() . mt_rand()), 0, 8));
            }
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $expiry = clone $now;
            $expiry->modify("+$expiry_value $expiry_unit");
            $expires_at = $expiry->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO licenses (license_key, expires_at, max_devices) VALUES (?, ?, ?)");
            $stmt->execute([$key, $expires_at, $max_devices]);
            $generated[] = $key;
            $generated_key = $key;
            $generated_expiry = $expires_at;
        }
        if (empty($error)) {
            $message = "Generated " . count($generated) . " key(s)!";
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?generated=1');
            exit;
        }
    }
    
    if (isset($_GET['generated']) && $_GET['generated'] == 1) {
        $message = "✅ Key(s) generated successfully!";
        echo '<script>history.replaceState({}, "", window.location.pathname);</script>';
    }
    
    try {
        $pdo->prepare("DELETE FROM licenses WHERE status = 'expired'")->execute();
    } catch (Exception $e) {}
    
    if (isset($_GET['toggle'])) {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT status FROM licenses WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        $new_status = ($current === 'active') ? 'disabled' : 'active';
        $pdo->prepare("UPDATE licenses SET status = ? WHERE id = ?")->execute([$new_status, $id]);
        header('Location: dashboard.php');
        exit;
    }
    
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['id'];
        $pdo->prepare("DELETE FROM licenses WHERE id = ?")->execute([$id]);
        header('Location: dashboard.php?deleted=1');
        exit;
    }
    
    $keys = $pdo->query("SELECT * FROM licenses WHERE status != 'expired' ORDER BY created_at DESC")->fetchAll();
    
    $first_key_expiry = null;
    foreach ($keys as &$key) {
        try {
            $expiry_utc = new DateTime($key['expires_at'], new DateTimeZone('UTC'));
            $user_tz = new DateTimeZone($user_timezone);
            $expiry_local = clone $expiry_utc;
            $expiry_local->setTimezone($user_tz);
            $key['expires_at_display'] = $expiry_local->format('g:i:s A');
            $now_user = new DateTime('now', $user_tz);
            $interval = $now_user->diff($expiry_local);
            $total_seconds = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
            if ($now_user > $expiry_local) {
                $key['time_remaining_seconds'] = 0;
                $pdo->prepare("DELETE FROM licenses WHERE id = ?")->execute([$key['id']]);
                $key['status'] = 'deleted';
            } else {
                $key['time_remaining_seconds'] = $total_seconds;
            }
            if ($first_key_expiry === null && $key['status'] == 'active') {
                $first_key_expiry = $expiry_utc;
            }
        } catch (Exception $e) {
            $key['expires_at_display'] = $key['expires_at'];
            $key['time_remaining_seconds'] = 0;
        }
    }
    
    $keys = array_filter($keys, function($key) {
        return $key['status'] !== 'deleted';
    });
    
    $total = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status != 'expired'")->fetchColumn();
    $active = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'active'")->fetchColumn();
    $expired = 0;
    $disabled = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'disabled'")->fetchColumn();
    $used = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'used'")->fetchColumn();
    
    $nigeria_now = new DateTime('now', new DateTimeZone('Africa/Lagos'));
    $nigeria_time = $nigeria_now->format('g:i:s A');
    
    $active_key_timer = null;
    foreach ($keys as $key) {
        if ($key['status'] == 'active' && $key['time_remaining_seconds'] > 0) {
            $active_key_timer = [
                'key' => $key['license_key'],
                'seconds' => $key['time_remaining_seconds'],
                'expires' => $key['expires_at_display']
            ];
            break;
        }
    }
    
    if (!empty($generated_key) && !empty($generated_expiry)) {
        try {
            $expiry_utc = new DateTime($generated_expiry, new DateTimeZone('UTC'));
            $user_tz = new DateTimeZone($user_timezone);
            $expiry_local = clone $expiry_utc;
            $expiry_local->setTimezone($user_tz);
            $now_user = new DateTime('now', $user_tz);
            $interval = $now_user->diff($expiry_local);
            $total_seconds = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
            if ($total_seconds > 0) {
                $active_key_timer = [
                    'key' => $generated_key,
                    'seconds' => $total_seconds,
                    'expires' => $expiry_local->format('g:i:s A')
                ];
            }
        } catch (Exception $e) {}
    }
} else {
    $total = 0;
    $active = 0;
    $disabled = 0;
    $used = 0;
    $keys = [];
    $first_key_expiry = null;
    $active_key_timer = null;
    $nigeria_time = date('g:i:s A');
    $user_timezone = 'Africa/Lagos';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>SENSI MODS · Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap');
        
        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        html, body { width:100%; overflow-x:hidden; font-family:'Segoe UI',sans-serif; min-height:100vh; 
            background: linear-gradient(135deg, #00ffff 0%, #0088cc 25%, #004466 50%, #001a33 75%, #000000 100%);
            background-size: 400% 400%;
            animation: gradientMove 18s ease-in-out infinite;
        }
        @keyframes gradientMove { 0%,100%{background-position:0% 50%;} 50%{background-position:100% 50%;} }
        
        /* ── OFFLINE OVERLAY ── */
        .offline-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.92);
            z-index: 99999;
            backdrop-filter: blur(20px);
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
            color: #00ffff;
            font-family: 'Orbitron', sans-serif;
            text-align: center;
            padding: 40px;
        }
        .offline-overlay.active { display: flex; }
        .offline-overlay .icon { font-size: 80px; animation: pulse 1.5s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{opacity:0.3;transform:scale(0.9);} 50%{opacity:1;transform:scale(1.1);} }
        .offline-overlay h2 { font-size: 28px; letter-spacing: 8px; color: #ff3355; }
        .offline-overlay .line { width: 80px; height: 2px; background: linear-gradient(90deg, transparent, #00ffff, transparent); animation: linePulse 2s ease-in-out infinite; }
        @keyframes linePulse { 0%,100%{opacity:0.2;} 50%{opacity:1;} }
        .offline-overlay p { font-size: 12px; color: rgba(0,255,255,0.3); letter-spacing: 4px; }
        .offline-overlay .sub { font-size: 8px; color: rgba(255,255,255,0.05); margin-top: 10px; }
        .offline-overlay .retry-btn { margin-top:20px;padding:12px 30px;background:rgba(0,255,255,0.1);border:1px solid rgba(0,255,255,0.2);color:#00ffff;border-radius:8px;cursor:pointer;font-family:'Orbitron',sans-serif;font-size:11px;letter-spacing:2px;transition:0.3s; }
        .offline-overlay .retry-btn:hover { background:rgba(0,255,255,0.2); }
        
        /* ── ELECTRIC LINES ── */
        .electric-lines { position:fixed; top:0; left:0; right:0; bottom:0; z-index:0; pointer-events:none; overflow:hidden; }
        .electric-line { position:absolute; height:2px; background:linear-gradient(90deg, transparent, rgba(0,255,255,0.5), rgba(0,200,255,0.7), rgba(0,255,255,0.5), transparent); background-size:300% 100%; animation:electricFlow linear infinite; border-radius:2px; box-shadow:0 0 30px rgba(0,255,255,0.1); }
        .electric-line.vertical { width:2px; height:100%; background:linear-gradient(180deg, transparent, rgba(0,255,255,0.5), rgba(0,200,255,0.7), rgba(0,255,255,0.5), transparent); background-size:100% 300%; animation:electricFlowV linear infinite; }
        @keyframes electricFlow { 0%{background-position:-300% 0;} 100%{background-position:300% 0;} }
        @keyframes electricFlowV { 0%{background-position:0 -300%;} 100%{background-position:0 300%;} }
        .electric-node { position:absolute; width:6px; height:6px; border-radius:50%; background:rgba(0,255,255,0.8); box-shadow:0 0 40px rgba(0,255,255,0.3); animation:nodePulse 2s ease-in-out infinite; }
        @keyframes nodePulse { 0%,100%{transform:scale(0.5);opacity:0.3;} 50%{transform:scale(1.5);opacity:1;} }
        
        .fire-storm { position:fixed; top:0; left:0; right:0; bottom:0; z-index:0; pointer-events:none; overflow:hidden; }
        .fire-particle { position:absolute; width:4px; height:4px; border-radius:50%; background:radial-gradient(circle,rgba(0,255,255,0.3),rgba(0,200,255,0.1)); animation:fireRise linear infinite; will-change:transform; }
        @keyframes fireRise { 0%{transform:translateY(100vh) scale(0) rotate(0deg);opacity:0;} 10%{opacity:1;} 90%{opacity:1;} 100%{transform:translateY(-10vh) scale(1) rotate(720deg);opacity:0;} }
        
        .glow-orb { position:fixed; border-radius:50%; filter:blur(120px); pointer-events:none; z-index:0; animation:orbFloat 15s ease-in-out infinite; }
        .glow-orb-1 { width:600px; height:600px; background:rgba(0,255,255,0.10); top:-200px; right:-150px; }
        .glow-orb-2 { width:500px; height:500px; background:rgba(0,200,255,0.08); bottom:-120px; left:-100px; animation-delay:-4s; }
        .glow-orb-3 { width:400px; height:400px; background:rgba(0,150,255,0.06); top:50%; left:50%; transform:translate(-50%,-50%); animation-delay:-8s; }
        .glow-orb-4 { width:300px; height:300px; background:rgba(0,255,255,0.04); top:20%; right:10%; animation-delay:-12s; }
        .glow-orb-5 { width:350px; height:350px; background:rgba(0,200,255,0.03); bottom:30%; left:5%; animation-delay:-16s; }
        @keyframes orbFloat { 0%,100%{transform:translate(0,0) scale(1);} 25%{transform:translate(-70px,50px) scale(1.1);} 50%{transform:translate(50px,-40px) scale(0.9);} 75%{transform:translate(-30px,60px) scale(1.05);} }
        
        .float-particles { position:fixed; top:0; left:0; right:0; bottom:0; z-index:0; pointer-events:none; overflow:hidden; }
        .float-particle { position:absolute; width:3px; height:3px; border-radius:50%; background:rgba(255,255,255,0.1); animation:floatUp linear infinite; will-change:transform; }
        @keyframes floatUp { 0%{transform:translateY(100vh) scale(0);opacity:0;} 10%{opacity:1;} 90%{opacity:1;} 100%{transform:translateY(-10vh) scale(1);opacity:0;} }
        
        .grid-lines { position:fixed; top:0; left:0; right:0; bottom:0; z-index:0; pointer-events:none; background:repeating-linear-gradient(0deg,transparent,transparent 59px,rgba(0,255,255,0.05) 59px,rgba(0,255,255,0.05) 60px), repeating-linear-gradient(90deg,transparent,transparent 59px,rgba(0,255,255,0.05) 59px,rgba(0,255,255,0.05) 60px); }
        
        .sparkle { position:absolute; width:8px; height:8px; border-radius:50%; background:radial-gradient(circle,rgba(0,255,255,0.9),rgba(0,200,255,0.2)); box-shadow:0 0 30px rgba(0,255,255,0.2),0 0 60px rgba(0,255,255,0.05); pointer-events:none; animation:sparkleBurst 2.5s ease-in-out infinite; will-change:transform; }
        .sparkle-lg { width:14px; height:14px; box-shadow:0 0 50px rgba(0,255,255,0.25),0 0 100px rgba(0,255,255,0.05); animation-duration:3.5s; }
        .sparkle-xl { width:20px; height:20px; box-shadow:0 0 70px rgba(0,255,255,0.3),0 0 150px rgba(0,255,255,0.05); animation-duration:4.5s; }
        @keyframes sparkleBurst { 0%,100%{opacity:0;transform:scale(0) rotate(0deg);} 50%{opacity:1;transform:scale(1) rotate(360deg);} }
        
        .shine-sweep { position:absolute; top:0; left:-100%; width:60%; height:100%; background:linear-gradient(90deg,transparent,rgba(0,255,255,0.05),rgba(0,200,255,0.02),transparent); transform:skewX(-30deg); animation:sweepMove 4s ease-in-out infinite; pointer-events:none; }
        @keyframes sweepMove { 0%{left:-100%;opacity:0;} 10%{opacity:1;} 90%{opacity:1;} 100%{left:200%;opacity:0;} }
        
        .fire-glow { position:absolute; top:-50%; left:-50%; width:200%; height:200%; background:radial-gradient(circle,rgba(0,255,255,0.05),rgba(0,200,255,0.02),transparent 70%); pointer-events:none; animation:fireGlowMove 10s ease-in-out infinite; }
        @keyframes fireGlowMove { 0%,100%{transform:translate(0,0) scale(1);} 25%{transform:translate(50px,-40px) scale(1.2);} 50%{transform:translate(-40px,50px) scale(0.8);} 75%{transform:translate(30px,30px) scale(1.1);} }
        
        .corner-fire { position:fixed; width:80px; height:80px; pointer-events:none; z-index:0; opacity:0.3; }
        .corner-fire-tl { top:0; left:0; background:radial-gradient(circle at 0% 0%, rgba(0,255,255,0.05), transparent 70%); }
        .corner-fire-tr { top:0; right:0; background:radial-gradient(circle at 100% 0%, rgba(0,255,255,0.05), transparent 70%); }
        .corner-fire-bl { bottom:0; left:0; background:radial-gradient(circle at 0% 100%, rgba(0,255,255,0.05), transparent 70%); }
        .corner-fire-br { bottom:0; right:0; background:radial-gradient(circle at 100% 100%, rgba(0,255,255,0.05), transparent 70%); }
        
        /* ── FLOATING CLOCK ── */
        .floating-clock {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 999;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,255,255,0.3);
            padding: 12px 20px;
            border-radius: 8px;
            backdrop-filter: blur(20px);
            box-shadow: 0 0 60px rgba(0,255,255,0.1);
            min-width: 200px;
            text-align: center;
            animation: clockPulse 4s ease-in-out infinite;
            transition: all 0.5s ease;
        }
        @keyframes clockPulse { 0%,100%{border-color:rgba(0,255,255,0.3);} 50%{border-color:rgba(0,255,255,0.5);} }
        .floating-clock .time { font-family: 'Orbitron', monospace; font-size: 24px; font-weight: 700; color: #000000; text-shadow: 0 0 30px rgba(0,255,255,0.1); letter-spacing: 2px; }
        .floating-clock .label { font-size: 8px; color: #444; letter-spacing: 4px; text-transform: uppercase; margin-top: 2px; font-weight: 600; }
        .floating-clock .label i { color: #0088cc; margin-right: 4px; }
        .floating-clock.timer-mode { border-color: rgba(0,255,255,0.6); animation: timerPulse 1s ease-in-out infinite; }
        @keyframes timerPulse { 0%,100%{border-color:rgba(0,255,255,0.6);} 50%{border-color:rgba(0,255,255,0.9);} }
        .floating-clock .timer-key { font-size: 9px; color: #0088cc; letter-spacing: 2px; margin-top: 2px; font-weight: 600; }
        
        /* ── HAMBURGER ── (On same line as title) ── */
        .hamburger {
            display: none;
            position: fixed;
            top: 18px;
            left: 15px;
            z-index: 1001;
            background: rgba(255,255,255,0.95);
            border: 1px solid rgba(0,255,255,0.3);
            border-radius: 10px;
            padding: 10px 12px;
            cursor: pointer;
            backdrop-filter: blur(20px);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            font-size: 20px;
            color: #000000;
            box-shadow: 0 10px 30px rgba(0,255,255,0.05);
        }
        .hamburger:hover { border-color: rgba(0,255,255,0.5); box-shadow: 0 10px 40px rgba(0,255,255,0.1); }
        .hamburger i { display: block; transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .hamburger.open i { 
            transform: translateX(120px) rotate(90deg);
        }
        
        /* ── SIDEBAR ── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 999;
            backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .sidebar-overlay.active { display: block; opacity: 1; }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: rgba(255,255,255,0.95);
            border-right: 2px solid rgba(0,255,255,0.2);
            padding: 30px 0;
            z-index: 1000;
            backdrop-filter: blur(30px);
            overflow-y: auto;
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 10px 0 60px rgba(0,0,0,0.05);
        }
        .sidebar.open { transform: translateX(0); }
        .sidebar-brand { padding: 0 24px; margin-bottom: 35px; }
        .sidebar-brand .logo { font-size: 22px; font-weight: 900; letter-spacing: 4px; font-family: 'Orbitron', sans-serif; color: #000000; }
        .sidebar-brand .logo span { color: #0088cc; }
        .sidebar-brand .sub { font-size: 8px; color: #999; letter-spacing: 8px; text-transform: uppercase; margin-top: 2px; }
        
        .sidebar-item {
            padding: 14px 24px;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            font-weight: 600;
        }
        .sidebar-item:hover, .sidebar-item.active { color: #0088cc; background: rgba(0,255,255,0.05); border-left-color: #00ffff; }
        .sidebar-item i { width: 20px; font-size: 16px; }
        .sidebar-item.logout { margin-top: 40px; color: #999; border-top: 1px solid rgba(0,255,255,0.1); padding-top: 20px; }
        .sidebar-item.logout:hover { color: #ff3355; border-left-color: #ff3355; }
        
        .wrapper {
            transition: margin-left 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            margin-left: 0;
        }
        .wrapper.shifted { margin-left: 280px; }
        
        /* ── MAIN ── */
        .main {
            padding: 30px 40px 100px;
            position: relative;
            z-index: 6;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* ── TOP BAR ── (BIGGER HEIGHT) ── */
        .top-bar {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            padding: 28px 45px 28px 80px;
            border-bottom: 2px solid rgba(0,255,255,0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 10px 40px rgba(0,255,255,0.08);
            margin-left: 0;
            transition: margin-left 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            min-height: 85px;
        }
        .top-bar.shifted { margin-left: 280px; }
        .top-bar .brand {
            font-family: 'Orbitron', sans-serif;
            font-size: 30px;
            font-weight: 900;
            color: #000000;
            letter-spacing: 10px;
            flex: 1;
        }
        .top-bar .brand span {
            color: #0088cc;
            font-weight: 300;
            font-size: 15px;
            letter-spacing: 5px;
        }
        
        .top-bar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            color: #000;
            font-size: 12px;
            letter-spacing: 1px;
            flex-wrap: wrap;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 18px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            background: rgba(0,255,255,0.1);
            color: #0088cc;
            border: 1px solid rgba(0,255,255,0.1);
        }
        .status-indicator.offline {
            background: rgba(255,51,85,0.1);
            color: #ff3355;
            border-color: rgba(255,51,85,0.1);
            animation: offlinePulse 2s ease-in-out infinite;
        }
        @keyframes offlinePulse { 0%,100%{opacity:1;} 50%{opacity:0.4;} }
        
        .timezone-badge {
            font-size: 9px;
            color: #0088cc;
            background: rgba(0,255,255,0.1);
            padding: 4px 16px;
            border: 1px solid rgba(0,255,255,0.15);
            letter-spacing: 3px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        /* ── HEADER ── */
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; flex-wrap:wrap; gap:15px; padding-top:10px; }
        .header h1 { font-size:22px; font-weight:300; letter-spacing:8px; color:rgba(0,0,0,0.5); font-family:'Orbitron',sans-serif; position:relative; }
        .header h1::after { content:''; position:absolute; bottom:-8px; left:0; width:40%; height:2px; background:linear-gradient(90deg,#00ffff,transparent); animation:headerLine 4s ease-in-out infinite; }
        @keyframes headerLine { 0%,100%{width:40%;} 50%{width:80%;} }
        .header h1 span { color:#0088cc; font-weight:700; }
        .header .user { display:flex; align-items:center; gap:16px; color:rgba(0,0,0,0.4); font-size:11px; letter-spacing:3px; flex-wrap:wrap; }
        .header .user i { font-size:18px; color:#0088cc; }
        
        /* ── STATS ── (BIGGER, MORE SPACING) ── */
        .stats { display:grid; grid-template-columns:repeat(5,1fr); gap:20px; margin-bottom:40px; }
        .stat-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0,255,255,0.2);
            padding: 28px 20px;
            text-align: center;
            transition: all 0.5s cubic-bezier(0.16,1,0.3,1);
            border-radius: 14px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,255,255,0.08);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #00ffff, #0088cc, #00ffff);
            animation: cardLine 3s ease-in-out infinite;
        }
        @keyframes cardLine { 0%,100%{opacity:0.5;} 50%{opacity:1;} }
        .stat-card:hover { transform:translateY(-6px); border-color:rgba(0,255,255,0.4); box-shadow:0 20px 60px rgba(0,255,255,0.15); }
        .stat-card .number { font-size:36px; font-weight:900; font-family:'Orbitron',sans-serif; color:#000000; }
        .stat-card .label { font-size:10px; color:#333; text-transform:uppercase; letter-spacing:4px; margin-top:8px; font-weight:600; }
        .stat-card .icon { font-size:26px; margin-bottom:12px; display:block; color:#0088cc; }
        .stat-card.green .number { color:#00cc88; }
        .stat-card.gold .number { color:#0088cc; }
        .stat-card.red .number { color:#ff3355; }
        .stat-card.dim .number { color:#999; }
        
        /* ── SECTION ── (MORE SPACING) ── */
        .section { display:none; background:rgba(255,255,255,0.9); backdrop-filter:blur(10px); border:1px solid rgba(0,255,255,0.15); padding:32px 36px; margin-bottom:28px; border-radius:14px; position:relative; overflow:hidden; box-shadow:0 10px 40px rgba(0,255,255,0.08); }
        .section.active { display:block; animation:fadeIn 0.4s ease; }
        @keyframes fadeIn { 0%{opacity:0;transform:translateY(10px);} 100%{opacity:1;transform:translateY(0);} }
        .section::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent,#00ffff,#0088cc,#00ffff,transparent); animation:edgePulse 4s ease-in-out infinite; }
        @keyframes edgePulse { 0%,100%{opacity:0.4;} 50%{opacity:1;} }
        
        .section-title { font-size:16px; font-weight:700; letter-spacing:8px; color:#000000; margin-bottom:24px; display:flex; align-items:center; gap:16px; font-family:'Orbitron',sans-serif; }
        .section-title .badge { font-size:9px; background:rgba(0,255,255,0.1); color:#0088cc; padding:4px 18px; letter-spacing:4px; border:1px solid rgba(0,255,255,0.15); border-radius:4px; font-weight:600; }
        
        /* ── GENERATE FORM ── (MORE SPACING) ── */
        .generate-form { display:flex; gap:20px; align-items:center; flex-wrap:wrap; }
        .generate-form label { color:#333; font-size:9px; text-transform:uppercase; letter-spacing:4px; font-weight:700; margin-bottom:2px; display:block; }
        .generate-form input, .generate-form select { padding:14px 20px; background:rgba(255,255,255,0.7); border:1px solid rgba(0,255,255,0.2); color:#000; font-size:14px; outline:none; min-width:70px; font-family:'Segoe UI',sans-serif; transition:all 0.3s ease; border-radius:8px; }
        .generate-form input:focus, .generate-form select:focus { border-color:#00ffff; box-shadow:0 0 30px rgba(0,255,255,0.1); background:rgba(255,255,255,0.9); }
        .generate-form button { padding:14px 40px; background:linear-gradient(135deg,#00ffff,#0088cc); border:none; color:#000; font-size:12px; font-weight:900; text-transform:uppercase; letter-spacing:6px; cursor:pointer; transition:all 0.3s ease; border-radius:8px; box-shadow:0 10px 30px rgba(0,255,255,0.15); }
        .generate-form button:hover { box-shadow:0 15px 50px rgba(0,255,255,0.25); transform:scale(1.02); }
        
        .message { margin-top:16px; color:#00cc88; font-size:13px; font-weight:700; }
        .error { margin-top:16px; color:#ff3355; font-size:13px; font-weight:700; }
        
        /* ── TABLE ── (MORE SPACING) ── */
        .table-container { overflow-x:auto; -webkit-overflow-scrolling:touch; margin-top:8px; }
        table { width:100%; border-collapse:collapse; font-size:13px; min-width:700px; }
        th { padding:14px 16px; text-align:left; color:#333; font-size:9px; text-transform:uppercase; letter-spacing:4px; border-bottom:2px solid rgba(0,255,255,0.15); font-weight:700; }
        td { padding:14px 16px; border-bottom:1px solid rgba(0,255,255,0.08); color:#000; }
        tr:hover td { background:rgba(0,255,255,0.03); }
        .key-code { font-family:'Courier New',monospace; font-size:14px; color:#0088cc; font-weight:700; letter-spacing:1px; }
        
        .status { display:inline-flex; align-items:center; gap:8px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; }
        .status .dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
        .status.active .dot { background:#00cc88; box-shadow:0 0 20px rgba(0,204,136,0.2); animation:dotPulse 1.5s ease-in-out infinite; }
        @keyframes dotPulse { 0%,100%{opacity:1;} 50%{opacity:0.3;} }
        .status.disabled .dot { background:#999; }
        .status.used .dot { background:#ffaa33; }
        .status.active { color:#00cc88; }
        .status.disabled { color:#999; }
        .status.used { color:#ffaa33; }
        
        .status-text { font-size:12px; font-weight:600; letter-spacing:1px; }
        .status-text.active { color:#00cc88; }
        .status-text.disabled { color:#999; }
        .status-text.used { color:#ffaa33; }
        
        .action-btn { padding:6px 14px; border:none; font-size:9px; font-weight:700; cursor:pointer; text-transform:uppercase; letter-spacing:1px; transition:all 0.3s ease; text-decoration:none; display:inline-block; margin:2px; border:1px solid transparent; border-radius:4px; }
        .action-btn.enable { background:rgba(0,204,136,0.1); color:#00cc88; border-color:rgba(0,204,136,0.1); }
        .action-btn.enable:hover { background:rgba(0,204,136,0.2); }
        .action-btn.disable { background:rgba(255,51,85,0.1); color:#ff3355; border-color:rgba(255,51,85,0.1); }
        .action-btn.disable:hover { background:rgba(255,51,85,0.2); }
        .action-btn.delete { background:rgba(0,0,0,0.03); color:#999; border-color:rgba(0,0,0,0.03); }
        .action-btn.delete:hover { color:#ff3355; background:rgba(255,51,85,0.05); }
        
        .no-keys { text-align:center; color:#999; padding:50px; font-size:15px; letter-spacing:4px; }
        .no-keys i { display:block; font-size:50px; margin-bottom:14px; color:#ddd; }
        
        /* ── WORLD TIMES ── */
        .world-times-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:16px; padding:10px 0; }
        .world-time-card { background: rgba(255,255,255,0.7); border: 1px solid rgba(0,255,255,0.1); padding:16px 18px; border-radius:10px; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .world-time-card:hover { border-color: rgba(0,255,255,0.3); background: rgba(255,255,255,0.9); transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,255,255,0.08); }
        .world-time-card .wt-flag { font-size: 30px; margin-bottom: 6px; }
        .world-time-card .wt-name { font-size: 12px; color: #333; letter-spacing: 1px; text-transform: uppercase; font-weight: 700; }
        .world-time-card .wt-current { font-size: 11px; color: #666; margin-top: 6px; }
        .world-time-card .wt-expiry { font-size: 15px; color: #000; font-family: 'Orbitron', monospace; margin-top: 6px; }
        .world-time-card .wt-expiry span { color: #0088cc; font-weight: 700; }
        .world-time-card .wt-status { font-size: 11px; margin-top: 6px; font-weight: 700; letter-spacing: 1px; }
        
        /* ── SETTINGS ── (MORE SPACING) ── */
        .settings-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; }
        .settings-item { background:rgba(255,255,255,0.7); padding:20px 24px; border:1px solid rgba(0,255,255,0.1); border-radius:10px; transition:all 0.3s ease; }
        .settings-item:hover { border-color:rgba(0,255,255,0.3); background:rgba(255,255,255,0.9); transform:translateY(-3px); box-shadow:0 10px 30px rgba(0,255,255,0.08); }
        .settings-item .label { font-size:9px; color:#666; text-transform:uppercase; letter-spacing:5px; font-weight:700; }
        .settings-item .value { font-size:18px; font-weight:700; font-family:'Orbitron',monospace; margin-top:6px; color:#000; }
        .settings-item .value.gold { color:#0088cc; }
        .settings-item .value.green { color:#00cc88; }
        .settings-item .value.red { color:#ff3355; }
        .settings-item .value.dim { color:#999; }
        
        @media (max-width:1024px) {
            .hamburger { display: block; }
            .main { padding: 20px 20px 80px; }
            .top-bar { padding: 20px 20px 20px 70px; min-height: 70px; }
            .top-bar .brand { font-size: 22px; letter-spacing: 4px; }
            .top-bar.shifted { margin-left: 0; }
            .stats { grid-template-columns: repeat(3, 1fr); gap: 14px; }
            .floating-clock { top: 20px; right: 20px; min-width: 150px; padding: 10px 16px; }
            .floating-clock .time { font-size: 18px; }
            table { min-width: 700px; font-size: 12px; }
            th, td { padding: 10px 12px; }
            .hamburger.open i { transform: translateX(90px) rotate(90deg); }
        }
        
        @media (max-width: 768px) {
            .main { padding: 15px 15px 70px; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header h1 { font-size: 18px; letter-spacing: 4px; }
            .stats { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 18px 14px; }
            .stat-card .number { font-size: 26px; }
            .section { padding: 20px 20px; }
            .section-title { font-size: 14px; letter-spacing: 4px; }
            .generate-form { flex-direction: column; align-items: stretch; }
            .generate-form input, .generate-form select { width: 100%; }
            .generate-form button { width: 100%; }
            .settings-grid { grid-template-columns: 1fr 1fr; }
            .floating-clock { top: 15px; right: 15px; min-width: 120px; padding: 8px 12px; }
            .floating-clock .time { font-size: 16px; }
            table { min-width: 600px; font-size: 11px; }
            th, td { padding: 8px 10px; }
            .world-times-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
            .top-bar { padding: 14px 16px 14px 60px; min-height: 60px; }
            .top-bar .brand { font-size: 18px; letter-spacing: 3px; }
            .top-bar .brand span { font-size: 11px; letter-spacing: 2px; }
            .hamburger { top: 14px; left: 10px; padding: 8px 10px; font-size: 18px; }
            .hamburger.open i { transform: translateX(60px) rotate(90deg); }
        }
        
        @media (max-width: 480px) {
            .main { padding: 10px 10px 60px; }
            .header h1 { font-size: 15px; letter-spacing: 3px; }
            .stats { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 14px 10px; }
            .stat-card .number { font-size: 22px; }
            .stat-card .label { font-size: 8px; letter-spacing: 2px; }
            .stat-card .icon { font-size: 20px; }
            .section { padding: 14px 14px; }
            .section-title { font-size: 12px; letter-spacing: 3px; }
            .generate-form input, .generate-form select { padding: 10px 14px; font-size: 12px; }
            .generate-form button { padding: 12px 20px; font-size: 10px; }
            .settings-grid { grid-template-columns: 1fr; gap: 8px; }
            .settings-item { padding: 12px 14px; }
            .settings-item .value { font-size: 15px; }
            .floating-clock { top: 8px; right: 8px; min-width: 90px; padding: 6px 10px; }
            .floating-clock .time { font-size: 13px; }
            table { min-width: 450px; font-size: 10px; }
            th, td { padding: 6px 8px; }
            .key-code { font-size: 11px; }
            .action-btn { padding: 4px 10px; font-size: 8px; }
            .world-times-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
            .world-time-card { padding: 10px 10px; }
            .world-time-card .wt-flag { font-size: 22px; }
            .world-time-card .wt-name { font-size: 10px; }
            .world-time-card .wt-expiry { font-size: 12px; }
            .hamburger { top: 10px; left: 8px; padding: 6px 8px; font-size: 16px; }
            .hamburger.open i { transform: translateX(45px) rotate(90deg); }
            .top-bar { padding: 10px 12px 10px 50px; min-height: 50px; }
            .top-bar .brand { font-size: 14px; letter-spacing: 2px; }
            .top-bar .brand span { font-size: 9px; letter-spacing: 1px; }
        }
        
        @media (min-width: 1025px) {
            .hamburger { display: none; }
            .sidebar-overlay { display: none !important; }
        }
    </style>
</head>
<body>
    
    <!-- ── OFFLINE OVERLAY ── -->
    <div class="offline-overlay <?= $online_status === 'offline' ? 'active' : '' ?>" id="offlineOverlay">
        <div class="icon">⚡</div>
        <h2>OFFLINE MODE</h2>
        <div class="line"></div>
        <p>No internet connection detected</p>
        <p style="font-size:9px;color:rgba(0,255,255,0.2);margin-top:5px;">Existing keys remain active</p>
        <p class="sub">SENSI MODS · v2.0</p>
        <button class="retry-btn" onclick="location.reload()">↻ RETRY</button>
    </div>
    
    <!-- ── BACKGROUND EFFECTS ── -->
    <div class="electric-lines" id="electricLines"></div>
    <div class="fire-storm" id="fireStorm"></div>
    <div class="glow-orb glow-orb-1"></div>
    <div class="glow-orb glow-orb-2"></div>
    <div class="glow-orb glow-orb-3"></div>
    <div class="glow-orb glow-orb-4"></div>
    <div class="glow-orb glow-orb-5"></div>
    <div class="float-particles" id="floatParticles"></div>
    <div class="grid-lines"></div>
    <div class="corner-fire corner-fire-tl"></div>
    <div class="corner-fire corner-fire-tr"></div>
    <div class="corner-fire corner-fire-bl"></div>
    <div class="corner-fire corner-fire-br"></div>
    
    <!-- ── HAMBURGER ── (On same line as title) ── -->
    <div class="hamburger" id="hamburger" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- ── SIDEBAR OVERLAY ── -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    
    <!-- ── SIDEBAR ── -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo">SENSI <span>MODS</span></div>
            <div class="sub">Premium · Panel</div>
        </div>
        <a href="#" class="sidebar-item active" onclick="showSection('dashboard'); closeSidebar();"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="#" class="sidebar-item" onclick="showSection('generate'); closeSidebar();"><i class="fas fa-key"></i><span>Generate</span></a>
        <a href="#" class="sidebar-item" onclick="showSection('licenses'); closeSidebar();"><i class="fas fa-list"></i><span>Licenses</span></a>
        <a href="#" class="sidebar-item" onclick="showSection('worldtimes'); closeSidebar();"><i class="fas fa-globe-africa"></i><span>  Middle East</span></a>
        <a href="#" class="sidebar-item" onclick="showSection('telegram'); closeSidebar();"><i class="fab fa-telegram"></i><span>Telegram Bot</span></a>
        <a href="#" class="sidebar-item" onclick="showSection('settings'); closeSidebar();"><i class="fas fa-cog"></i><span>Settings</span></a>
        <a href="logout.php" class="sidebar-item logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>
    
    <!-- ── FLOATING CLOCK ── -->
    <div class="floating-clock <?= ($active_key_timer && $active_key_timer['seconds'] > 0) ? 'timer-mode' : '' ?>" id="floatingClock">
        <div class="time"><span id="clockDisplay"><?= $nigeria_time ?></span></div>
        <div class="label" id="clockLabel">
            <i class="fas <?= ($active_key_timer && $active_key_timer['seconds'] > 0) ? 'fa-hourglass-half' : 'fa-map-pin' ?>"></i> 
            <?= ($active_key_timer && $active_key_timer['seconds'] > 0) ? 'Key Expires In' : 'Nigeria Time (UTC+1)' ?>
        </div>
        <?php if ($active_key_timer && $active_key_timer['seconds'] > 0): ?>
        <div class="timer-key" id="timerKeyInfo">🔑 <?= $active_key_timer['key'] ?> expires at <?= $active_key_timer['expires'] ?></div>
        <?php endif; ?>
    </div>
    
    <!-- ── WRAPPER ── -->
    <div class="wrapper" id="wrapper">
    
        <!-- ── TOP BAR ── (BIGGER) ── -->
        <div class="top-bar" id="topBar">
            <div class="brand">SENSI <span>MODS</span></div>
            <div class="user-info">
                <i class="fas fa-user-cog" style="color:#0088cc;"></i>
                <?= htmlspecialchars($_SESSION['username']) ?>
                <span style="color:#ccc;">|</span>
                <span class="status-indicator <?= $online_status === 'offline' ? 'offline' : '' ?>">
                    <i class="fas <?= $online_status === 'online' ? 'fa-wifi' : 'fa-exclamation-triangle' ?>"></i>
                    <?= ucfirst($online_status) ?>
                </span>
                <span class="timezone-badge"><i class="fas fa-clock"></i> <?= $user_timezone ?></span>
            </div>
        </div>
        
        <!-- ── MAIN ── -->
        <div class="main">
            
            <div class="header">
                <h1 id="pageTitle">Dashboard · <span>Panel</span></h1>
                <div class="user">
                    <i class="fas fa-user-circle"></i>
                    <?= htmlspecialchars($_SESSION['username']) ?>
                    <span style="color:rgba(0,0,0,0.1);">|</span>
                    <span style="color:#0088cc;">SenseiDev</span>
                </div>
            </div>
            
            <!-- ===== DASHBOARD ===== -->
            <div id="section-dashboard" class="section active">
                <div class="stats">
                    <div class="stat-card"><div class="glow"></div><i class="fas fa-key icon"></i><div class="number"><?= $total ?></div><div class="label">Total Keys</div></div>
                    <div class="stat-card green"><div class="glow"></div><i class="fas fa-check-circle icon"></i><div class="number"><?= $active ?></div><div class="label">Active</div></div>
                    <div class="stat-card gold"><div class="glow"></div><i class="fas fa-user-check icon"></i><div class="number"><?= $used ?></div><div class="label">Used</div></div>
                    <div class="stat-card red"><div class="glow"></div><i class="fas fa-times-circle icon"></i><div class="number">0</div><div class="label">Expired</div></div>
                    <div class="stat-card dim"><div class="glow"></div><i class="fas fa-ban icon"></i><div class="number"><?= $disabled ?></div><div class="label">Disabled</div></div>
                </div>
                <div style="color:rgba(0,0,0,0.3);text-align:center;font-size:13px;letter-spacing:3px;padding:20px 0;">
                    <i class="fas fa-check-circle" style="color:#00cc88;"></i> Welcome to SENSI MODS Panel
                </div>
            </div>
            
            <!-- ===== GENERATE ===== -->
            <div id="section-generate" class="section">
                <div class="fire-glow"></div>
                <div class="section-title">
                    <i class="fas fa-key" style="color:#0088cc;"></i>
                    Generate License Key
                    <span class="badge">Max 20</span>
                </div>
                
                <form method="POST" class="generate-form">
                    <div>
                        <label>Count</label>
                        <input type="number" name="count" value="1" min="1" max="20">
                    </div>
                    <div>
                        <label>Expiry</label>
                        <input type="number" name="expiry_value" value="2" min="1">
                    </div>
                    <div>
                        <label>Unit</label>
                        <select name="expiry_unit">
                            <option value="minutes" selected>Minutes</option>
                            <option value="hours">Hours</option>
                            <option value="days">Days</option>
                            <option value="months">Months</option>
                            <option value="years">Years</option>
                        </select>
                    </div>
                    <div>
                        <label>Custom Key</label>
                        <input type="text" name="custom_key" placeholder="Leave empty for random" style="min-width:150px;">
                    </div>
                    <div>
                        <label>Max Devices</label>
                        <input type="number" name="max_devices" value="1" min="1" max="999" style="width:70px;">
                    </div>
                    <button type="submit" name="generate">Generate</button>
                </form>
                
                <?php if ($message): ?>
                    <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="error"><i class="fas fa-times-circle"></i> <?= $error ?></div>
                <?php endif; ?>
            </div>
            
            <!-- ===== LICENSES ===== -->
            <div id="section-licenses" class="section">
                <div class="fire-glow"></div>
                <div class="section-title">
                    <i class="fas fa-list" style="color:#0088cc;"></i>
                    License List
                    <span class="badge"><?= $total ?> total</span>
                </div>
                
                <div class="table-container">
                    <?php if (empty($keys)): ?>
                        <div class="no-keys"><i class="fas fa-key"></i>No licenses generated yet</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>License Key</th>
                                    <th>Status</th>
                                    <th>Devices</th>
                                    <th>Expires</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($keys as $key): ?>
                                <tr>
                                    <td><span class="key-code"><?= htmlspecialchars($key['license_key']) ?></span></td>
                                    <td>
                                        <span class="status <?= $key['status'] ?>">
                                            <span class="dot"></span>
                                            <span class="status-text <?= $key['status'] ?>"><?= ucfirst($key['status']) ?></span>
                                        </span>
                                    </td>
                                    <td style="font-size:12px;color:rgba(0,0,0,0.4);"><?= $key['device_count'] ?? 0 ?> / <?= $key['max_devices'] ?? 1 ?></td>
                                    <td style="font-size:12px;color:rgba(0,0,0,0.5);"><?= $key['expires_at_display'] ?? $key['expires_at'] ?></td>
                                    <td style="font-size:12px;color:rgba(0,0,0,0.2);"><?= date('Y-m-d', strtotime($key['created_at'])) ?></td>
                                    <td>
                                        <a href="?toggle=1&id=<?= $key['id'] ?>" class="action-btn <?= $key['status'] === 'active' ? 'disable' : 'enable' ?>"><?= $key['status'] === 'active' ? 'Disable' : 'Enable' ?></a>
                                        <a href="?delete=1&id=<?= $key['id'] ?>" class="action-btn delete" onclick="return confirm('⚠️ Permanently delete this key?\nThis cannot be undone!')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== MIDDLE EAST ===== -->
            <div id="section-worldtimes" class="section">
                <div class="fire-glow"></div>
                <div class="section-title">
                    <i class="fas fa-globe-africa" style="color:#0088cc;"></i>
                      Middle East
                    <span class="badge">All Countries</span>
                </div>
                
                <div class="world-times-grid">
                    <?php
                    $world_expiry = $first_key_expiry;
                    
                    if ($world_expiry) {
                        $countries = [
                            ['flag' => '🇸🇦', 'name' => 'Saudi Arabia', 'tz' => 'Asia/Riyadh'],
                            ['flag' => '🇦🇪', 'name' => 'UAE', 'tz' => 'Asia/Dubai'],
                            ['flag' => '🇶🇦', 'name' => 'Qatar', 'tz' => 'Asia/Qatar'],
                            ['flag' => '🇰🇼', 'name' => 'Kuwait', 'tz' => 'Asia/Kuwait'],
                            ['flag' => '🇧🇭', 'name' => 'Bahrain', 'tz' => 'Asia/Bahrain'],
                            ['flag' => '🇴🇲', 'name' => 'Oman', 'tz' => 'Asia/Muscat'],
                            ['flag' => '🇯🇴', 'name' => 'Jordan', 'tz' => 'Asia/Amman'],
                            ['flag' => '🇱🇧', 'name' => 'Lebanon', 'tz' => 'Asia/Beirut'],
                            ['flag' => '🇮🇱', 'name' => 'Israel', 'tz' => 'Asia/Jerusalem'],
                            ['flag' => '🇵🇸', 'name' => 'Palestine', 'tz' => 'Asia/Gaza'],
                            ['flag' => '🇸🇾', 'name' => 'Syria', 'tz' => 'Asia/Damascus'],
                            ['flag' => '🇮🇶', 'name' => 'Iraq', 'tz' => 'Asia/Baghdad'],
                            ['flag' => '🇮🇷', 'name' => 'Iran', 'tz' => 'Asia/Tehran'],
                            ['flag' => '🇹🇷', 'name' => 'Turkey', 'tz' => 'Europe/Istanbul'],
                            ['flag' => '🇾🇪', 'name' => 'Yemen', 'tz' => 'Asia/Aden'],
                            ['flag' => '🇪🇬', 'name' => 'Egypt', 'tz' => 'Africa/Cairo'],
                            ['flag' => '🇱🇾', 'name' => 'Libya', 'tz' => 'Africa/Tripoli'],
                            ['flag' => '🇩🇿', 'name' => 'Algeria', 'tz' => 'Africa/Algiers'],
                            ['flag' => '🇲🇦', 'name' => 'Morocco', 'tz' => 'Africa/Casablanca'],
                            ['flag' => '🇸🇩', 'name' => 'Sudan', 'tz' => 'Africa/Khartoum'],
                            ['flag' => '🇹🇳', 'name' => 'Tunisia', 'tz' => 'Africa/Tunis'],
                        ];
                        
                        foreach ($countries as $country) {
                            try {
                                $tz_obj = new DateTimeZone($country['tz']);
                                $expiry_tz = clone $world_expiry;
                                $expiry_tz->setTimezone($tz_obj);
                                $time = $expiry_tz->format('g:i:s A');
                                $now = new DateTime('now', $tz_obj);
                                $current = $now->format('g:i:s A');
                                
                                $is_expired = ($now > $expiry_tz);
                                $status_text = $is_expired ? '🔴 EXPIRED' : '🟢 Active';
                                $status_color = $is_expired ? '#ff3355' : '#00cc88';
                                
                                echo '<div class="world-time-card">';
                                echo '<div class="wt-flag">' . $country['flag'] . '</div>';
                                echo '<div class="wt-name">' . $country['name'] . '</div>';
                                echo '<div class="wt-current">🕐 ' . $current . '</div>';
                                echo '<div class="wt-expiry">⏳ Expires: <span>' . $time . '</span></div>';
                                echo '<div class="wt-status" style="color:' . $status_color . ';">' . $status_text . '</div>';
                                echo '</div>';
                            } catch (Exception $e) {}
                        }
                    } else {
                        echo '<div class="no-keys" style="grid-column:1/-1;"><i class="fas fa-key"></i>Generate a key first to see Middle East times</div>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- ===== TELEGRAM BOT ===== -->
            <div id="section-telegram" class="section">
                <div class="fire-glow"></div>
                <div class="section-title">
                    <i class="fab fa-telegram" style="color:#00ffff;"></i>
                    Telegram Bot Control
                    <span class="badge">🟢 Online</span>
                </div>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:15px;">
                    <div style="background:rgba(0,0,0,0.2);padding:16px;border-radius:8px;border:1px solid rgba(0,255,255,0.1);text-align:center;">
                        <div style="font-size:28px;font-weight:700;font-family:'Orbitron',monospace;color:#00ffff;"><?= $total ?? 0 ?></div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:4px;">Total Keys</div>
                    </div>
                    <div style="background:rgba(0,0,0,0.2);padding:16px;border-radius:8px;border:1px solid rgba(0,255,255,0.1);text-align:center;">
                        <div style="font-size:28px;font-weight:700;font-family:'Orbitron',monospace;color:#00cc88;"><?= $active ?? 0 ?></div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:4px;">Active</div>
                    </div>
                    <div style="background:rgba(0,0,0,0.2);padding:16px;border-radius:8px;border:1px solid rgba(0,255,255,0.1);text-align:center;">
                        <div style="font-size:28px;font-weight:700;font-family:'Orbitron',monospace;color:#ffaa33;"><?= $used ?? 0 ?></div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:4px;">Used</div>
                    </div>
                    <div style="background:rgba(0,0,0,0.2);padding:16px;border-radius:8px;border:1px solid rgba(0,255,255,0.1);text-align:center;">
                        <div style="font-size:28px;font-weight:700;font-family:'Orbitron',monospace;color:#ff3355;">0</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:4px;">Expired</div>
                    </div>
                    <div style="background:rgba(0,0,0,0.2);padding:16px;border-radius:8px;border:1px solid rgba(0,255,255,0.1);text-align:center;">
                        <div style="font-size:28px;font-weight:700;font-family:'Orbitron',monospace;color:rgba(255,255,255,0.3);"><?= $disabled ?? 0 ?></div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:4px;">Disabled</div>
                    </div>
                    <div style="background:rgba(0,0,0,0.2);padding:16px;border-radius:8px;border:1px solid rgba(0,255,255,0.1);text-align:center;">
                        <div style="font-size:16px;font-weight:700;font-family:'Orbitron',monospace;color:#00cc88;">🟢 Active</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:4px;">Bot Status</div>
                    </div>
                </div>
                
                <div style="background:rgba(0,0,0,0.2);padding:12px;border-radius:8px;border:1px solid rgba(0,255,255,0.05);margin-bottom:15px;">
                    <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-bottom:6px;">📋 Bot Commands</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:6px;">
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/gen [amount] [time]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/list</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/check [key]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/delete [key]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/disable [key]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/enable [key]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/sell [key] [price]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/renew [key] [time]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/maxdev [key] [number]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/hwid [key] [hwid]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/timer [key]</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/worldtimes</code>
                        <code style="background:rgba(0,0,0,0.3);padding:4px 10px;border-radius:4px;font-size:10px;color:rgba(255,255,255,0.6);border:1px solid rgba(0,255,255,0.05);text-align:center;">/stats</code>
                    </div>
                </div>
                
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="https://t.me/Sensimodsadminconrol_bot" target="_blank" style="padding:10px 25px;background:linear-gradient(135deg,#0088cc,#00ffff);color:#000;text-decoration:none;border-radius:6px;font-weight:700;font-size:12px;letter-spacing:2px;display:inline-block;">
                        <i class="fab fa-telegram"></i> Open Bot
                    </a>
                    <button onclick="location.reload()" style="padding:10px 25px;background:rgba(0,255,255,0.1);color:#00ffff;border:1px solid rgba(0,255,255,0.2);border-radius:6px;font-weight:700;font-size:12px;cursor:pointer;letter-spacing:2px;">
                        <i class="fas fa-sync"></i> Refresh Stats
                    </button>
                </div>
            </div>
            
            <!-- ===== SETTINGS ===== -->
            <div id="section-settings" class="section">
                <div class="fire-glow"></div>
                <div class="section-title">
                    <i class="fas fa-cog" style="color:#0088cc;"></i>
                    Settings
                    <span class="badge">Panel Info</span>
                </div>
                
                <div class="settings-grid">
                    <div class="settings-item"><div class="label">Panel Name</div><div class="value gold">SENSI MODS</div></div>
                    <div class="settings-item"><div class="label">Version</div><div class="value">v2.0</div></div>
                    <div class="settings-item"><div class="label">Developer</div><div class="value green">SenseiDev</div></div>
                    <div class="settings-item"><div class="label">Your Status</div><div class="value <?= $online_status === 'online' ? 'green' : 'red' ?>"><?= $online_status === 'online' ? '🟢 Online' : '🔴 Offline' ?></div></div>
                    <div class="settings-item"><div class="label">Your Timezone</div><div class="value gold"><?= $user_timezone ?></div></div>
                    <div class="settings-item"><div class="label">Nigeria Time</div><div class="value gold" id="nigeriaClock"><?= $nigeria_time ?></div></div>
                    <div class="settings-item"><div class="label">Total Keys</div><div class="value"><?= $total ?></div></div>
                    <div class="settings-item"><div class="label">Active Keys</div><div class="value green"><?= $active ?></div></div>
                    <div class="settings-item"><div class="label">Used Keys</div><div class="value gold"><?= $used ?></div></div>
                    <div class="settings-item"><div class="label">Expired Keys</div><div class="value red">0</div></div>
                    <div class="settings-item"><div class="label">Disabled Keys</div><div class="value dim"><?= $disabled ?></div></div>
                    <div class="settings-item"><div class="label">Internet Status</div><div class="value <?= $online_status === 'online' ? 'green' : 'red' ?>"><?= $online_status === 'online' ? '🟢 Connected' : '🔴 Disconnected' ?></div></div>
                </div>
            </div>
            
        </div>
    
    </div><!-- END WRAPPER -->
    
    <script>
        // ── ELECTRIC LINES ──
        (function() {
            const container = document.getElementById('electricLines');
            const colors = ['rgba(0,255,255,0.35)', 'rgba(0,200,255,0.25)', 'rgba(0,150,255,0.15)'];
            
            for (let i = 0; i < 20; i++) {
                const line = document.createElement('div');
                line.className = 'electric-line';
                line.style.top = (Math.random() * 100) + '%';
                line.style.left = (Math.random() * 50) + '%';
                line.style.width = (Math.random() * 50 + 20) + '%';
                line.style.animationDuration = (Math.random() * 4 + 3) + 's';
                line.style.animationDelay = (Math.random() * 5) + 's';
                line.style.opacity = Math.random() * 0.4 + 0.1;
                line.style.background = 'linear-gradient(90deg, transparent, ' + colors[i % 3] + ', ' + colors[(i+1) % 3] + ', transparent)';
                container.appendChild(line);
            }
            
            for (let i = 0; i < 14; i++) {
                const line = document.createElement('div');
                line.className = 'electric-line vertical';
                line.style.left = (Math.random() * 100) + '%';
                line.style.top = (Math.random() * 30) + '%';
                line.style.height = (Math.random() * 40 + 20) + '%';
                line.style.animationDuration = (Math.random() * 5 + 4) + 's';
                line.style.animationDelay = (Math.random() * 6) + 's';
                line.style.opacity = Math.random() * 0.3 + 0.05;
                line.style.background = 'linear-gradient(180deg, transparent, ' + colors[i % 3] + ', ' + colors[(i+1) % 3] + ', transparent)';
                container.appendChild(line);
            }
            
            for (let i = 0; i < 25; i++) {
                const node = document.createElement('div');
                node.className = 'electric-node';
                node.style.left = (Math.random() * 100) + '%';
                node.style.top = (Math.random() * 100) + '%';
                node.style.animationDelay = (Math.random() * 3) + 's';
                container.appendChild(node);
            }
        })();
        
        // ── FIRE STORM ──
        (function() {
            const container = document.getElementById('fireStorm');
            for (let i = 0; i < 60; i++) {
                const p = document.createElement('div');
                p.className = 'fire-particle';
                p.style.left = Math.random() * 100 + '%';
                p.style.width = (Math.random() * 6 + 2) + 'px';
                p.style.height = p.style.width;
                p.style.animationDuration = (Math.random() * 25 + 15) + 's';
                p.style.animationDelay = (Math.random() * 20) + 's';
                p.style.opacity = Math.random() * 0.4 + 0.05;
                container.appendChild(p);
            }
        })();
        
        // ── FLOATING PARTICLES ──
        (function() {
            const container = document.getElementById('floatParticles');
            for (let i = 0; i < 40; i++) {
                const dot = document.createElement('div');
                dot.className = 'float-particle';
                dot.style.left = Math.random() * 100 + '%';
                dot.style.width = (Math.random() * 3 + 1) + 'px';
                dot.style.height = dot.style.width;
                dot.style.animationDuration = (Math.random() * 20 + 15) + 's';
                dot.style.animationDelay = (Math.random() * 20) + 's';
                dot.style.opacity = Math.random() * 0.3 + 0.05;
                container.appendChild(dot);
            }
        })();
        
        // ── SPARKLES ──
        (function() {
            document.querySelectorAll('.section, .stat-card').forEach(section => {
                for (let i = 0; i < 3; i++) {
                    const s = document.createElement('div');
                    s.className = 'sparkle';
                    if (Math.random() > 0.6) s.classList.add('sparkle-lg');
                    if (Math.random() > 0.8) s.classList.add('sparkle-xl');
                    s.style.top = Math.random() * 100 + '%';
                    s.style.left = Math.random() * 100 + '%';
                    s.style.animationDelay = (Math.random() * 4) + 's';
                    s.style.animationDuration = (Math.random() * 3 + 2) + 's';
                    section.appendChild(s);
                }
            });
        })();
        
        // ── SIDEBAR ──
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburger');
            const wrapper = document.getElementById('wrapper');
            const topBar = document.getElementById('topBar');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            hamburger.classList.toggle('open');
            
            const icon = hamburger.querySelector('i');
            if (hamburger.classList.contains('open')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
            
            if (window.innerWidth > 1024) {
                wrapper.classList.toggle('shifted');
                topBar.classList.toggle('shifted');
            }
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburger');
            const wrapper = document.getElementById('wrapper');
            const topBar = document.getElementById('topBar');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            hamburger.classList.remove('open');
            
            const icon = hamburger.querySelector('i');
            icon.className = 'fas fa-bars';
            
            if (window.innerWidth > 1024) {
                wrapper.classList.remove('shifted');
                topBar.classList.remove('shifted');
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });
        
        // ── NAVIGATION ──
        function showSection(section) {
            document.querySelectorAll('.section').forEach(el => el.classList.remove('active'));
            document.getElementById('section-' + section).classList.add('active');
            document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.sidebar-item').forEach(el => {
                if (el.textContent.trim().toLowerCase().includes(section)) el.classList.add('active');
            });
            const titles = {
                'dashboard': 'Dashboard · <span>Panel</span>',
                'generate': 'Generate · <span>Keys</span>',
                'licenses': 'Licenses · <span>List</span>',
                'worldtimes': '  Middle East · <span>All Countries</span>',
                'telegram': 'Telegram Bot · <span>Control</span>',
                'settings': 'Settings · <span>Panel</span>'
            };
            document.getElementById('pageTitle').innerHTML = titles[section] || 'Dashboard · <span>Panel</span>';
        }
        
        // ── CLOCK ──
        function updateClock() {
            const now = new Date();
            const lagos = now.toLocaleString('en-US', { timeZone: 'Africa/Lagos' });
            const d = new Date(lagos);
            let h = d.getHours();
            const m = String(d.getMinutes()).padStart(2, '0');
            const s = String(d.getSeconds()).padStart(2, '0');
            const ap = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            const str = String(h).padStart(2, '0') + ':' + m + ':' + s + ' ' + ap;
            document.querySelectorAll('#clockDisplay, .clock, #nigeriaClock').forEach(el => { if (el) el.textContent = str; });
        }
        updateClock();
        setInterval(updateClock, 1000);
        
        // ── COUNTDOWN ──
        <?php if ($active_key_timer && $active_key_timer['seconds'] > 0): ?>
        let countdownSeconds = <?= $active_key_timer['seconds'] ?>;
        const cd = document.getElementById('clockDisplay');
        let refreshed = false;
        function updateCD() {
            if (countdownSeconds > 0) {
                const h = Math.floor(countdownSeconds / 3600);
                const m = Math.floor((countdownSeconds % 3600) / 60);
                const s = countdownSeconds % 60;
                cd.textContent = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                countdownSeconds--;
                if (countdownSeconds <= 5 && countdownSeconds > 0) {
                    cd.style.color = '#ff6644';
                }
                if (countdownSeconds <= 0 && !refreshed) {
                    refreshed = true;
                    cd.textContent = '00:00:00';
                    cd.style.color = '#ff3355';
                    setTimeout(() => { location.reload(); }, 1500);
                }
            }
        }
        updateCD();
        setInterval(updateCD, 1000);
        <?php endif; ?>
        
        // ── OFFLINE CHECK ──
        <?php if ($online_status === 'offline'): ?>
        document.getElementById('offlineOverlay').classList.add('active');
        document.addEventListener('click', function(e) {
            if (e.target.closest('.offline-overlay')) return;
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select')) {
                e.preventDefault();
                document.getElementById('offlineOverlay').classList.add('active');
            }
        });
        <?php endif; ?>
        
        console.log('⚡ SENSI MODS · Dashboard loaded');
    </script>
    
</body>
</html>