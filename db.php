<?php
// ============================================
// SENSI MODS - COMPLETE DATABASE CONNECTION
// ============================================

$connectionString = getenv('DATABASE_URL');

if (!$connectionString) {
    die("DATABASE_URL environment variable not set!");
}

function db() {
    global $connectionString;
    try {
        $pdo = new PDO($connectionString);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

// ── CREATE TABLES IF NOT EXISTS ──
function initDatabase() {
    try {
        $pdo = db();
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS licenses (
                id SERIAL PRIMARY KEY,
                license_key VARCHAR(64) UNIQUE NOT NULL,
                status VARCHAR(20) DEFAULT 'active',
                expires_at TIMESTAMP NOT NULL,
                max_devices INT DEFAULT 1,
                device_count INT DEFAULT 0,
                used_by VARCHAR(255),
                hwid VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS resellers (
                id SERIAL PRIMARY KEY,
                user_id VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(100),
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rebranders (
                id SERIAL PRIMARY KEY,
                user_id VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(100),
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS interactions (
                id SERIAL PRIMARY KEY,
                user_id VARCHAR(50) UNIQUE NOT NULL,
                username VARCHAR(100),
                first_name VARCHAR(100),
                wa_joined INT DEFAULT 0,
                last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS countries (
                id SERIAL PRIMARY KEY,
                flag VARCHAR(10) NOT NULL,
                name VARCHAR(100) NOT NULL,
                tz VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // ── DEFAULT COUNTRIES ──
        $count = $pdo->query("SELECT COUNT(*) FROM countries")->fetchColumn();
        if ($count == 0) {
            $pdo->exec("
                INSERT INTO countries (flag, name, tz) VALUES 
                ('🇸🇦', 'Saudi Arabia', 'Asia/Riyadh'),
                ('🇦🇪', 'UAE', 'Asia/Dubai'),
                ('🇶🇦', 'Qatar', 'Asia/Qatar'),
                ('🇰🇼', 'Kuwait', 'Asia/Kuwait'),
                ('🇧🇭', 'Bahrain', 'Asia/Bahrain'),
                ('🇴🇲', 'Oman', 'Asia/Muscat'),
                ('🇯🇴', 'Jordan', 'Asia/Amman'),
                ('🇱🇧', 'Lebanon', 'Asia/Beirut'),
                ('🇮🇱', 'Israel', 'Asia/Jerusalem'),
                ('🇵🇸', 'Palestine', 'Asia/Gaza'),
                ('🇸🇾', 'Syria', 'Asia/Damascus'),
                ('🇮🇶', 'Iraq', 'Asia/Baghdad'),
                ('🇮🇷', 'Iran', 'Asia/Tehran'),
                ('🇹🇷', 'Turkey', 'Europe/Istanbul'),
                ('🇾🇪', 'Yemen', 'Asia/Aden'),
                ('🇪🇬', 'Egypt', 'Africa/Cairo'),
                ('🇱🇾', 'Libya', 'Africa/Tripoli'),
                ('🇩🇿', 'Algeria', 'Africa/Algiers'),
                ('🇲🇦', 'Morocco', 'Africa/Casablanca'),
                ('🇸🇩', 'Sudan', 'Africa/Khartoum'),
                ('🇹🇳', 'Tunisia', 'Africa/Tunis')
            ");
        }
        
        echo "✅ Database tables ready!\n";
    } catch (Exception $e) {
        echo "❌ DB init failed: " . $e->getMessage() . "\n";
    }
}

// ── RUN INIT ──
if (basename($_SERVER['PHP_SELF']) === 'db.php' && isset($_GET['init'])) {
    initDatabase();
    exit;
}
?>
