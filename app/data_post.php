<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../forum_post_helpers.php';

$_alert = '';
$post_id = null;
$post_detail = null;

$logged_in_username = $_SESSION['username'] ?? $_SESSION['account'] ?? null;
$logged_in_username = is_string($logged_in_username) && $logged_in_username !== '' ? $logged_in_username : null;
$logged_in_player_name = null;
$is_admin = 0;

if ($logged_in_username !== null && isset($conn)) {
    $stmt_admin_check = $conn->prepare("SELECT a.admin, a.is_admin, p.name AS player_name FROM account a LEFT JOIN player p ON p.account_id = a.id WHERE a.username = ? LIMIT 1");
    if ($stmt_admin_check) {
        $stmt_admin_check->bind_param("s", $logged_in_username);
        $stmt_admin_check->execute();
        $result_admin_check = $stmt_admin_check->get_result();
        if ($result_admin_check->num_rows > 0) {
            $user_info = $result_admin_check->fetch_assoc();
            $is_admin = (int)($user_info['admin'] ?? 0) === 1 || (int)($user_info['is_admin'] ?? 0) === 1;
            $logged_in_player_name = isset($user_info['player_name']) ? (string)$user_info['player_name'] : null;
        }
        $stmt_admin_check->close();
    }
}

if (isset($_GET['id'])) {
    if (filter_var($_GET['id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))) {
        $post_id = intval($_GET['id']);
    } else {
        $_alert = "<div class='alert alert-danger'>ID bài viết không hợp lệ.</div>";
    }
}

if ($post_id !== null && isset($conn)) {
    $stmt = $conn->prepare("SELECT id, tieude, noidung, username, image FROM posts WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $post_detail = $result->fetch_assoc();

            if (!forum_post_can_edit((string)$post_detail['username'], $logged_in_username, $logged_in_player_name, (bool)$is_admin)) {
                $_alert = "<div class='alert alert-danger'>Bạn không có quyền chỉnh sửa bài viết này.</div>";
                $post_detail = null;
            } else {
                $post_detail['tieude'] = forum_post_normalize_legacy_text($post_detail['tieude'] ?? '');
                $post_detail['noidung'] = forum_post_normalize_legacy_text($post_detail['noidung'] ?? '');
            }
        } else {
            $_alert = "<div class='alert alert-danger'>Bài viết không tồn tại.</div>";
        }
        $stmt->close();
    } else {
        $_alert = "<div class='alert alert-danger'>Đã xảy ra lỗi khi tải bài viết. Vui lòng thử lại sau.</div>";
    }
} else {
    if ($logged_in_username === null) {
        $_alert = "<div class='alert alert-warning'>Vui lòng đăng nhập để chỉnh sửa bài viết.</div>";
    } else {
        $_alert = "<div class='alert alert-info'>Vui lòng cung cấp ID bài viết để chỉnh sửa.</div>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit'])) {
    if (!forum_post_verify_csrf($_POST['csrf_token'] ?? null)) {
        $_alert = "<div class='alert alert-danger'>Phiên bảo mật không hợp lệ. Vui lòng tải lại trang rồi thử lại.</div>";
    } elseif ($logged_in_username === null) {
        $_alert = "<div class='alert alert-danger'>Bạn cần đăng nhập để thực hiện hành động này.</div>";
    } elseif ($post_detail === null) {
        $_alert = "<div class='alert alert-danger'>Không thể chỉnh sửa bài viết không tồn tại hoặc không có quyền.</div>";
    } else {
        if (!forum_post_can_edit((string)$post_detail['username'], $logged_in_username, $logged_in_player_name, (bool)$is_admin)) {
            $_alert = "<div class='alert alert-danger'>Bạn không có quyền chỉnh sửa bài viết này.</div>";
        } else {
            $new_tieude = isset($_POST['tieude']) && is_string($_POST['tieude']) ? trim($_POST['tieude']) : '';
            $new_noidung = isset($_POST['noidung']) && is_string($_POST['noidung']) ? trim($_POST['noidung']) : '';
            $post_id_from_form = filter_var(
                $_POST['post_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (forum_post_text_length($new_tieude) < 5) {
                $_alert = "<div class='alert alert-danger'>Tiêu đề bài viết phải có ít nhất 5 ký tự.</div>";
            } elseif (forum_post_text_length($new_tieude) > 75) {
                $_alert = "<div class='alert alert-danger'>Tiêu đề bài viết không được vượt quá 75 ký tự.</div>";
            } elseif (forum_post_text_length(trim(strip_tags($new_noidung))) < 10) {
                $_alert = "<div class='alert alert-danger'>Nội dung bài viết phải có ít nhất 10 ký tự.</div>";
            } elseif (strlen($new_noidung) > 60000) {
                $_alert = "<div class='alert alert-danger'>Nội dung bài viết quá dài.</div>";
            } elseif ($post_id_from_form === false || $post_id_from_form !== $post_id) {
                $_alert = "<div class='alert alert-danger'>ID bài viết không khớp.</div>";
            } else {
                $stmt_update = $conn->prepare("UPDATE posts SET tieude = ?, noidung = ? WHERE id = ?");
                if ($stmt_update) {
                    $stmt_update->bind_param("ssi", $new_tieude, $new_noidung, $post_id);
                    if ($stmt_update->execute()) {
                        forum_post_set_flash('success', 'Bài viết đã được cập nhật thành công.');
                        header("Location: /forum.php");
                        exit();
                    } else {
                        $_alert = "<div class='alert alert-danger'>Lỗi khi cập nhật bài viết: " . htmlspecialchars($stmt_update->error) . "</div>";
                    }
                    $stmt_update->close();
                } else {
                    $_alert = "<div class='alert alert-danger'>Lỗi chuẩn bị câu lệnh SQL để cập nhật: " . htmlspecialchars($conn->error) . "</div>";
                }
            }
        }
    }
}

if (isset($conn)) {
    mysqli_close($conn);
}
?>
