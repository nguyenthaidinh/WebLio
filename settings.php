<?php
// settings.php
$ip_sv = "103.67.197.241";
$port_sv = 14445;
$dbname_sv = "team2026";
$user_sv = "liodev";
$pass_sv = "liopass";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connect.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
?>
