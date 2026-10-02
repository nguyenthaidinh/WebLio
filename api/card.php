<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../server_config.php';
require_server_one_recharge(true);

header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
    'success' => false,
    'message' => 'Nap the tu dong da tat. Vui long chuyen khoan va gui yeu cau de admin duyet.'
]);
?>
