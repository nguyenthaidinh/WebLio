# Website - Server 2

Website hỗ trợ đăng nhập và đăng ký riêng cho **Server 2** với database `awnv3` và cổng game `14446`.

## Cấu hình môi trường

Các biến có thể đặt cho tiến trình PHP:

- `NRO_DB_S2_HOST` (mặc định `127.0.0.1`)
- `NRO_DB_S2_PORT` (mặc định `3306`)
- `NRO_DB_S2_USER`
- `NRO_DB_S2_PASSWORD`
- `NRO_ADMIN_API_S2_URL` (mặc định `http://127.0.0.1:18082`)
- `NRO_ADMIN_API_S2_TOKEN`

Ở máy phát triển hiện tại, SV1 và SV2 dùng chung tài khoản MariaDB nên khi hai biến user/password của SV2 chưa được đặt, website dùng thông tin đăng nhập database của SV1. Tên database vẫn luôn là `awnv3`, không dùng chung dữ liệu với `team2026`.

## Phạm vi đã bật

- Chọn Server 2 tại trang đăng nhập và đăng ký.
- Đăng ký theo schema `account` của AWN, tham chiếu từ `htdocs/Api/Auth.php`.
- Handler đăng ký khóa cứng Server 1 vào `team2026` và Server 2 vào `awnv3`, đồng thời kiểm tra `SELECT DATABASE()` trước khi `INSERT` để ngăn ghi chéo server.
- Sau đăng nhập, tài khoản SV2 được chuyển đến `/app/server-2.php`.
- Khu vực SV2 hiển thị rõ server/database và hỗ trợ đổi mật khẩu riêng.
- Quản trị runtime SV2 tiếp tục dùng `/admin/server-runtime.php?server=2` qua Java Admin API.
- Nạp tiền/nạp thẻ bị khóa ở cả giao diện, trang nạp, API tạo yêu cầu, webhook có session SV2 và các URL nạp cũ. Diễn đàn/khu vực SV2 luôn hiển thị thông báo không hỗ trợ nạp tiền.

## Tách biệt với Server 1

Bảng `posts` của SV1 và SV2 khác schema. Vì vậy tài khoản SV2 không chạy các truy vấn diễn đàn cũ của SV1; khi truy cập `/forum.php` sẽ được chuyển về khu vực SV2. Các chức năng nạp tiền, đổi vàng và vòng quay của SV1 không hiển thị ở khu vực SV2. Nạp thẻ không được bật cho SV2.

