# Quản trị server game trên web

Trang mới: `/admin/server-runtime.php?server=2`. Người đăng nhập phải là admin **Server 1**; quyền được kiểm tra trong database Server 1. Các trang sửa vật phẩm, chỉ số và nhân vật cũ được chuyển đến trang này để tránh ghi SQL trực tiếp đè lên nhân vật đang online.

Phạm vi hiện có: trạng thái server, danh sách online, gửi/thu hồi vật phẩm, cộng chỉ số, ngắt kết nối, EXP, lịch sự kiện, thông báo, bảo trì và tải lại giftcode/shop/drop. Chưa chuyển các màn hình chỉnh sửa tài khoản, shop, giftcode và nhân vật offline lên web; các chức năng đó cần thiết kế quyền, giao dịch và đồng bộ riêng trước khi mở.

## Triển khai Server 2

1. Build và chạy source game tại `F:\LioDev\LioDev\SrcLioV2\AWN_Version` bằng JDK 21 (`run.bat`). File JAR cũ không chứa Admin API; cần build lại và khởi động lại game sau khi triển khai code.
2. Tạo token mới, ví dụ `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. **Không commit token**. Đặt `NRO_ADMIN_API_TOKEN` vào môi trường của tiến trình game trước khi khởi động; đặt **cùng token** vào `NRO_ADMIN_API_S2_TOKEN` trong môi trường của PHP. Token cũ từng xuất hiện trong source phải bỏ, không dùng lại.
3. Mặc định API chỉ nghe tại `127.0.0.1:18082`. Nếu PHP cùng máy với game, dùng `NRO_ADMIN_API_S2_URL=http://127.0.0.1:18082`. Nếu PHP trên máy khác, dùng SSH tunnel/VPN riêng có xác thực và giới hạn IP, rồi trỏ URL PHP vào địa chỉ tunnel. Không mở cổng HTTP của Admin API ra Internet. Địa chỉ bind và IP cho phép nằm trong `data/config/data_base.properties` của game.
4. Đăng nhập admin Server 1 rồi mở trang mới. Kiểm tra database hiển thị là `awnv3`, cổng game là `14446`. Web tự khóa lệnh nếu API trả về nhầm server.

Server 1 chưa có Admin API tương ứng trong source Server 2 này. Tab Server 1 sẽ báo chưa cấu hình cho đến khi triển khai bridge riêng và đặt `NRO_ADMIN_API_S1_URL`, `NRO_ADMIN_API_S1_TOKEN`. Không trỏ token hoặc URL Server 1 sang Server 2.

## Cổng database

`14445` và `14446` là cổng game. Source game Server 2 dùng MySQL `localhost:3306`; web hiện giữ cổng database cũ để không tự ý thay đổi hạ tầng đang chạy. Nếu PHP truy cập MySQL bằng cổng khác, đặt `NRO_DB_S1_PORT` và `NRO_DB_S2_PORT` đúng theo cổng MySQL thực tế. Database Server 1 là `team2026`, Server 2 là `awnv3`.

## Lưu ý vận hành

- Lệnh sửa nhân vật chỉ hỗ trợ nhân vật **đang online** trên đúng server. Khi API báo timeout hoặc không xác nhận đã lưu, cần kiểm tra nhân vật và database trước khi gửi lại, tránh cộng trùng.
- Đổi sự kiện chỉ ghi `active_event.txt`. **Cần khởi động lại game** để tạo boss/NPC một lần; trang hiển thị sự kiện đang chạy và sự kiện đang chờ. Web không tự khởi động lại server.
- Chưa chạy thử lệnh vật phẩm trên game và database thật. Cần sao lưu database và thử với nhân vật thử nghiệm trước khi thao tác trên người chơi thật.
