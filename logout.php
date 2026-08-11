<?php
// ============================================
// SENSI MODS - LOGOUT
// ============================================

session_start();

// ── CLEAR ALL SESSION DATA ──
$_SESSION = array();

// ── DESTROY SESSION ──
session_destroy();

// ── REDIRECT TO LOGIN ──
header('Location: index.php');
exit;
?>