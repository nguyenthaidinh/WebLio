<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_socket_timeout', '5');
ini_set('mysqlnd.net_read_timeout', '5');

$is_post_request = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($is_post_request) {
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=UTF-8');
}

if (isset($_SESSION['user_id'])) {
    if ($is_post_request) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Bạn đã đăng nhập rồi, đang chuyển về diễn đàn.',
            'redirect' => '/forum.php'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: /forum.php');
    }
    exit();
}

require_once __DIR__ . '/../server_config.php';

$serverId = (string)($_POST['server'] ?? '1');
$serverConfig = game_server_config($serverId);

if ($serverConfig === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server bạn chọn không hợp lệ.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$host = $serverConfig['host'];
$port = $serverConfig['port'];
$dbname = $serverConfig['database'];
$user = $serverConfig['username'];
$pass = $serverConfig['password'];
$pdo = null;

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_TIMEOUT => 5]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
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
                    'message' => 'Đăng nhập thành công! Chúc bạn chơi game vui vẻ.',
                    'redirect' => '/forum.php'
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
    } elseif ($action === 'register') {
        $username = trim($_POST['user'] ?? '');
        $password = $_POST['pass'] ?? '';
        $rePassword = $_POST['repass'] ?? '';
        $server = $_POST['server'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'];

        $email = '';
        if (empty($username) || empty($password) || empty($rePassword) || empty($server)) {
            echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ các trường bắt buộc (Tài Khoản, Mật khẩu, Nhập lại Mật khẩu, và Server).']);
            exit();
        }
        if ($password !== $rePassword) {
            echo json_encode(['status' => 'error', 'message' => 'Mật khẩu xác nhận không khớp.']);
            exit();
        }
        if (strlen($username) < 3 || strlen($username) > 20) {
            echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập phải có từ 3 đến 20 ký tự.']);
            exit();
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới.']);
            exit();
        }
        if (strlen($password) < 6) {
            echo json_encode(['status' => 'error', 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.']);
            exit();
        }

        try {
            // Kiểm tra tên đăng nhập đã tồn tại
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM account WHERE username = :username");
            $stmt->execute([':username' => $username]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.']);
                exit();
            }

            // Thêm tài khoản mới vào database
            $stmt = $pdo->prepare("INSERT INTO account (
                username, password, email, create_time, update_time, ban, is_admin,
                last_time_login, last_time_logout, ip_address, active, thoi_vang,
                server_login, bd_player, is_gift_box, gift_time, reward, vnd,
                tongnap, token, xsrf_token, newpass, luotquay, vang, event_point,
                vip, tichdiem, point_post, last_post, gioithieu, xacnhan_gioitheu,
                baiviet, xacminh, admin
            ) VALUES (
                :username, :password, :email, NOW(), NOW(), 0, 0,
                '2002-07-31 00:00:00', '2002-07-31 00:00:00', :ip_address, 1, 0,
                :server_login, 1, 0, '0', NULL, 0,
                0, '', '', '', 0, 0, 0,
                0, 0, 0, 0, NULL, 0,
                0, 0, 0
            )");

            $stmt->execute([
                ':username' => $username,
                ':password' => $password,
                ':email' => $email,
                ':ip_address' => $ip_address,
                ':server_login' => $server
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay bây giờ.']);
            exit();
        } catch (PDOException $e) {
            error_log("Lỗi đăng ký: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Đã xảy ra lỗi khi đăng ký. Vui lòng thử lại.']);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
        exit();
    }
}
?>
