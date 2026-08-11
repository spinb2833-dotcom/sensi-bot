<?php
// ============================================
// SENSI MODS - COMPLETE CONFIGURATION
// ============================================

// ── APP SETTINGS ──
define('APP_NAME', 'SENSI MODS');
define('APP_VERSION', '2.0');
define('KEY_PREFIX', 'SENSI');
define('MAX_GENERATE', 20);
define('SELLER_NAME', 'SenseiDev');

// ── ADMIN LOGIN ──
define('ADMIN_USERNAME', 'sensei');
define('ADMIN_PASSWORD', 'sensei');

// ── TELEGRAM BOT ──
define('BOT_TOKEN', '8848564612:AAFj3ueTQ4AvMaXu-KYqeLj_sjUAxU40dUI');
define('BOT_ADMIN_ID', '987322');
define('BOT_USERNAME', '@Sensimodsadminconrol_bot');

// ── WHATSAPP CHANNEL ──
define('WA_CHANNEL', 'https://whatsapp.com/channel/0029Va...');

// ── TIMEZONE ──
date_default_timezone_set('UTC');

// ── SESSION ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── CORS HEADERS ──
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ── ERROR REPORTING ──
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
?>