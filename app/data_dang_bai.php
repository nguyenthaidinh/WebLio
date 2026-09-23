<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../forum_post_helpers.php';

forum_post_set_flash('error', 'Biểu mẫu đăng bài cũ đã ngừng sử dụng. Vui lòng đăng bài tại trang mới.');
header('Location: /dang-bai.php', true, 303);
exit();
