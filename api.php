<?php
// ============================================
// SENSI MODS - COMPLETE API
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'config.php';

date_default_timezone_set('UTC');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ── SUPPORT POST JSON ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if ($data) {
        $_GET['key'] = $data['key'] ?? $_GET['key'] ?? null;
        $_GET['hwid'] = $data['hwid'] ?? $_GET['hwid'] ?? null;
        $_GET['timezone'] = $data['timezone'] ?? $_GET['timezone'] ?? 'UTC';
    }
}

$key = isset($_GET['key']) ? trim($_GET['key']) : null;
$hwid = isset($_GET['hwid']) ? trim($_GET['hwid']) : null;
$timezone = isset($_GET['timezone']) ? trim($_GET['timezone']) : 'UTC';

// ── AUTO-DELETE EXPIRED ──
try {
    $pdo = db();
    $pdo->prepare("DELETE FROM licenses WHERE expires_at < NOW() AND status != 'deleted'")->execute();
} catch (Exception $e) {}

// ── TEST ENDPOINT ──
if ($action === 'test') {
    echo json_encode([
        'success' => true,
        'message' => 'API working on Render PostgreSQL!',
        'server' => $_SERVER['SERVER_NAME'],
        'version' => APP_VERSION
    ]);
    exit;
}

// ── AUTHENTICATION ──
if ($action === 'auth') {
    if (empty($key)) {
        echo json_encode(['success' => false, 'error' => 'Missing license key']);
        exit;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
        $stmt->execute([$key]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$license) {
            echo json_encode(['success' => false, 'error' => 'Invalid key']);
            exit;
        }

        if ($license['status'] === 'disabled') {
            echo json_encode(['success' => false, 'error' => 'Key is disabled']);
            exit;
        }

        // ── CHECK EXPIRY ──
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $expires = new DateTime($license['expires_at'], new DateTimeZone('UTC'));
        if ($now > $expires) {
            $pdo->prepare("DELETE FROM licenses WHERE id = ?")->execute([$license['id']]);
            echo json_encode(['success' => false, 'error' => 'Key expired and deleted']);
            exit;
        }

        // ── CHECK DEVICE COUNT ──
        $device_count = (int)$license['device_count'];
        $max_devices = (int)$license['max_devices'];

        $check_hwid = $pdo->prepare("SELECT id FROM licenses WHERE license_key = ? AND hwid = ?");
        $check_hwid->execute([$key, $hwid]);
        $hwid_exists = $check_hwid->fetch();

        if (!$hwid_exists && !empty($hwid) && $device_count >= $max_devices) {
            echo json_encode(['success' => false, 'error' => 'Max devices reached (' . $max_devices . ')']);
            exit;
        }

        // ── UPDATE OR INSERT HWID ──
        if (!$hwid_exists && !empty($hwid)) {
            $update = $pdo->prepare("UPDATE licenses SET hwid = ?, device_count = device_count + 1, status = 'used' WHERE id = ?");
            $update->execute([$hwid, $license['id']]);
        }

        // ── GET UPDATED LICENSE ──
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
        $stmt->execute([$license['id']]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'seller' => SELLER_NAME,
            'expires_at' => $license['expires_at'],
            'timezone' => $timezone,
            'max_devices' => (int)$license['max_devices'],
            'device_count' => (int)$license['device_count'],
            'message' => 'Authenticated'
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// ── FEATURES ──
if ($action === 'features') {
    echo json_encode([
        'success' => true,
        'features' => [
            'aimbot' => true,
            'silent_aim' => true,
            'esp' => true,
            'box' => true,
            'health' => true,
            'line' => true,
            'name' => true,
            'alert' => true,
            'distance' => true,
            'speed' => true,
            'spinbot' => true
        ]
    ]);
    exit;
}

// ── DEFAULT ──
echo json_encode(['success' => false, 'error' => 'Unknown action']);
exit;
?>