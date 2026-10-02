<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('default_socket_timeout', '5');
ini_set('mysqlnd.net_read_timeout', '5');
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../server_config.php';
require_once __DIR__ . '/account_service.php';

function registration_response(string $status, string $message, array $extra = [], int $httpStatus = 200): void
{
    http_response_code($httpStatus);
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'register') {
    registration_response('error', 'Yêu cầu đăng ký không hợp lệ.', [], 405);
}

if (!empty($_SESSION['user_id'])) {
    $loggedServerId = (string)($_SESSION['server_id'] ?? '1');
    registration_response('success', 'Bạn đã đăng nhập rồi.', [
        'redirect' => $loggedServerId === '2' ? '/app/server-2.php' : '/forum.php',
    ]);
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['auth_csrf_token'] ?? '');
if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
    registration_response('error', 'Phiên bảo mật không hợp lệ. Vui lòng tải lại trang.', [], 403);
}

$serverId = (string)($_POST['server'] ?? '');
if (!in_array($serverId, ['1', '2'], true)) {
    registration_response('error', 'Vui lòng chọn Server 1 hoặc Server 2.', [], 422);
}

$serverConfig = game_server_config($serverId);
$expectedDatabase = registration_database_name($serverId);
if ($serverConfig === null || (string)($serverConfig['database'] ?? '') !== $expectedDatabase) {
    error_log("Khóa đăng ký Server {$serverId}: cấu hình database không khớp {$expectedDatabase}.");
    registration_response('error', 'Cấu hình máy chủ đăng ký không an toàn. Thao tác đã bị khóa.', [], 503);
}

$username = trim((string)($_POST['user'] ?? ''));
$password = (string)($_POST['pass'] ?? '');
$rePassword = (string)($_POST['repass'] ?? '');
$ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($username === '' || $password === '' || $rePassword === '') {
    registration_response('error', 'Vui lòng điền đầy đủ tài khoản và mật khẩu.', [], 422);
}
if ($password !== $rePassword) {
    registration_response('error', 'Mật khẩu xác nhận không khớp.', [], 422);
}
if (strlen($username) < 3 || strlen($username) > 20) {
    registration_response('error', 'Tên đăng nhập phải có từ 3 đến 20 ký tự.', [], 422);
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    registration_response('error', 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới.', [], 422);
}
if (strlen($password) < 6) {
    registration_response('error', 'Mật khẩu phải có ít nhất 6 ký tự.', [], 422);
}

try {
    $pdo = game_server_pdo($serverConfig);

    // Verify the live connection too, not only the PHP configuration.
    assert_registration_database($pdo, $serverId);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM account WHERE username = :username');
    $stmt->execute([':username' => $username]);
    if ((int)$stmt->fetchColumn() > 0) {
        registration_response('error', "Tên đăng nhập đã tồn tại trên {$serverConfig['name']}.", [], 409);
    }

    register_game_account($pdo, $serverId, $username, $password, $ipAddress);
    registration_response('success', "Đăng ký tài khoản {$serverConfig['name']} thành công!", [
        'server_id' => $serverId,
        'server_name' => $serverConfig['name'],
        'redirect' => '/app/login.php?registered=1&server=' . rawurlencode($serverId),
    ]);
} catch (PDOException $e) {
    error_log("Lỗi đăng ký {$serverConfig['name']} ({$expectedDatabase}): " . $e->getMessage());
    registration_response('error', "Không thể kết nối {$serverConfig['name']}. Vui lòng thử lại sau.", [], 503);
} catch (Throwable $e) {
    error_log("Đã khóa đăng ký {$serverConfig['name']}: " . $e->getMessage());
    registration_response('error', 'Không thể xác minh đúng database của máy chủ. Thao tác đã bị khóa.', [], 503);
}
