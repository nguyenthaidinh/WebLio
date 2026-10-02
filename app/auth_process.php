<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('default_socket_timeout', '5');
ini_set('mysqlnd.net_read_timeout', '5');

$is_post_request = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($is_post_request) {
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=UTF-8');
}

if (isset($_SESSION['user_id'])) {
    $loggedServerId = (string)($_SESSION['server_id'] ?? '1');
    if ($is_post_request) {
        echo json_encode([
            'status' => 'success',
            'message' => $loggedServerId === '2'
                ? 'Bạn đã đăng nhập Server 2, đang chuyển về khu vực SV2.'
                : 'Bạn đã đăng nhập rồi, đang chuyển về diễn đàn.',
            'redirect' => $loggedServerId === '2' ? '/app/server-2.php' : '/forum.php'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: ' . ($loggedServerId === '2' ? '/app/server-2.php' : '/forum.php'));
    }
    exit();
}

require_once __DIR__ . '/../server_config.php';
require_once __DIR__ . '/account_service.php';

if ($is_post_request) {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['auth_csrf_token'] ?? '');
    if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Phiên bảo mật không hợp lệ. Vui lòng tải lại trang.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Keep backward compatibility for old registration forms while routing every
// registration through the database-locked handler.
if ($is_post_request && ($_POST['action'] ?? '') === 'register') {
    require __DIR__ . '/register_process.php';
    exit();
}

$serverId = (string)($_POST['server'] ?? '1');
$serverConfig = game_server_config($serverId);

if ($serverConfig === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server bạn chọn không hợp lệ.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$pdo = null;

try {
    $pdo = game_server_pdo($serverConfig);
} catch (PDOException $e) {
    error_log("Lỗi kết nối {$serverConfig['name']}: " . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode([
            'status' => 'error',
            'message' => "Không thể kết nối {$serverConfig['name']}. Vui lòng thử lại sau."
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo "<!DOCTYPE html><html><head><title>Lỗi</title></head><body><h1>Lỗi kết nối cơ sở dữ liệu.</h1><p>Vui lòng thử lại sau hoặc liên hệ quản trị viên.</p></body></html>";
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['user'] ?? '');
        $password = $_POST['pass'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập và mật khẩu không được để trống.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT id, username, password FROM account WHERE username = :username AND password = :password");
            $stmt->execute([
                ':username' => $username,
                ':password' => $password
            ]);
            $user = $stmt->fetch();

            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['id'] = $user['id'];
                $_SESSION['account'] = $user['username'];
                $_SESSION['server_id'] = $serverId;
                $_SESSION['server_name'] = $serverConfig['name'];
                $update_stmt = $pdo->prepare("UPDATE account SET last_time_login = NOW(), ip_address = :ip_address WHERE id = :id");
                $update_stmt->execute([
                    ':ip_address' => $_SERVER['REMOTE_ADDR'],
                    ':id' => $user['id']
                ]);
                echo json_encode([
                    'status' => 'success',
                    'message' => "Đăng nhập {$serverConfig['name']} thành công! Chúc bạn chơi game vui vẻ.",
                    'server_id' => $serverId,
                    'server_name' => $serverConfig['name'],
                    'redirect' => $serverId === '2' ? '/app/server-2.php' : '/forum.php'
                ]);
                exit();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
                exit();
            }
        } catch (PDOException $e) {
            error_log("Lỗi đăng nhập: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Đã xảy ra lỗi khi đăng nhập. Vui lòng thử lại.']);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
        exit();
    }
}
?>
