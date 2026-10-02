<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/server_config.php';
require_server_one_recharge(false);

header("Location: /app/nap-ngoc.php");
exit();
?>
