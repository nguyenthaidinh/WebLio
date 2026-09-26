<?php
// connect.php
require_once __DIR__ . '/server_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$selected_server_id = current_game_server_id();
$selected_server = game_server_config($selected_server_id) ?? game_server_config('1');
$_SESSION['server_id'] = $selected_server_id;
$_SESSION['server_name'] = $selected_server['name'];

$ip_sv = $selected_server['host'];
$port_sv = $selected_server['port'];
$dbname_sv = $selected_server['database'];
$user_sv = $selected_server['username'];
$pass_sv = $selected_server['password'];

ini_set('default_socket_timeout', '5');
ini_set('mysqlnd.net_read_timeout', '5');
mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
$connected = $conn->real_connect($ip_sv, $user_sv, $pass_sv, $dbname_sv, $port_sv);

if (!$connected) {
    die("Lỗi kết nối database: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

date_default_timezone_set('Asia/Ho_Chi_Minh');
?>
