<?php
// ============================================
// SENSI MODS - CONFIGURATION
// ============================================

// ── TELEGRAM BOT ──
define('BOT_TOKEN', '8848564612:AAFj3ueTQ4AvMaXu-KYqeLj_sjUAxU40dUI');
define('BOT_ADMIN_ID', '987322');
define('BOT_USERNAME', '@Sensimodsadminconrol_bot');

// ── CODES ──
define('RESELLER_CODE', '444');
define('REBRANDER_CODE', '667');

// ── APP ──
define('APP_NAME', 'SENSI MODS');
define('APP_VERSION', '2.0');
define('SELLER_NAME', 'SenseiDev');

// ── TIMEZONE ──
date_default_timezone_set('UTC');

// ── SESSION ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── CORS ──
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
?>
