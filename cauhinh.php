<?php
$_domain = 'http://nro.liodev.io.vn'; // điền domain của sự kiện giới thiệu của bạn
$_IP = '180.93.54.5'; // IP hiển thị ở phần cuối trang

// MySQL cua Server 1; cong game va cong MySQL la hai cau hinh rieng.
require_once __DIR__ . '/server_config.php';
$serverOne = game_server_config('1');
$db_host = $serverOne['host'];
$db_port = $serverOne['port'];
$db_user = $serverOne['username'];
$db_pass = $serverOne['password'];
$db_name = $serverOne['database'];

// API
$w_cuphap_momo = 'Lio đẹp trai'; // cú pháp
$_qrmomo = 'img/qrmomo.png'; // link ảnh qr code
$_phonemomo = ''; // số điện thoại momo
$_momo = 'Momo'; // ngân hàng momo
$_nganhang = 'Lio đẹp trai'; // ngân hàng quân đội mbbank
$_taikhoanmm = 'Lio'; // tên tài khoản

// Thong tin nhan nap tien thu cong. Trang /app/nap-ngoc.php se doc cac bien nay.
$_bank_name = $_nganhang;
$_bank_account_number = $_phonemomo;
$_bank_account_name = $_taikhoanmm;
$_qr_nap_tien = '/images/img/qr.png';

?>
