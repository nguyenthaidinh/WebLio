<?php

require_once __DIR__ . '/../server_config.php';
$serverOne = game_server_config('1');
$ip_sv = $serverOne['host'];
$port_sv = $serverOne['port'];
$dbname_sv = $serverOne['database'];
$user_sv = $serverOne['username'];
$pass_sv = $serverOne['password'];

//GMT +7

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Create connection

$conn = new mysqli($ip_sv, $user_sv, $pass_sv, $dbname_sv, $port_sv);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
