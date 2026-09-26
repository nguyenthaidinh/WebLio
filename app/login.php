<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../server_config.php';

$alreadyLoggedIn = !empty($_SESSION['username']);
$loggedInUser = $_SESSION['username'] ?? '';
$gameServers = game_server_configs();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Lio Universe | Chiến Binh Vũ Trụ - LioDev</title>
    <meta name="keywords" content="Lio Universe, Dang nhap Lio, Chien Binh Vu Tru, LioDev, Game Nhap Vai">
    <meta name="description" content="Đăng nhập tài khoản Lio Universe - Game nhập vai chiến binh vũ trụ trực tuyến hàng đầu phát triển bởi LioDev!">
    <meta name="robots" content="INDEX,FOLLOW">

    <!-- Favicons -->
    <link rel="apple-touch-icon" href="/images/favicon-48x48.ico">
    <link rel="icon" href="/images/favicon-48x48.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/images/favicon-48x48.ico" type="image/x-icon">
    <link rel="icon" type="image/png" href="/images/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/images/favicon-64x64.png" sizes="64x64">

    <!-- External & Design System CSS -->
    <script src="/view/static/js/disable_devtools.js"></script>
    <link rel="stylesheet" href="/view/static/css/template.css?v=2.0">
    <link rel="stylesheet" href="/view/static/css/eff.css?v=2.0">
    <link rel="stylesheet" href="/view/static/css/styleSheet.css?v=2.0">

    <style>
        /* Scoped Menu2 & Layout Refinements */
        .menu2 td#selected,
        .menu2 td,
        td#selected {
            border: none !important;
            outline: none !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        /* Scoped Enhancements for Login matching Bright White/Blue Theme */
        .login-card-container {
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            border: 1.5px solid rgba(226, 232, 240, 0.95);
            border-radius: 24px;
            padding: 32px 28px;
            box-shadow: 0 16px 36px rgba(2, 132, 199, 0.1), 0 2px 10px rgba(0, 0, 0, 0.03);
            position: relative;
        }

        .login-header {
            text-align: center;
            margin-bottom: 22px;
        }

        .login-header-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .login-header-desc {
            font-size: 13.5px;
            color: #64748b;
            line-height: 1.45;
        }

        /* Pill Switcher between Login and Register */
        .auth-switch-tabs {
            display: flex;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 22px;
            gap: 4px;
        }

        .auth-switch-tab {
            flex: 1;
            text-align: center;
            padding: 10px 14px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: all 0.25s ease;
        }

        .auth-switch-tab svg {
            width: 16px;
            height: 16px;
        }

        .auth-switch-tab:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.7);
        }

        .auth-switch-tab.active {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.35);
        }

        /* Form Inputs */
        .form-group-custom {
            margin-bottom: 16px;
            text-align: left;
        }

        .form-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 7px;
        }

        .form-label-custom {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .forgot-link-custom {
            font-size: 12.5px;
            color: #0284c7;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-link-custom:hover {
            color: #0369a1;
            text-decoration: underline;
        }

        .input-wrap-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-lead {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #64748b;
            pointer-events: none;
            z-index: 2;
            transition: color 0.2s ease;
        }

        .input-wrap-custom input[type="text"],
        .input-wrap-custom input[type="password"],
        .form-control-custom {
            width: 100% !important;
            height: 46px !important;
            background: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 12px !important;
            padding: 0 44px 0 44px !important;
            color: #0f172a !important;
            font-size: 14.5px !important;
            font-family: inherit !important;
            outline: none !important;
            transition: all 0.22s ease !important;
            box-sizing: border-box !important;
        }

        .input-wrap-custom input[type="text"]::placeholder,
        .input-wrap-custom input[type="password"]::placeholder,
        .form-control-custom::placeholder {
            color: #94a3b8 !important;
            font-size: 13.5px !important;
        }

        .input-wrap-custom input[type="text"]:focus,
        .input-wrap-custom input[type="password"]:focus,
        .form-control-custom:focus {
            background: #ffffff !important;
            border-color: #f97316 !important;
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.16) !important;
        }

        .input-wrap-custom:focus-within .input-icon-lead {
            color: #ea580c;
        }

        .btn-toggle-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            border-radius: 8px;
            z-index: 2;
            transition: all 0.2s ease;
        }

        .btn-toggle-eye:hover {
            color: #0f172a;
            background: #e2e8f0;
        }

        .btn-toggle-eye svg {
            width: 18px;
            height: 18px;
        }

        /* Server Selection */
        .server-select-wrap {
            margin: 18px 0 16px;
        }

        .server-select-title {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .server-cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .server-box-card {
            position: relative;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: all 0.25s ease;
            user-select: none;
            text-align: left;
        }

        .server-box-card:hover {
            border-color: #f97316;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.12);
        }

        .server-box-card.active {
            background: #fff7ed;
            border-color: #f97316;
            border-width: 2px;
            box-shadow: 0 4px 16px rgba(249, 115, 22, 0.18);
        }

        .server-box-card.disabled-coming-soon {
            background: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            cursor: pointer;
            opacity: 0.85;
            transition: all 0.25s ease;
        }

        .server-box-card.disabled-coming-soon:hover {
            opacity: 1;
            border-color: #f59e0b;
            background: #fffbeb;
            transform: translateY(-2px);
        }

        .server-box-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .server-box-name {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .server-box-desc {
            font-size: 11.5px;
            color: #64748b;
        }

        /* Server Status Badges */
        .status-badge-online {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 700;
            color: #15803d;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            padding: 2px 7px;
            border-radius: 9999px;
        }

        .status-dot-green {
            width: 6px;
            height: 6px;
            background-color: #16a34a;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 6px #16a34a;
        }

        .status-badge-soon {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 700;
            color: #b45309;
            background: #fef3c7;
            border: 1px solid #fde68a;
            padding: 2px 7px;
            border-radius: 9999px;
            text-transform: uppercase;
        }

        /* Coming Soon Banner/Toast Notice */
        .server-notice-banner {
            margin-top: 10px;
            padding: 10px 14px;
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            font-size: 12.5px;
            color: #92400e;
            display: none;
            align-items: center;
            gap: 8px;
            line-height: 1.4;
            animation: slideDownAlert 0.3s ease;
        }

        @keyframes slideDownAlert {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .server-notice-banner svg {
            flex-shrink: 0;
            width: 17px;
            height: 17px;
            color: #d97706;
        }

        /* Remember Me */
        .remember-wrap-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 14px 0 18px;
            font-size: 13.5px;
        }

        .remember-label-custom {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #475569;
            cursor: pointer;
            user-select: none;
            font-weight: 500;
        }

        .remember-label-custom input {
            accent-color: #f97316;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        /* Alert / Message */
        .alert-custom {
            padding: 11px 14px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }

        .alert-custom.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-custom.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            animation: shakeNotice 0.35s ease;
        }

        @keyframes shakeNotice {
            0%, 100% { transform: translateX(0); }
            25%, 75% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
        }

        .alert-custom svg {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
        }

        /* Submit Button */
        .btn-login-submit {
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 6px 18px rgba(249, 115, 22, 0.35);
            transition: all 0.25s ease;
        }

        .btn-login-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(249, 115, 22, 0.45);
        }

        .btn-login-submit:active {
            transform: translateY(1px);
        }

        .btn-login-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        .spinner-login {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .register-footer-cta {
            margin-top: 18px;
            text-align: center;
            font-size: 13.5px;
            color: #64748b;
        }

        .register-footer-cta a {
            color: #ea580c;
            font-weight: 700;
            text-decoration: none;
            margin-left: 4px;
        }

        .register-footer-cta a:hover {
            text-decoration: underline;
        }

        .security-footer-info {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .security-footer-info svg {
            width: 14px;
            height: 14px;
            color: #16a34a;
        }

        /* Logged In Box */
        .logged-in-profile-box {
            text-align: center;
            padding: 12px 0;
        }

        .logged-in-profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid #f97316;
            box-shadow: 0 4px 16px rgba(249, 115, 22, 0.25);
            margin: 0 auto 14px;
            display: block;
            background: #fff;
        }

        .logged-in-user-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .logged-in-user-desc {
            font-size: 13.5px;
            color: #64748b;
            margin-bottom: 22px;
        }

        .logged-in-user-btns {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-enter-forum {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #fff;
            text-decoration: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-switch-logout {
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            padding: 11px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13.5px;
        }

        .btn-switch-logout:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        @media (max-width: 520px) {
            .login-card-container {
                padding: 24px 18px;
                border-radius: 20px;
            }
            .server-cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Falling blossoms canvas matching outer pages -->
    <div class="snowEffect">
        <canvas id="snowcanvas" height="100%" width="100%"></canvas>
    </div>

    <div class="body_body">
        <a href="#" id="backTop"><img id="backTopimg" src="/images/favicon-32x32.png" alt="top" /></a>

        <!-- 18+ Advisory Banner (identical to outer pages) -->
        <div class="div-12">
            <img height="14" src="/images/18-1.png" alt="18+" style="vertical-align: middle;" />
            <span>Khuyến cáo: Chơi quá 180 phút một ngày sẽ ảnh hưởng xấu đến sức khỏe. Hãy cân bằng thời gian hợp lý.</span>
        </div>

        <!-- Main Outer Glassmorphism Container (White Theme matching trang-chu) -->
        <div class="body-content">
            <div class="bg-content2">

                <!-- Header Logo -->
                <h1 class="a">
                    <a href="/" title="Lio Universe - Chiến Binh Vũ Trụ">
                        <img height="105" src="/images/logo_liodev.svg" alt="Lio Universe - Chiến Binh Vũ Trụ" />
                    </a>
                </h1>

                <!-- Navigation Menu (Matching trang-chu & forum) -->
                <div id="top">
                    <div class="link-more">
                        <div class="h">
                            <div class="menu2">
                                <table width="100%" cellspacing="4">
                                    <tr class="menu">
                                        <td>
                                            <a href="/trang-chu.php">Trang Chủ</a>
                                        </td>
                                        <td>
                                            <a href="/gioi-thieu.php">Giới Thiệu</a>
                                        </td>
                                        <td>
                                            <a href="/forum.php" title="Diễn Đàn">Diễn Đàn</a>
                                        </td>
                                        <td id="selected">
                                            <a href="/app/login.php" title="Đăng Nhập">Đăng Nhập</a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- White Login Card -->
                <div class="login-card-container">

                    <div class="login-header">
                        <div class="login-header-title">Đăng Nhập Tài Khoản</div>
                        <div class="login-header-desc">Sử dụng tài khoản Lio Universe để đăng nhập vào game và diễn đàn</div>
                    </div>

                    <?php if ($alreadyLoggedIn): ?>
                        <!-- Already logged in view -->
                        <div class="logged-in-profile-box">
                            <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/images/avatar/default_avatar.png') ?>" alt="Avatar" class="logged-in-profile-avatar" onerror="this.src='/images/favicon-32x32.png'">
                            <div class="logged-in-user-name">Xin chào, <?= htmlspecialchars($loggedInUser) ?>!</div>
                            <div class="logged-in-user-desc">Bạn đang đăng nhập vào hệ thống Lio Universe.</div>

                            <div class="logged-in-user-btns">
                                <a href="/forum.php" class="btn-enter-forum">
                                    <span>Vào Diễn Đàn Ngay</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                                <a href="/app/logout.php" class="btn-switch-logout">Đăng xuất / Đổi tài khoản khác</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Switch Tabs between Login and Register -->
                        <div class="auth-switch-tabs">
                            <a href="/app/login.php" class="auth-switch-tab active">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                    <polyline points="10 17 15 12 10 7"></polyline>
                                    <line x1="15" y1="12" x2="3" y2="12"></line>
                                </svg>
                                <span>Đăng Nhập</span>
                            </a>
                            <a href="/app/register.php" class="auth-switch-tab">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                <span>Đăng Ký</span>
                            </a>
                        </div>

                        <!-- Login Form -->
                        <form id="loginForm" method="POST" name="login" novalidate>
                            <!-- Preserved Hidden Security & Action Fields -->
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="keySig" value="a511129a7ce15460414e6fe318eebc2b">
                            <input type="hidden" name="nav" value="" readonly="readonly">
                            <input type="hidden" name="checkru" value="d3540b1767470e0a87215174bd0ed85d">

                            <!-- Username Field -->
                            <div class="form-group-custom">
                                <div class="form-label-row">
                                    <label class="form-label-custom" for="user">Tài Khoản</label>
                                </div>
                                <div class="input-wrap-custom">
                                    <svg class="input-icon-lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <input 
                                        type="text" 
                                        id="user" 
                                        name="user" 
                                        class="form-control-custom" 
                                        placeholder="Nhập tên tài khoản..." 
                                        autocomplete="username" 
                                        required
                                    >
                                </div>
                            </div>

                            <!-- Password Field -->
                            <div class="form-group-custom">
                                <div class="form-label-row">
                                    <label class="form-label-custom" for="pass">Mật Khẩu</label>
                                    <a href="/forgot-password.php" class="forgot-link-custom">Quên mật khẩu?</a>
                                </div>
                                <div class="input-wrap-custom">
                                    <svg class="input-icon-lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                    <input 
                                        type="password" 
                                        id="pass" 
                                        name="pass" 
                                        class="form-control-custom" 
                                        placeholder="Nhập mật khẩu..." 
                                        autocomplete="current-password" 
                                        required
                                    >
                                    <button type="button" class="btn-toggle-eye" id="btnTogglePass" aria-label="Hiện/ẩn mật khẩu">
                                        <svg id="eyeShowIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <svg id="eyeHideIcon" style="display:none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                            <line x1="1" y1="1" x2="23" y2="23"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Server Selection (Server 1 Active + Server 2 Coming Soon) -->
                            <div class="server-select-wrap">
                                <div class="server-select-title">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                                        <line x1="6" y1="6" x2="6.01" y2="6"></line>
                                        <line x1="6" y1="18" x2="6.01" y2="18"></line>
                                    </svg>
                                    <span>Máy Chủ Game</span>
                                </div>

                                <div class="server-cards-grid">
                                    <!-- Server 1: Active -->
                                    <label class="server-box-card active" id="serverCard1">
                                        <input type="radio" name="server" value="1" checked required style="display:none;">
                                        <div class="server-box-top">
                                            <div class="server-box-name">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                                    <polyline points="2 17 12 22 22 17"></polyline>
                                                    <polyline points="2 12 12 17 22 12"></polyline>
                                                </svg>
                                                <span>Server 1</span>
                                            </div>
                                            <span class="status-badge-online">
                                                <span class="status-dot-green"></span>
                                                <span>Online</span>
                                            </span>
                                        </div>
                                        <div class="server-box-desc">Cụm Chiến Binh Chính Thức</div>
                                    </label>

                                    <!-- Server 2: Coming Soon -->
                                    <div class="server-box-card disabled-coming-soon" id="serverCard2" title="Nhấp để xem thông tin Server 2">
                                        <div class="server-box-top">
                                            <div class="server-box-name">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                                    <polyline points="2 17 12 22 22 17"></polyline>
                                                    <polyline points="2 12 12 17 22 12"></polyline>
                                                </svg>
                                                <span>Server 2</span>
                                            </div>
                                            <span class="status-badge-soon">Coming Soon</span>
                                        </div>
                                        <div class="server-box-desc">Cụm Thử Nghiệm Vũ Trụ</div>
                                    </div>
                                </div>

                                <!-- Coming Soon Interactive Banner -->
                                <div id="serverNotice" class="server-notice-banner">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                    <span><strong>Server 2 (Coming Soon):</strong> Máy chủ đang trong quá trình chuẩn bị ra mắt. Vui lòng chọn <strong>Server 1</strong> để đăng nhập chơi ngay!</span>
                                </div>
                            </div>

                            <!-- Options: Remember Me -->
                            <div class="remember-wrap-custom">
                                <label class="remember-label-custom">
                                    <input type="checkbox" id="rememberMe" name="remember" checked>
                                    <span>Ghi nhớ đăng nhập</span>
                                </label>
                            </div>

                            <!-- Alert Box for AJAX Response -->
                            <div id="loginMessage" class="alert-custom" style="display:none;" role="alert">
                                <svg class="alert-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></svg>
                                <span class="alert-msg-text"></span>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" id="button1" name="submit" class="btn-login-submit">
                                <span class="btn-login-text">ĐĂNG NHẬP</span>
                                <svg class="btn-arrow-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                                <div class="spinner-login btn-login-spinner" style="display:none;"></div>
                            </button>

                            <!-- Bottom Register CTA -->
                            <div class="register-footer-cta">
                                Chưa có tài khoản? <a href="register.php">Đăng Ký ngay</a>
                            </div>

                            <!-- SSL Security Info -->
                            <div class="security-footer-info">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <polyline points="9 12 11 14 15 10"></polyline>
                                </svg>
                                <span>Hệ thống bảo mật dữ liệu & thông tin tài khoản an toàn</span>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>

            </div>
        </div>

        <!-- Footer matching outer website -->
        <div class="text-center mt-3 mb-4" style="color: #64748b; font-size: 13px; line-height: 1.6;">
            <div><strong>LIO UNIVERSE - CHIẾN BINH VŨ TRỤ</strong></div>
            <div>&copy; 2024 - 2026 Bản quyền thuộc về LioDev (liodev.io.vn). All rights reserved.</div>
            <div class="code-by-lio" style="margin-top: 6px;">
                Phát triển & Vận hành độc quyền bởi <span class="lio-badge">LIODEV</span>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/view/static/js/ThreeCanvas.js" type="text/javascript"></script>
    <script src="/view/static/js/Snow3d.js" type="text/javascript"></script>
    <script src="/view/static/js/animation.js" type="text/javascript"></script>

    <script>
    $(document).ready(function() {
        // Toggle Password Visibility
        $('#btnTogglePass').on('click', function(e) {
            e.preventDefault();
            var passInput = $('#pass');
            var eyeShow = $('#eyeShowIcon');
            var eyeHide = $('#eyeHideIcon');

            if (passInput.attr('type') === 'password') {
                passInput.attr('type', 'text');
                eyeShow.hide();
                eyeHide.show();
            } else {
                passInput.attr('type', 'password');
                eyeShow.show();
                eyeHide.hide();
            }
        });

        // Server 1 Selection
        $('#serverCard1').on('click', function() {
            $(this).addClass('active');
            $(this).find('input[type="radio"]').prop('checked', true);
            $('#serverNotice').slideUp(200);
        });

        // Server 2 Click -> Shows "Coming Soon" Alert Banner
        $('#serverCard2').on('click', function() {
            var card = $(this);
            
            // Micro-shake animation
            card.css({
                'transform': 'translateX(-4px)',
                'transition': 'transform 0.1s ease'
            });
            setTimeout(function() {
                card.css('transform', 'translateX(4px)');
                setTimeout(function() {
                    card.css('transform', 'none');
                }, 100);
            }, 100);

            // Show Coming Soon Notice
            $('#serverNotice').stop(true, true).slideDown(250);

            // Keep Server 1 checked
            $('#serverCard1').addClass('active');
            $('#serverCard1 input[type="radio"]').prop('checked', true);
        });

        // Preload remembered username if exists
        var savedUser = localStorage.getItem('lio_remembered_user');
        if (savedUser && $('#user').val() === '') {
            $('#user').val(savedUser);
            $('#rememberMe').prop('checked', true);
        }

        // Handle AJAX Login Submit
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var submitBtn = $('#button1');
            var btnText = submitBtn.find('.btn-login-text');
            var btnArrow = submitBtn.find('.btn-arrow-icon');
            var btnSpinner = submitBtn.find('.btn-login-spinner');
            var messageDiv = $('#loginMessage');
            var alertIcon = messageDiv.find('.alert-icon-svg');
            var alertText = messageDiv.find('.alert-msg-text');

            var userVal = $('#user').val().trim();
            var passVal = $('#pass').val();

            if (!userVal || !passVal) {
                messageDiv.removeClass('success').addClass('error').show();
                alertIcon.html('<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>');
                alertText.text('Vui lòng nhập đầy đủ tài khoản và mật khẩu.');
                return;
            }

            // Save or clear remembered username
            if ($('#rememberMe').is(':checked')) {
                localStorage.setItem('lio_remembered_user', userVal);
            } else {
                localStorage.removeItem('lio_remembered_user');
            }

            // Set Loading UI
            submitBtn.prop('disabled', true);
            btnText.text('Đang đăng nhập...');
            btnArrow.hide();
            btnSpinner.show();
            messageDiv.hide();

            $.ajax({
                type: "POST",
                url: 'auth_process.php',
                data: form.serialize(),
                dataType: "json",
                success: function(response) {
                    messageDiv.show().removeClass('success error');

                    if (response.status === 'success') {
                        messageDiv.addClass('success');
                        alertIcon.html('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>');
                        alertText.text(response.message || 'Đăng nhập thành công! Đang chuyển hướng...');
                        btnText.text('Thành công!');

                        var targetRedirect = response.redirect || '/forum.php';
                        setTimeout(function() {
                            window.location.href = targetRedirect;
                        }, 1200);
                    } else {
                        messageDiv.addClass('error');
                        alertIcon.html('<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>');
                        alertText.text(response.message || 'Tên đăng nhập hoặc mật khẩu không đúng.');
                        
                        submitBtn.prop('disabled', false);
                        btnText.text('ĐĂNG NHẬP');
                        btnArrow.show();
                        btnSpinner.hide();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    messageDiv.show().removeClass('success error').addClass('error');
                    alertIcon.html('<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>');
                    
                    var errorMessage = 'Đã xảy ra lỗi kết nối. Vui lòng kiểm tra lại mạng hoặc thử lại sau.';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMessage = jqXHR.responseJSON.message;
                    } else if (jqXHR.responseText) {
                        var plainText = $('<div>').html(jqXHR.responseText).text().trim();
                        if (plainText !== '') {
                            errorMessage = plainText.substring(0, 260);
                        }
                    }
                    alertText.text(errorMessage);

                    submitBtn.prop('disabled', false);
                    btnText.text('ĐĂNG NHẬP');
                    btnArrow.show();
                    btnSpinner.hide();
                }
            });
        });
    });
    </script>
</body>
</html>
