<?php
include_once 'set.php';
include_once 'connect.php';
include('head.php');
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Trang Chủ Chính Thức - Lio Universe</title>
    <meta name="description" content="">
    <meta name="author" content="">
    <base href="/">
    <meta name="description"
        content="Website chính thức của Lio Universe – Game nhập vai chiến binh vũ trụ trực tuyến hấp dẫn hàng đầu phát triển bởi LioDev!">
    <meta name="keywords"
        content="Lio Universe, Lio Online, Chien Binh Vu Tru, LioDev, Game Nhap Vai Truc Tuyen">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title"
        content="Website chính thức của Lio Universe – Game nhập vai chiến binh vũ trụ trực tuyến hấp dẫn hàng đầu phát triển bởi LioDev!">
    <meta name="twitter:description"
        content="Website chính thức của Lio Universe – Game nhập vai chiến binh vũ trụ trực tuyến hấp dẫn hàng đầu phát triển bởi LioDev!">
    <meta name="twitter:image" content="/image/logo.png">
    <meta name="twitter:image:width" content="200">
    <meta name="twitter:image:height" content="200">
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <script src="assets/jquery/jquery.min.js"></script>
    <script src="assets/notify/notify.js"></script>
    <link rel="icon" href="/image/icon.png?v=99">
    <link href="assets/main.css" rel="stylesheet">
</head>

<body>
    <style>
        th,
        td {
            white-space: nowrap;
            padding: 2px 4px !important;
            font-size: 11px;
        }
    </style>
    <div class="container color-forum pt-1 pb-1">
        <div class="row">
            <div class="col"> <a href="dien-dan" style="color: white">Quay lại diễn đàn</a> </div>
        </div>
    </div>
    <div class="container color-forum pt-2">
        <div class="row">
            <div class="col">
                <h6 class="text-center">LỊCH SỬ NẠP THẺ</h6>
                <table class="table table-borderless text-center">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>TÀI KHOẢN</th>
                            <th>MỆNH GIÁ</th>
                            <th>LOẠI THẺ</th>
                            <th>TRẠNG THÁI</th>
                            <th>THỜI GIAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (isset($_username)) {
                            $stmt = $conn->prepare("SELECT * FROM trans_log WHERE name = ? ORDER BY id DESC LIMIT 10");
                            $stmt->bind_param("s", $_username);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result->num_rows > 0) {
                                echo '<tbody>';

                                while ($row = $result->fetch_assoc()) {
                                    $status = '';

                                    switch ($row['status']) {
                                        case 1:
                                            $status = '<span>Thành Công</span>';
                                            break;
                                        case 2:
                                            $status = '<span>Thất Bại</span>';
                                            break;
                                        case 3:
                                            $status = '<span>Sai Mệnh Giá</span>';
                                            break;
                                        default:
                                            $status = '<span>Chờ Duyệt</span>';
                                    }

                                    echo '<tr>
                                    <td>' . $row['id'] . '</td>
                                    <td>' . $row['name'] . '</td>
                                    <td>' . number_format($row['amount']) . 'đ</td>
                                    <td>' . $row['type'] . '</td>
                                    <td>' . $status . '</td>
                                    <td>' . $row['date'] . '</td>
                                    </tr>';
                                }

                                echo '</tbody>';
                            } else {
                                echo '<tbody>
                                <tr>
                                   <td colspan="6" align="center"><span style="font-size:100%;"><< Lịch Sử Trống >></span></td>
                                </tr>
                               </tbody>';
                            }
                        } else {
                            echo 'Chưa có tên người dùng được cung cấp.';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="border-secondary border-top"></div>
    <div class="container pt-4 pb-4 text-white">
        <div class="row">
            <div class="col">
                <div class="text-center">
                    <div style="font-size: 13px" class="text-dark">
                                    <small>IP:
                                        <?php echo $_IP; ?>
                                    </small><br>
                                    <small>Desgin By Mr Blue</small><br>
                                    <small>2024© Lio Universe</small>
                                </div>
                            </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/main.js"></script>
</body><!-- Bootstrap core JavaScript -->

</html>