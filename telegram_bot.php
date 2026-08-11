<?php
// ============================================================
// 🔥 SENSI MODS ULTIMATE MASTER BOT
// 🔥 ADMIN ID: 987322
// 🔥 FULL CONTROL - ONLY YOU CAN USE IT
// 🔥 WORKS IN GROUPS, CHANNELS, PRIVATE CHATS
// ============================================================

// 🔥 BOT CONFIGURATION
$botToken = "8848564612:AAFj3ueTQ4AvMaXu-KYqeLj_sjUAxU40dUI";
$adminId = "987322"; // ✅ ONLY YOU

// 🔥 DATABASE CONNECTION - RENDER POSTGRESQL
require_once 'db.php';

// 🔥 BOT SETTINGS
$botName = "🔥 SENSI MODS MASTER BOT";
$botDescription = "Ultimate License Management Bot - Admin Only";

// ============================================================
// 📥 HANDLE INCOMING TELEGRAM MESSAGES
// ============================================================

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) {
    echo "ok";
    exit;
}

$chatId = $update['message']['chat']['id'] ?? '';
$text = $update['message']['text'] ?? '';
$userId = $update['message']['from']['id'] ?? '';
$username = $update['message']['from']['username'] ?? '';
$firstName = $update['message']['from']['first_name'] ?? '';
$photo = $update['message']['photo'] ?? null;
$messageId = $update['message']['message_id'] ?? '';

// ── CHECK IF USER IS ADMIN (ONLY 987322) ──
$isAdmin = ($userId == $adminId);

// ── CHECK IF USER IS RESELLER ──
$isReseller = false;
$stmt = db()->prepare("SELECT * FROM resellers WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
if ($stmt->fetch()) { $isReseller = true; }

// ── CHECK IF USER IS REBRANDER ──
$isRebrander = false;
$stmt = db()->prepare("SELECT * FROM rebranders WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
if ($stmt->fetch()) { $isRebrander = true; }

// ── BLOCK UNAUTHORIZED ──
if (!$isAdmin && !$isReseller && !$isRebrander) {
    sendMessage($chatId, "⛔ **UNAUTHORIZED ACCESS**\n\n🔐 This bot is for authorized personnel only.\n\n👑 Owner: @SenseiDev");
    exit;
}

// ── LOG ADMIN ACTION ──
try {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO interactions (user_id, username, first_name, last_interaction) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE last_interaction = NOW(), username = ?, first_name = ?");
    $stmt->execute([$userId, $username, $firstName, $username, $firstName]);
} catch (Exception $e) {}

// ============================================================
// 📋 COMMAND HANDLER - FULL LIST
// ============================================================

// ── START ──
if ($text === '/start') {
    $msg = "🔥 **" . $botName . "** 🔥\n\n";
    $msg .= "👑 Welcome " . ($isAdmin ? "Master" : ($isReseller ? "Reseller" : "Rebrander")) . "!\n";
    $msg .= "📱 User: @" . ($username ?: 'No username') . "\n";
    $msg .= "🆔 ID: `$userId`\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "📋 **GENERATE KEYS:**\n";
    $msg .= "/gen [amount] [time] - Generate random keys\n";
    $msg .= "/genkey [name] [time] - Generate custom key name\n";
    $msg .= "   Examples:\n";
    $msg .= "   /gen 5 7days\n";
    $msg .= "   /genkey SENSI-PRO-001 30days\n\n";
    $msg .= "📋 **KEY MANAGEMENT:**\n";
    $msg .= "/list - Show all keys\n";
    $msg .= "/check [key] - Check key status\n";
    $msg .= "/delete [key] - Delete key\n";
    $msg .= "/disable [key] - Disable key\n";
    $msg .= "/enable [key] - Enable key\n";
    $msg .= "/sell [key] [price] - Mark as sold\n";
    $msg .= "/renew [key] [time] - Extend expiry\n";
    $msg .= "/maxdev [key] [number] - Set max devices\n";
    $msg .= "/hwid [key] [hwid] - Lock to specific device\n";
    $msg .= "/timer [key] - Show expiry countdown\n\n";
    $msg .= "🌍 **WORLD TIMES:**\n";
    $msg .= "/worldtimes - Show all countries time\n\n";
    $msg .= "🔍 **ADVANCED:**\n";
    $msg .= "/search [term] - Search keys\n";
    $msg .= "/export - Export all keys\n";
    $msg .= "/bulkdelete expired - Delete expired keys\n";
    $msg .= "/profile - Show bot profile\n\n";
    
    if ($isAdmin) {
        $msg .= "👑 **ADMIN COMMANDS:**\n";
        $msg .= "/stats - Show statistics\n";
        $msg .= "/broadcast [message] - Send to all users\n";
        $msg .= "/addreseller [user_id] [name] - Add reseller\n";
        $msg .= "/removereseller [user_id] - Remove reseller\n";
        $msg .= "/listresellers - Show all resellers\n";
        $msg .= "/addrebrander [user_id] [name] - Add rebrander\n";
        $msg .= "/removerebrander [user_id] - Remove rebrander\n";
        $msg .= "/listrebranders - Show all rebranders\n";
        $msg .= "/setbotname [name] - Change bot name\n";
        $msg .= "/setbotdesc [desc] - Change bot description\n";
        $msg .= "/addcountry [flag] [name] [timezone] - Add country\n";
        $msg .= "/removecountry [name] - Remove country\n";
        $msg .= "/setrebrander [user_id] - Set user as rebrander\n";
    }
    
    if ($isRebrander) {
        $msg .= "🎨 **REBRANDER COMMANDS:**\n";
        $msg .= "/setbotname [name] - Change bot name\n";
        $msg .= "/setbotdesc [desc] - Change bot description\n";
        $msg .= "(Send a photo to change bot profile picture)\n";
    }
    
    $msg .= "\n━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "⚡ Powered by SENSI MODS";
    
    sendMessage($chatId, $msg);
    exit;
}

// ── GENERATE KEYS ──
if (strpos($text, '/gen') === 0 && !strpos($text, '/genkey')) {
    $parts = explode(' ', $text);
    $amount = (int)($parts[1] ?? 1);
    $time = $parts[2] ?? '1day';
    
    if ($isReseller && $amount > 10) {
        sendMessage($chatId, "⚠️ Resellers can only generate up to 10 keys.");
        exit;
    }
    
    if ($amount > 100) {
        sendMessage($chatId, "⚠️ Maximum 100 keys per generation.");
        exit;
    }
    
    preg_match('/(\d+)(\w+)/', $time, $matches);
    $value = $matches[1] ?? 1;
    $unit = $matches[2] ?? 'days';
    
    $unitMap = ['minutes'=>'minutes','minute'=>'minutes','hours'=>'hours','hour'=>'hours','days'=>'days','day'=>'days','months'=>'months','month'=>'months','years'=>'years','year'=>'years','week'=>'weeks','weeks'=>'weeks'];
    $unitSingular = $unitMap[strtolower($unit)] ?? 'days';
    
    $expiry = date('Y-m-d H:i:s', strtotime("+$value $unitSingular"));
    $generated = [];
    
    try {
        $pdo = db();
        for ($i = 0; $i < $amount; $i++) {
            $key = 'SENSI-' . strtoupper(substr(md5(uniqid() . mt_rand() . microtime()), 0, 8));
            $stmt = $pdo->prepare("INSERT INTO licenses (license_key, expires_at, status, max_devices) VALUES (?, ?, 'active', 1)");
            $stmt->execute([$key, $expiry]);
            $generated[] = $key;
        }
        
        $msg = "✅ **" . $amount . " KEYS GENERATED**\n\n";
        $msg .= "⏰ Expires: `$expiry`\n";
        $msg .= "📅 Duration: **$value $unitSingular**\n\n";
        $msg .= "🔑 **Keys:**\n";
        foreach ($generated as $k) {
            $msg .= "`$k`\n";
        }
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── GENERATE CUSTOM KEY NAME ──
if (strpos($text, '/genkey') === 0) {
    $parts = explode(' ', $text);
    $customKey = $parts[1] ?? '';
    $time = $parts[2] ?? '7days';
    
    if (empty($customKey)) {
        sendMessage($chatId, "❌ Usage: /genkey SENSI-CUSTOM-001 30days");
        exit;
    }
    
    preg_match('/(\d+)(\w+)/', $time, $matches);
    $value = $matches[1] ?? 7;
    $unit = $matches[2] ?? 'days';
    
    $unitMap = ['minutes'=>'minutes','minute'=>'minutes','hours'=>'hours','hour'=>'hours','days'=>'days','day'=>'days','months'=>'months','month'=>'months','years'=>'years','year'=>'years'];
    $unitSingular = $unitMap[strtolower($unit)] ?? 'days';
    
    $expiry = date('Y-m-d H:i:s', strtotime("+$value $unitSingular"));
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id FROM licenses WHERE license_key = ?");
        $stmt->execute([$customKey]);
        if ($stmt->fetch()) {
            sendMessage($chatId, "❌ Key `$customKey` already exists!");
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO licenses (license_key, expires_at, status, max_devices) VALUES (?, ?, 'active', 1)");
        $stmt->execute([$customKey, $expiry]);
        
        $msg = "✅ **CUSTOM KEY GENERATED**\n\n";
        $msg .= "🔑 `$customKey`\n";
        $msg .= "⏰ Expires: `$expiry`\n";
        $msg .= "📅 Duration: **$value $unitSingular**";
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── LIST KEYS ──
if ($text === '/list') {
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT license_key, status, expires_at, max_devices, device_count FROM licenses ORDER BY created_at DESC LIMIT 20");
        $keys = $stmt->fetchAll();
        
        if (empty($keys)) {
            sendMessage($chatId, "📭 No keys found.");
            exit;
        }
        
        $msg = "📋 **KEY LIST** (Last 20):\n\n";
        foreach ($keys as $k) {
            $msg .= "🔑 `" . $k['license_key'] . "`\n";
            $msg .= "   Status: " . $k['status'] . "\n";
            $msg .= "   Expires: " . $k['expires_at'] . "\n";
            $msg .= "   Devices: " . $k['device_count'] . "/" . $k['max_devices'] . "\n\n";
        }
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── CHECK KEY ──
if (strpos($text, '/check') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    
    if (empty($key)) {
        sendMessage($chatId, "❌ Usage: /check SENSI-XXXX");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            sendMessage($chatId, "❌ Key `$key` not found.");
            exit;
        }
        
        $msg = "🔍 **KEY CHECK:**\n\n";
        $msg .= "🔑 `" . $result['license_key'] . "`\n";
        $msg .= "📊 Status: " . $result['status'] . "\n";
        $msg .= "⏰ Expires: " . $result['expires_at'] . "\n";
        $msg .= "📱 Devices: " . $result['device_count'] . "/" . $result['max_devices'] . "\n";
        $msg .= "🆔 HWID: " . ($result['hwid'] ?: 'Not used yet');
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── DELETE KEY ──
if (strpos($text, '/delete') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    
    if (empty($key)) {
        sendMessage($chatId, "❌ Usage: /delete SENSI-XXXX");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM licenses WHERE license_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->rowCount() > 0) {
            sendMessage($chatId, "🗑️ Key `$key` deleted permanently.");
        } else {
            sendMessage($chatId, "❌ Key `$key` not found.");
        }
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── DISABLE KEY ──
if (strpos($text, '/disable') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    
    if (empty($key)) {
        sendMessage($chatId, "❌ Usage: /disable SENSI-XXXX");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE licenses SET status = 'disabled' WHERE license_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->rowCount() > 0) {
            sendMessage($chatId, "🔒 Key `$key` disabled.");
        } else {
            sendMessage($chatId, "❌ Key `$key` not found.");
        }
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── ENABLE KEY ──
if (strpos($text, '/enable') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    
    if (empty($key)) {
        sendMessage($chatId, "❌ Usage: /enable SENSI-XXXX");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE licenses SET status = 'active' WHERE license_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->rowCount() > 0) {
            sendMessage($chatId, "🔓 Key `$key` enabled.");
        } else {
            sendMessage($chatId, "❌ Key `$key` not found.");
        }
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── SELL KEY ──
if (strpos($text, '/sell') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    $price = $parts[2] ?? 'N/A';
    
    if (empty($key)) {
        sendMessage($chatId, "❌ Usage: /sell SENSI-XXXX \$10");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE licenses SET status = 'used' WHERE license_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->rowCount() > 0) {
            sendMessage($chatId, "💰 Key `$key` sold for **$price**!\n📊 Status: Used");
        } else {
            sendMessage($chatId, "❌ Key `$key` not found.");
        }
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── RENEW KEY ──
if (strpos($text, '/renew') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    $time = $parts[2] ?? '7days';
    
    if (empty($key)) {
        sendMessage($chatId, "❌ Usage: /renew SENSI-XXXX 30days");
        exit;
    }
    
    preg_match('/(\d+)(\w+)/', $time, $matches);
    $value = $matches[1] ?? 7;
    $unit = $matches[2] ?? 'days';
    
    $unitMap = ['minutes'=>'minutes','minute'=>'minutes','hours'=>'hours','hour'=>'hours','days'=>'days','day'=>'days','months'=>'months','month'=>'months','years'=>'years','year'=>'years'];
    $unitSingular = $unitMap[strtolower($unit)] ?? 'days';
    
    $expiry = date('Y-m-d H:i:s', strtotime("+$value $unitSingular"));
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE licenses SET expires_at = ?, status = 'active' WHERE license_key = ?");
        $stmt->execute([$expiry, $key]);
        
        if ($stmt->rowCount() > 0) {
            sendMessage($chatId, "🔄 Key `$key` renewed!\n⏰ New Expiry: `$expiry`\n📅 Duration: **$value $unitSingular**");
        } else {
            sendMessage($chatId, "❌ Key `$key` not found.");
        }
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── MAX DEVICES ──
if (strpos($text, '/maxdev') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    $maxDevices = (int)($parts[2] ?? 1);
    
    if (empty($key)) {
        sendMessage($chatId, "❌ Usage: /maxdev SENSI-XXXX 5");
        exit;
    }
    
    if ($maxDevices < 1 || $maxDevices > 999) {
        sendMessage($chatId, "❌ Max devices must be between 1 and 999.");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE licenses SET max_devices = ? WHERE license_key = ?");
        $stmt->execute([$maxDevices, $key]);
        
        if ($stmt->rowCount() > 0) {
            sendMessage($chatId, "📱 Key `$key` now allows **$maxDevices** devices.");
        } else {
            sendMessage($chatId, "❌ Key `$key` not found.");
        }
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── HWID LOCK ──
if (strpos($text, '/hwid') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    $hwid = $parts[2] ?? '';
    
    if (empty($key) || empty($hwid)) {
        sendMessage($chatId, "❌ Usage: /hwid SENSI-XXXX ABC123");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE licenses SET hwid = ?, status = 'used' WHERE license_key = ?");
        $stmt->execute([$hwid, $key]);
        
        if ($stmt->rowCount() > 0) {
            sendMessage($chatId, "🔒 Key `$key` locked to HWID: `$hwid`");
        } else {
            sendMessage($chatId, "❌ Key `$key` not found.");
        }
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── TIMER ──
if (strpos($text, '/timer') === 0) {
    $parts = explode(' ', $text);
    $key = $parts[1] ?? '';
    
    if (empty($key)) {
        sendMessage($chatId, "❌ Usage: /timer SENSI-XXXX");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT expires_at FROM licenses WHERE license_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            sendMessage($chatId, "❌ Key `$key` not found.");
            exit;
        }
        
        $expiry = strtotime($result['expires_at']);
        $now = time();
        $diff = $expiry - $now;
        
        if ($diff < 0) {
            sendMessage($chatId, "⏰ Key `$key` has **EXPIRED**! ❌");
            exit;
        }
        
        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);
        $seconds = $diff % 60;
        
        $msg = "⏰ **TIMER FOR KEY:** `$key`\n\n";
        $msg .= "📅 **$days** days\n";
        $msg .= "⏱️ **$hours** hours\n";
        $msg .= "⏱️ **$minutes** minutes\n";
        $msg .= "⏱️ **$seconds** seconds\n\n";
        $msg .= "📅 Expires: " . date('Y-m-d H:i:s', $expiry);
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── WORLD TIMES ──
if ($text === '/worldtimes') {
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT flag, name, tz FROM countries ORDER BY name");
        $countries = $stmt->fetchAll();
        
        if (empty($countries)) {
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
                ['flag' => '🇹🇳', 'name' => 'Tunisia', 'tz' => 'Africa/Tunis']
            ];
        }
        
        $msg = "🌍 **WORLD TIME ZONES**\n\n";
        foreach ($countries as $c) {
            try {
                $tz = new DateTimeZone($c['tz']);
                $now = new DateTime('now', $tz);
                $time = $now->format('g:i:s A');
                $msg .= $c['flag'] . " " . $c['name'] . ": `" . $time . "`\n";
            } catch (Exception $e) {}
        }
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── SEARCH KEYS ──
if (strpos($text, '/search') === 0) {
    $parts = explode(' ', $text);
    array_shift($parts);
    $term = implode(' ', $parts);
    
    if (empty($term)) {
        sendMessage($chatId, "❌ Usage: /search SENSI");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key LIKE ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute(['%' . $term . '%']);
        $results = $stmt->fetchAll();
        
        if (empty($results)) {
            sendMessage($chatId, "🔍 No keys found for `$term`");
            exit;
        }
        
        $msg = "🔍 **SEARCH RESULTS:**\n\n";
        foreach ($results as $r) {
            $msg .= "🔑 `" . $r['license_key'] . "`\n";
            $msg .= "   Status: " . $r['status'] . "\n";
            $msg .= "   Expires: " . $r['expires_at'] . "\n\n";
        }
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── EXPORT ──
if ($text === '/export') {
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT * FROM licenses ORDER BY created_at DESC");
        $keys = $stmt->fetchAll();
        
        if (empty($keys)) {
            sendMessage($chatId, "📭 No keys to export.");
            exit;
        }
        
        $filename = "licenses_" . date('Y-m-d') . ".txt";
        $content = "🔥 SENSI MODS - LICENSE EXPORT\n";
        $content .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
        $content .= str_repeat("═", 50) . "\n\n";
        
        foreach ($keys as $k) {
            $content .= "🔑 Key: " . $k['license_key'] . "\n";
            $content .= "📊 Status: " . $k['status'] . "\n";
            $content .= "⏰ Expires: " . $k['expires_at'] . "\n";
            $content .= "📱 Devices: " . $k['device_count'] . "/" . $k['max_devices'] . "\n";
            $content .= "🆔 HWID: " . ($k['hwid'] ?: 'Not used') . "\n";
            $content .= str_repeat("─", 30) . "\n";
        }
        
        sendDocument($chatId, $content, $filename);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── BULK DELETE ──
if (strpos($text, '/bulkdelete') === 0) {
    $parts = explode(' ', $text);
    $type = $parts[1] ?? '';
    
    if ($type === 'expired') {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("DELETE FROM licenses WHERE expires_at < NOW()");
            $stmt->execute();
            $count = $stmt->rowCount();
            sendMessage($chatId, "🗑️ Deleted **$count** expired keys.");
        } catch (Exception $e) {
            sendMessage($chatId, "❌ Error: " . $e->getMessage());
        }
    } else {
        sendMessage($chatId, "❌ Usage: /bulkdelete expired");
    }
    exit;
}

// ── PROFILE ──
if ($text === '/profile') {
    try {
        $pdo = db();
        $totalKeys = $pdo->query("SELECT COUNT(*) FROM licenses")->fetchColumn();
        $activeKeys = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'active'")->fetchColumn();
        $usedKeys = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'used'")->fetchColumn();
        $disabledKeys = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'disabled'")->fetchColumn();
        $resellerCount = $pdo->query("SELECT COUNT(*) FROM resellers")->fetchColumn();
        $rebranderCount = $pdo->query("SELECT COUNT(*) FROM rebranders")->fetchColumn();
        $userCount = $pdo->query("SELECT COUNT(*) FROM interactions")->fetchColumn();
        
        $msg = "👤 **BOT PROFILE**\n\n";
        $msg .= "📛 Name: `" . $botName . "`\n";
        $msg .= "📝 Desc: " . $botDescription . "\n";
        $msg .= "🆔 Admin: `$adminId`\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📊 **STATISTICS**\n";
        $msg .= "🔑 Total Keys: **$totalKeys**\n";
        $msg .= "🟢 Active: **$activeKeys**\n";
        $msg .= "🟡 Used: **$usedKeys**\n";
        $msg .= "🔴 Disabled: **$disabledKeys**\n";
        $msg .= "👑 Resellers: **$resellerCount**\n";
        $msg .= "🎨 Rebranders: **$rebranderCount**\n";
        $msg .= "👤 Users: **$userCount**\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚡ SENSI MODS PRO";
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── STATS ──
if ($text === '/stats') {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    try {
        $pdo = db();
        $totalKeys = $pdo->query("SELECT COUNT(*) FROM licenses")->fetchColumn();
        $activeKeys = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'active'")->fetchColumn();
        $usedKeys = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'used'")->fetchColumn();
        $disabledKeys = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'disabled'")->fetchColumn();
        $resellerCount = $pdo->query("SELECT COUNT(*) FROM resellers")->fetchColumn();
        $rebranderCount = $pdo->query("SELECT COUNT(*) FROM rebranders")->fetchColumn();
        $userCount = $pdo->query("SELECT COUNT(*) FROM interactions")->fetchColumn();
        
        $msg = "📊 **BOT STATISTICS**\n\n";
        $msg .= "🔑 Total Keys: **$totalKeys**\n";
        $msg .= "🟢 Active: **$activeKeys**\n";
        $msg .= "🟡 Used: **$usedKeys**\n";
        $msg .= "🔴 Disabled: **$disabledKeys**\n";
        $msg .= "👑 Resellers: **$resellerCount**\n";
        $msg .= "🎨 Rebranders: **$rebranderCount**\n";
        $msg .= "👤 Users: **$userCount**\n";
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── BROADCAST ──
if (strpos($text, '/broadcast') === 0) {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    array_shift($parts);
    $message = implode(' ', $parts);
    
    if (empty($message)) {
        sendMessage($chatId, "❌ Usage: /broadcast Hello everyone!");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT DISTINCT user_id FROM interactions");
        $users = $stmt->fetchAll();
        
        $sent = 0;
        foreach ($users as $user) {
            sendMessage($user['user_id'], "📢 **BROADCAST**\n\n$message");
            $sent++;
        }
        
        sendMessage($chatId, "✅ Broadcast sent to **$sent** users.");
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── ADD RESELLER ──
if (strpos($text, '/addreseller') === 0) {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    $resellerId = $parts[1] ?? '';
    $resellerName = $parts[2] ?? 'Reseller';
    
    if (empty($resellerId)) {
        sendMessage($chatId, "❌ Usage: /addreseller USER_ID NAME");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO resellers (user_id, name, status) VALUES (?, ?, 'active')");
        $stmt->execute([$resellerId, $resellerName]);
        sendMessage($chatId, "✅ Reseller **$resellerName** added!\n🆔 User ID: `$resellerId`");
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── REMOVE RESELLER ──
if (strpos($text, '/removereseller') === 0) {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    $resellerId = $parts[1] ?? '';
    
    if (empty($resellerId)) {
        sendMessage($chatId, "❌ Usage: /removereseller USER_ID");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM resellers WHERE user_id = ?");
        $stmt->execute([$resellerId]);
        sendMessage($chatId, "🗑️ Reseller removed.");
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── LIST RESELLERS ──
if ($text === '/listresellers') {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT * FROM resellers");
        $resellers = $stmt->fetchAll();
        
        if (empty($resellers)) {
            sendMessage($chatId, "📭 No resellers found.");
            exit;
        }
        
        $msg = "👑 **RESELLER LIST:**\n\n";
        foreach ($resellers as $r) {
            $msg .= "🆔 User ID: `" . $r['user_id'] . "`\n";
            $msg .= "📛 Name: " . $r['name'] . "\n";
            $msg .= "📊 Status: " . $r['status'] . "\n\n";
        }
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── ADD REBRANDER ──
if (strpos($text, '/addrebrander') === 0) {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    $rebranderId = $parts[1] ?? '';
    $rebranderName = $parts[2] ?? 'Rebrander';
    
    if (empty($rebranderId)) {
        sendMessage($chatId, "❌ Usage: /addrebrander USER_ID NAME");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO rebranders (user_id, name, status) VALUES (?, ?, 'active')");
        $stmt->execute([$rebranderId, $rebranderName]);
        sendMessage($chatId, "✅ Rebrander **$rebranderName** added!\n🆔 User ID: `$rebranderId`");
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── REMOVE REBRANDER ──
if (strpos($text, '/removerebrander') === 0) {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    $rebranderId = $parts[1] ?? '';
    
    if (empty($rebranderId)) {
        sendMessage($chatId, "❌ Usage: /removerebrander USER_ID");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM rebranders WHERE user_id = ?");
        $stmt->execute([$rebranderId]);
        sendMessage($chatId, "🗑️ Rebrander removed.");
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── LIST REBRANDERS ──
if ($text === '/listrebranders') {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT * FROM rebranders");
        $rebranders = $stmt->fetchAll();
        
        if (empty($rebranders)) {
            sendMessage($chatId, "📭 No rebranders found.");
            exit;
        }
        
        $msg = "🎨 **REBRANDER LIST:**\n\n";
        foreach ($rebranders as $r) {
            $msg .= "🆔 User ID: `" . $r['user_id'] . "`\n";
            $msg .= "📛 Name: " . $r['name'] . "\n";
            $msg .= "📊 Status: " . $r['status'] . "\n\n";
        }
        sendMessage($chatId, $msg);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── SET BOT NAME ──
if (strpos($text, '/setbotname') === 0) {
    if (!$isAdmin && !$isRebrander) {
        sendMessage($chatId, "⛔ Admin or Rebrander only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    array_shift($parts);
    $newName = implode(' ', $parts);
    
    if (empty($newName)) {
        sendMessage($chatId, "❌ Usage: /setbotname 🔥 SENSI MODS PRO");
        exit;
    }
    
    $url = "https://api.telegram.org/bot$botToken/setMyName?name=" . urlencode($newName);
    $response = file_get_contents($url);
    
    if (strpos($response, '"ok":true') !== false) {
        sendMessage($chatId, "✅ Bot name updated to:\n**$newName**");
    } else {
        sendMessage($chatId, "❌ Failed to update bot name.");
    }
    exit;
}

// ── SET BOT DESCRIPTION ──
if (strpos($text, '/setbotdesc') === 0) {
    if (!$isAdmin && !$isRebrander) {
        sendMessage($chatId, "⛔ Admin or Rebrander only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    array_shift($parts);
    $newDesc = implode(' ', $parts);
    
    if (empty($newDesc)) {
        sendMessage($chatId, "❌ Usage: /setbotdesc My new description");
        exit;
    }
    
    $url = "https://api.telegram.org/bot$botToken/setMyDescription?description=" . urlencode($newDesc);
    $response = file_get_contents($url);
    
    if (strpos($response, '"ok":true') !== false) {
        sendMessage($chatId, "✅ Bot description updated!");
    } else {
        sendMessage($chatId, "❌ Failed to update bot description.");
    }
    exit;
}

// ── SET BOT PHOTO ──
if ($photo) {
    if (!$isAdmin && !$isRebrander) {
        sendMessage($chatId, "⛔ Admin or Rebrander only.");
        exit;
    }
    
    $fileId = $photo[count($photo) - 1]['file_id'];
    $url = "https://api.telegram.org/bot$botToken/setMyPhoto?photo=$fileId";
    $response = file_get_contents($url);
    
    if (strpos($response, '"ok":true') !== false) {
        sendMessage($chatId, "✅ Bot profile photo updated!");
    } else {
        sendMessage($chatId, "❌ Failed to update bot photo.");
    }
    exit;
}

// ── ADD COUNTRY ──
if (strpos($text, '/addcountry') === 0) {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    $flag = $parts[1] ?? '🇳🇬';
    $name = $parts[2] ?? '';
    $tz = $parts[3] ?? 'Africa/Lagos';
    
    if (empty($name)) {
        sendMessage($chatId, "❌ Usage: /addcountry 🇳🇬 Nigeria Africa/Lagos");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO countries (flag, name, tz) VALUES (?, ?, ?)");
        $stmt->execute([$flag, $name, $tz]);
        sendMessage($chatId, "✅ Country **$name** added to world times!");
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── REMOVE COUNTRY ──
if (strpos($text, '/removecountry') === 0) {
    if (!$isAdmin) {
        sendMessage($chatId, "⛔ Admin only.");
        exit;
    }
    
    $parts = explode(' ', $text);
    $name = $parts[1] ?? '';
    
    if (empty($name)) {
        sendMessage($chatId, "❌ Usage: /removecountry Nigeria");
        exit;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM countries WHERE name = ?");
        $stmt->execute([$name]);
        sendMessage($chatId, "🗑️ Country **$name** removed from world times.");
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
    exit;
}

// ── UNKNOWN COMMAND ──
sendMessage($chatId, "❌ Unknown command. Send /start for help.");

// ============================================================
// 📤 SEND MESSAGE FUNCTION
// ============================================================
function sendMessage($chatId, $text) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ];
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];
    file_get_contents($url, false, stream_context_create($options));
}

// ── SEND DOCUMENT ──
function sendDocument($chatId, $content, $filename) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/sendDocument";
    $boundary = "WebAppBoundary" . md5(time());
    
    $data = "--$boundary\r\n";
    $data .= "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n$chatId\r\n";
    $data .= "--$boundary\r\n";
    $data .= "Content-Disposition: form-data; name=\"document\"; filename=\"$filename\"\r\n";
    $data .= "Content-Type: text/plain\r\n\r\n";
    $data .= $content . "\r\n";
    $data .= "--$boundary--\r\n";
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: multipart/form-data; boundary=$boundary\r\n",
            'content' => $data
        ]
    ];
    file_get_contents($url, false, stream_context_create($options));
}
?>