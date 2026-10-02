<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../server_config.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['username']) || current_game_server_id() !== '2') {
    header('Location: /app/login.php?server=2');
    exit();
}

$serverTwoNotice = (string)($_SESSION['server_two_notice'] ?? '');
unset($_SESSION['server_two_notice']);

require_once __DIR__ . '/../connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash = null;
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($postedToken === '' || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $flash = ['type' => 'error', 'message' => 'Phiên bảo mật không hợp lệ. Vui lòng tải lại trang.'];
    } elseif ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $flash = ['type' => 'error', 'message' => 'Vui lòng nhập đầy đủ các trường mật khẩu.'];
    } elseif (strlen($newPassword) < 6) {
        $flash = ['type' => 'error', 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.'];
    } elseif ($newPassword !== $confirmPassword) {
        $flash = ['type' => 'error', 'message' => 'Mật khẩu xác nhận không khớp.'];
    } else {
        $passwordStmt = $conn->prepare('SELECT password FROM account WHERE id = ? AND username = ? LIMIT 1');
        $passwordStmt->bind_param('is', $userId, $_SESSION['username']);
        $passwordStmt->execute();
        $passwordRow = $passwordStmt->get_result()->fetch_assoc();
        $passwordStmt->close();

        if (!$passwordRow || !hash_equals((string)$passwordRow['password'], $currentPassword)) {
            $flash = ['type' => 'error', 'message' => 'Mật khẩu hiện tại không đúng.'];
        } else {
            $updateStmt = $conn->prepare('UPDATE account SET password = ?, update_time = NOW() WHERE id = ? AND username = ?');
            $updateStmt->bind_param('sis', $newPassword, $userId, $_SESSION['username']);
            $updated = $updateStmt->execute();
            $updateStmt->close();
            $flash = $updated
                ? ['type' => 'success', 'message' => 'Đổi mật khẩu Server 2 thành công.']
                : ['type' => 'error', 'message' => 'Không thể đổi mật khẩu lúc này. Vui lòng thử lại.'];
        }
    }
}

$profileStmt = $conn->prepare(
    'SELECT
        a.username, a.vnd, a.active, a.ban, a.create_time,
        p.name AS player_name, p.gender, p.head
     FROM account a
     LEFT JOIN player p ON p.account_id = a.id
     WHERE a.id = ? AND a.username = ?
     LIMIT 1'
);
$profileStmt->bind_param('is', $userId, $_SESSION['username']);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc();
$profileStmt->close();

if (!$profile) {
    session_unset();
    session_destroy();
    header('Location: /app/login.php?server=2');
    exit();
}

$displayName = trim((string)($profile['player_name'] ?? '')) ?: (string)$profile['username'];
$accountStatus = (int)$profile['ban'] === 1
    ? 'Đã khóa'
    : ((int)$profile['active'] === 1 ? 'Đã kích hoạt' : 'Chưa kích hoạt');

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khu vực Server 2 - Lio Universe</title>
    <link rel="icon" href="/images/favicon-48x48.ico" type="image/x-icon">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            color: #e2e8f0;
            background: radial-gradient(circle at top, #172554 0, #0f172a 42%, #020617 100%);
        }
        .page { width: min(940px, calc(100% - 28px)); margin: 0 auto; padding: 30px 0 48px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 22px; }
        .brand { display: flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; }
        .brand img { width: 58px; height: 58px; object-fit: contain; }
        .brand strong { display: block; font-size: 19px; }
        .brand span { color: #93c5fd; font-size: 13px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .button { display: inline-block; padding: 9px 13px; border-radius: 9px; color: #e2e8f0; text-decoration: none; border: 1px solid #334155; background: #0f172a; font-size: 13px; }
        .button:hover { border-color: #60a5fa; color: #fff; }
        .hero, .card { border: 1px solid rgba(96, 165, 250, .28); background: rgba(15, 23, 42, .88); box-shadow: 0 18px 50px rgba(0, 0, 0, .28); }
        .hero { padding: 24px; border-radius: 18px; margin-bottom: 16px; }
        .server-tag { display: inline-flex; align-items: center; gap: 7px; color: #bfdbfe; background: #1e3a8a; border: 1px solid #3b82f6; border-radius: 999px; padding: 6px 11px; font-size: 12px; font-weight: 700; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 9px #22c55e; }
        h1 { margin: 15px 0 8px; color: #fff; font-size: clamp(24px, 4vw, 34px); }
        .hero p { margin: 0; color: #94a3b8; line-height: 1.6; }
        .notice { margin-top: 16px; padding: 12px 14px; border-radius: 10px; color: #fde68a; background: rgba(120, 53, 15, .4); border: 1px solid rgba(245, 158, 11, .45); font-size: 13px; line-height: 1.5; }
        .notice.recharge-disabled { color: #fecaca; background: rgba(127, 29, 29, .5); border-color: #ef4444; }
        .notice.recharge-disabled strong { display: block; color: #fff; font-size: 14px; margin-bottom: 4px; }
        .grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 16px; }
        .card { padding: 20px; border-radius: 15px; }
        .card h2 { margin: 0 0 16px; color: #fff; font-size: 18px; }
        .profile-list { display: grid; gap: 10px; }
        .profile-row { display: flex; justify-content: space-between; gap: 15px; padding: 10px 0; border-bottom: 1px solid #1e293b; }
        .profile-row span { color: #94a3b8; }
        .profile-row strong { text-align: right; color: #e2e8f0; }
        .flash { margin-bottom: 14px; padding: 11px 13px; border-radius: 9px; font-size: 13px; }
        .flash.success { color: #bbf7d0; background: rgba(20, 83, 45, .55); border: 1px solid #22c55e; }
        .flash.error { color: #fecaca; background: rgba(127, 29, 29, .45); border: 1px solid #ef4444; }
        label { display: block; margin: 12px 0 6px; color: #bfdbfe; font-size: 12px; font-weight: 700; }
        input { width: 100%; padding: 11px 12px; color: #fff; background: #020617; border: 1px solid #334155; border-radius: 9px; outline: none; }
        input:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
        button { width: 100%; margin-top: 16px; padding: 11px; color: #fff; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: 0; border-radius: 9px; font-weight: 800; cursor: pointer; }
        button:hover { filter: brightness(1.08); }
        .footer { margin-top: 20px; color: #64748b; text-align: center; font-size: 12px; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } .topbar { align-items: flex-start; } .brand span { display: none; } }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <a class="brand" href="/">
                <img src="/images/logo_liodev.svg" alt="LioDev">
                <div><strong>Lio Universe</strong><span>Diễn đàn và tài khoản riêng cho SV2</span></div>
            </a>
            <div class="actions">
                <a class="button" href="/">Trang chủ</a>
                <a class="button" href="/app/logout.php">Đăng xuất</a>
            </div>
        </div>

        <section class="hero">
            <span class="server-tag"><span class="dot"></span> SERVER 2 · AWN · CỔNG 14446</span>
            <h1>Xin chào, <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Bạn đang thao tác với tài khoản thuộc database <strong>awnv3</strong>. Mọi thông tin ở trang này được đọc và cập nhật riêng cho Server 2.</p>
            <div class="notice recharge-disabled" id="sv2-recharge-disabled" role="alert">
                <strong>SERVER 2 KHÔNG CÓ TÍNH NĂNG NẠP TIỀN</strong>
                Server 2 hiện không hỗ trợ nạp tiền hoặc nạp thẻ. Mọi trang và API nạp tiền đều bị khóa cho tài khoản SV2; chức năng nạp của Server 1 không dùng chung với máy chủ này.
                <?php if ($serverTwoNotice !== ''): ?>
                    <br><?php echo htmlspecialchars($serverTwoNotice, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>
        </section>

        <div class="grid">
            <section class="card">
                <h2>Thông tin tài khoản SV2</h2>
                <div class="profile-list">
                    <div class="profile-row"><span>Tài khoản</span><strong><?php echo htmlspecialchars((string)$profile['username'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div class="profile-row"><span>Nhân vật</span><strong><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div class="profile-row"><span>Trạng thái</span><strong><?php echo htmlspecialchars($accountStatus, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div class="profile-row"><span>Số dư VND</span><strong><?php echo number_format((int)($profile['vnd'] ?? 0), 0, ',', '.'); ?></strong></div>
                    <div class="profile-row"><span>Ngày tạo</span><strong><?php echo htmlspecialchars((string)($profile['create_time'] ?? 'Chưa có'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div class="profile-row"><span>Máy chủ</span><strong>Server 2 (awnv3)</strong></div>
                </div>
            </section>

            <section class="card">
                <h2>Đổi mật khẩu SV2</h2>
                <?php if ($flash): ?>
                    <div class="flash <?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="current_password">Mật khẩu hiện tại</label>
                    <input id="current_password" name="current_password" type="password" required>
                    <label for="new_password">Mật khẩu mới</label>
                    <input id="new_password" name="new_password" type="password" minlength="6" required>
                    <label for="confirm_password">Nhập lại mật khẩu mới</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="6" required>
                    <button type="submit">ĐỔI MẬT KHẨU SERVER 2</button>
                </form>
            </section>
        </div>

        <div class="footer">LioDev · Server 2 được tách riêng để không ảnh hưởng Server 1</div>
    </main>
</body>
</html>
