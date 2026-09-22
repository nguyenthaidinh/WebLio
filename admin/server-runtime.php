<?php

include_once __DIR__ . '/set.php';
require_once __DIR__ . '/admin_api_client.php';

if ($_login === null) {
    header('Location: /app/login.php');
    exit();
}

if (empty($_SESSION['runtime_admin_csrf'])) {
    $_SESSION['runtime_admin_csrf'] = bin2hex(random_bytes(32));
}

$servers = game_server_configs();
$targetServer = (string)($_POST['target_server'] ?? $_GET['server'] ?? '2');
if (!isset($servers[$targetServer])) {
    $targetServer = '2';
}

function runtime_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function runtime_int(string $name, int $default = 0): int
{
    $value = filter_input(INPUT_POST, $name, FILTER_VALIDATE_INT);
    return $value === false || $value === null ? $default : (int)$value;
}

function runtime_player_payload(): array
{
    return [
        'player_id' => runtime_int('player_id'),
        'player_name' => trim((string)($_POST['player_name'] ?? '')),
    ];
}

function runtime_server_matches(array $status, array $config): bool
{
    return (int)($status['game_port'] ?? 0) === (int)$config['game_port']
        && (string)($status['database'] ?? '') === (string)$config['database'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['runtime_admin_csrf'], $postedToken)) {
        $_SESSION['runtime_admin_flash'] = ['type' => 'error', 'message' => 'Phiên bảo mật không hợp lệ.'];
        header('Location: /admin/server-runtime.php?server=' . urlencode($targetServer));
        exit();
    }

    $action = (string)($_POST['action'] ?? '');
    $request = null;

    try {
        $preflight = admin_api_request($targetServer, 'GET', '/api/status');
        if (!$preflight['ok']) {
            throw new RuntimeException((string)$preflight['error']);
        }
        if (!runtime_server_matches($preflight['data'], $servers[$targetServer])) {
            throw new RuntimeException('Admin API không khớp cổng game hoặc database của server đã chọn. Lệnh chưa được gửi.');
        }
        switch ($action) {
            case 'set_exp':
                $request = admin_api_request($targetServer, 'POST', '/api/config/exp', [
                    'rate' => runtime_int('rate'),
                ]);
                break;

            case 'set_event':
                $request = admin_api_request($targetServer, 'POST', '/api/events/current', [
                    'event_id' => runtime_int('event_id'),
                ]);
                break;

            case 'reload':
                $request = admin_api_request($targetServer, 'POST', '/api/reload', [
                    'target' => (string)($_POST['reload_target'] ?? ''),
                ]);
                break;

            case 'broadcast':
                $request = admin_api_request($targetServer, 'POST', '/api/broadcast', [
                    'message' => trim((string)($_POST['message'] ?? '')),
                ]);
                break;

            case 'maintenance':
                $request = admin_api_request($targetServer, 'POST', '/api/maintenance', [
                    'minutes' => runtime_int('minutes'),
                    'confirm' => isset($_POST['confirm_maintenance']),
                ]);
                break;

            case 'give_item':
                $optionsRaw = trim((string)($_POST['options'] ?? ''));
                $options = $optionsRaw === '' ? [] : json_decode($optionsRaw, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($options)) {
                    throw new RuntimeException('Option phải là mảng JSON.');
                }
                $request = admin_api_request($targetServer, 'POST', '/api/players/give-item', array_merge(
                    runtime_player_payload(),
                    [
                        'item_id' => runtime_int('item_id'),
                        'quantity' => runtime_int('quantity', 1),
                        'options' => $options,
                    ]
                ));
                break;

            case 'add_stats':
                $request = admin_api_request($targetServer, 'POST', '/api/players/add-stats', array_merge(
                    runtime_player_payload(),
                    [
                        'changes' => [
                            'power' => runtime_int('power'),
                            'potential' => runtime_int('potential'),
                            'base_hp' => runtime_int('base_hp'),
                            'base_mp' => runtime_int('base_mp'),
                            'base_damage' => runtime_int('base_damage'),
                            'base_defense' => runtime_int('base_defense'),
                            'base_critical' => runtime_int('base_critical'),
                        ],
                    ]
                ));
                break;

            case 'revoke_item':
                $request = admin_api_request($targetServer, 'POST', '/api/players/revoke-item', array_merge(
                    runtime_player_payload(),
                    ['item_id' => runtime_int('item_id')]
                ));
                break;

            case 'kick':
                $request = admin_api_request($targetServer, 'POST', '/api/players/kick', runtime_player_payload());
                break;

            default:
                throw new RuntimeException('Thao tác quản trị không hợp lệ.');
        }

        if (!empty($request['ok'])) {
            $_SESSION['runtime_admin_flash'] = [
                'type' => 'success',
                'message' => (string)($request['data']['message'] ?? 'Thao tác thành công.'),
            ];
        } else {
            $_SESSION['runtime_admin_flash'] = [
                'type' => 'error',
                'message' => (string)($request['error'] ?? 'Thao tác không thành công.'),
            ];
        }
    } catch (Throwable $e) {
        $_SESSION['runtime_admin_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    }

    header('Location: /admin/server-runtime.php?server=' . urlencode($targetServer));
    exit();
}

$flash = $_SESSION['runtime_admin_flash'] ?? null;
unset($_SESSION['runtime_admin_flash']);

$statusResponse = admin_api_request($targetServer, 'GET', '/api/status');
$targetMatches = $statusResponse['ok'] && runtime_server_matches($statusResponse['data'], $servers[$targetServer]);
if ($statusResponse['ok'] && !$targetMatches) {
    $statusResponse = admin_api_failure(409, 'Admin API không khớp cổng game hoặc database của server đã chọn. Đã khóa thao tác.');
}
$playersResponse = $targetMatches ? admin_api_request($targetServer, 'GET', '/api/players/online') : admin_api_failure(409, 'Không thể đọc danh sách nhân vật.');
$status = $statusResponse['ok'] ? $statusResponse['data'] : [];
$players = $playersResponse['ok'] ? $playersResponse['data'] : [];
$apiOnline = !empty($statusResponse['ok']);
$csrfToken = $_SESSION['runtime_admin_csrf'];

$eventNames = [
    0 => 'Không có sự kiện',
    1 => 'Tết Nguyên Đán',
    2 => 'Trung Thu',
    3 => 'Halloween',
    4 => 'Giáng Sinh',
    5 => 'Vu Lan Báo Hiếu',
    6 => 'Quốc tế Phụ nữ 8/3',
    7 => 'Giỗ Tổ Hùng Vương',
    8 => 'Black Friday',
    9 => 'Valentine',
    10 => 'Phụ nữ Việt Nam 20/10',
    11 => 'Top nạp',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Điều khiển máy chủ - Lio Admin</title>
    <link href="../assets/fontawesome-free/css/all.min.css" rel="stylesheet">
    <style>
        :root { --nav:#172033; --ink:#172033; --muted:#657086; --line:#dce1e8; --bg:#f4f6f8; --white:#fff; --orange:#e65f1b; --green:#16834b; --red:#bb2e3f; --blue:#2465a8; }
        * { box-sizing:border-box; }
        body { background:var(--bg); color:var(--ink); font-family:"Segoe UI",Arial,sans-serif; margin:0; }
        a { color:inherit; }
        .topbar { align-items:center; background:var(--nav); color:#fff; display:flex; gap:16px; justify-content:space-between; min-height:62px; padding:10px 24px; }
        .brand { align-items:center; display:flex; font-size:18px; font-weight:800; gap:10px; text-decoration:none; }
        .top-actions { align-items:center; display:flex; flex-wrap:wrap; gap:8px; }
        .top-actions a,.server-tab { border:1px solid #3b465b; color:#dbe3ef; padding:7px 10px; text-decoration:none; }
        .server-tab.active { background:#fff; border-color:#fff; color:var(--ink); }
        .page { margin:0 auto; max-width:1500px; padding:22px; }
        .heading { align-items:flex-end; display:flex; gap:16px; justify-content:space-between; margin-bottom:16px; }
        h1 { font-size:25px; margin:0; }
        h2 { font-size:16px; margin:0 0 14px; }
        .sub { color:var(--muted); font-size:13px; margin-top:5px; }
        .badge { align-items:center; display:inline-flex; font-size:13px; font-weight:800; gap:7px; }
        .dot { background:var(--red); border-radius:50%; height:9px; width:9px; }
        .online .dot { background:var(--green); }
        .alert { border:1px solid; margin-bottom:16px; padding:11px 13px; }
        .alert.success { background:#eaf7ef; border-color:#8bc8a5; color:#11683a; }
        .alert.error { background:#fff0f1; border-color:#dfa0a8; color:#922333; }
        .stats { display:grid; gap:10px; grid-template-columns:repeat(6,minmax(130px,1fr)); margin-bottom:16px; }
        .stat { background:var(--white); border:1px solid var(--line); border-top:3px solid var(--blue); min-height:88px; padding:13px; }
        .stat:nth-child(2) { border-top-color:var(--green); }
        .stat:nth-child(3) { border-top-color:var(--orange); }
        .stat:nth-child(4) { border-top-color:#7048a8; }
        .stat:nth-child(5) { border-top-color:#2d8796; }
        .stat:nth-child(6) { border-top-color:#9b6a22; }
        .stat-label { color:var(--muted); font-size:12px; font-weight:700; }
        .stat-value { font-size:22px; font-weight:800; margin-top:8px; overflow-wrap:anywhere; }
        .layout { display:grid; gap:16px; grid-template-columns:minmax(0,1fr) 390px; }
        .panel { background:var(--white); border:1px solid var(--line); margin-bottom:16px; padding:16px; }
        .form-grid { display:grid; gap:11px; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .field.full { grid-column:1/-1; }
        label { color:#404a5c; display:block; font-size:12px; font-weight:700; margin-bottom:5px; }
        input,select,textarea { background:#fff; border:1px solid #bcc4cf; border-radius:4px; color:var(--ink); font:inherit; min-height:38px; padding:8px 10px; width:100%; }
        textarea { min-height:76px; resize:vertical; }
        .actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
        button { align-items:center; background:var(--blue); border:0; border-radius:4px; color:#fff; cursor:pointer; display:inline-flex; font-weight:800; gap:7px; justify-content:center; min-height:38px; padding:8px 13px; }
        button:hover { filter:brightness(.94); }
        button.orange { background:var(--orange); }
        button.green { background:var(--green); }
        button.danger { background:var(--red); }
        button.secondary { background:#586579; }
        .check { align-items:center; display:flex; gap:8px; margin-top:10px; }
        .check input { min-height:auto; width:auto; }
        .check label { margin:0; }
        .table-wrap { overflow:auto; }
        table { border-collapse:collapse; font-size:13px; width:100%; }
        th,td { border-bottom:1px solid var(--line); padding:10px 9px; text-align:left; white-space:nowrap; }
        th { background:#f1f3f6; color:#4c5668; font-size:11px; text-transform:uppercase; }
        td.name { font-weight:800; }
        .icon-button { background:var(--red); min-height:32px; padding:6px 9px; }
        .empty { color:var(--muted); padding:25px 10px; text-align:center; }
        .api-error { background:#fff; border:1px solid #e5a1aa; color:#8d2433; padding:14px; }
        @media (max-width:1100px) { .stats { grid-template-columns:repeat(3,1fr); } .layout { grid-template-columns:1fr; } }
        @media (max-width:650px) { .topbar,.heading { align-items:flex-start; flex-direction:column; } .page { padding:14px 10px; } .stats { grid-template-columns:repeat(2,1fr); } .form-grid { grid-template-columns:1fr; } .field.full { grid-column:auto; } }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="/admin"><i class="fas fa-server"></i> Lio Admin</a>
    <div class="top-actions">
        <?php foreach ($servers as $serverId => $serverConfig): ?>
            <a class="server-tab <?php echo $targetServer === (string)$serverId ? 'active' : ''; ?>"
               href="?server=<?php echo runtime_h($serverId); ?>">
                <?php echo runtime_h($serverConfig['name']); ?>
            </a>
        <?php endforeach; ?>
        <a href="/admin"><i class="fas fa-arrow-left"></i> Tổng quan</a>
    </div>
</header>

<main class="page">
    <div class="heading">
        <div>
            <h1>Điều khiển <?php echo runtime_h($servers[$targetServer]['name']); ?></h1>
            <div class="sub"><?php echo runtime_h($status['server_name'] ?? $servers[$targetServer]['name']); ?></div>
        </div>
        <div class="badge <?php echo $apiOnline ? 'online' : ''; ?>">
            <span class="dot"></span><?php echo $apiOnline ? 'API đang kết nối' : 'API mất kết nối'; ?>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert <?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo runtime_h($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$apiOnline): ?>
        <div class="api-error"><?php echo runtime_h($statusResponse['error']); ?></div>
    <?php else: ?>
        <section class="stats">
            <div class="stat"><div class="stat-label">Người chơi online</div><div class="stat-value"><?php echo number_format((int)($status['players_online'] ?? 0)); ?></div></div>
            <div class="stat"><div class="stat-label">Phiên kết nối</div><div class="stat-value"><?php echo number_format((int)($status['sessions'] ?? 0)); ?></div></div>
            <div class="stat"><div class="stat-label">EXP</div><div class="stat-value">x<?php echo number_format((int)($status['exp_rate'] ?? 0)); ?></div></div>
            <div class="stat"><div class="stat-label">Sự kiện</div><div class="stat-value"><?php echo runtime_h($eventNames[(int)($status['event_id'] ?? 0)] ?? 'Không rõ'); ?></div></div>
            <div class="stat"><div class="stat-label">Bộ nhớ</div><div class="stat-value"><?php echo number_format((int)($status['memory_used_mb'] ?? 0)); ?> MB</div></div>
            <div class="stat"><div class="stat-label">Trạng thái</div><div class="stat-value"><?php echo !empty($status['maintenance']) ? 'Bảo trì' : 'Đang chạy'; ?></div></div>
        </section>

        <?php if ((int)($status['pending_event_id'] ?? 0) !== (int)($status['event_id'] ?? 0)): ?>
            <div class="alert error">Sự kiện <?php echo runtime_h($eventNames[(int)$status['pending_event_id']] ?? 'Không rõ'); ?> đã lưu, đang chờ khởi động lại server game.</div>
        <?php endif; ?>
        <div class="layout">
            <div>
                <section class="panel" id="runtime-players">
                    <h2><i class="fas fa-users"></i> Nhân vật đang online</h2>
                    <?php if (!$playersResponse['ok']): ?>
                        <div class="alert error"><?php echo runtime_h($playersResponse['error']); ?></div>
                    <?php endif; ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Nhân vật</th><th>Hành tinh</th><th>Sức mạnh</th><th>Map</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($players as $player): ?>
                                <tr>
                                    <td><?php echo runtime_h($player['id'] ?? ''); ?></td>
                                    <td class="name"><?php echo runtime_h($player['name'] ?? ''); ?></td>
                                    <td><?php echo runtime_h($player['gender'] ?? ''); ?></td>
                                    <td><?php echo number_format((float)($player['power'] ?? 0), 0, ',', '.'); ?></td>
                                    <td><?php echo runtime_h($player['map_id'] ?? ''); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Lưu dữ liệu và ngắt kết nối nhân vật này?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                                            <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                                            <input type="hidden" name="action" value="kick">
                                            <input type="hidden" name="player_id" value="<?php echo runtime_h($player['id'] ?? 0); ?>">
                                            <button class="icon-button" type="submit" title="Ngắt kết nối"><i class="fas fa-sign-out-alt"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$players): ?><tr><td class="empty" colspan="6">Không có nhân vật online.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel" id="runtime-items">
                    <h2><i class="fas fa-box-open"></i> Vật phẩm runtime</h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                        <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                        <input type="hidden" name="action" value="give_item">
                        <div class="form-grid">
                            <div class="field"><label>ID nhân vật</label><input type="number" name="player_id" min="1"></div>
                            <div class="field"><label>Tên nhân vật</label><input type="text" name="player_name" maxlength="50"></div>
                            <div class="field"><label>ID vật phẩm</label><input type="number" name="item_id" min="0" max="32767" required></div>
                            <div class="field"><label>Số lượng</label><input type="number" name="quantity" min="1" max="1000000000" value="1" required></div>
                            <div class="field full"><label>Options JSON</label><textarea name="options" spellcheck="false">[]</textarea></div>
                        </div>
                        <div class="actions"><button class="green" type="submit"><i class="fas fa-plus"></i> Gửi vật phẩm</button></div>
                    </form>
                    <form method="post" onsubmit="return confirm('Thu hồi toàn bộ vật phẩm có ID này khỏi body, hành trang và rương?')">
                        <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                        <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                        <input type="hidden" name="action" value="revoke_item">
                        <div class="form-grid" style="margin-top:16px">
                            <div class="field"><label>ID nhân vật</label><input type="number" name="player_id" min="1"></div>
                            <div class="field"><label>Tên nhân vật</label><input type="text" name="player_name" maxlength="50"></div>
                            <div class="field"><label>ID vật phẩm cần thu hồi</label><input type="number" name="item_id" min="0" max="32767" required></div>
                        </div>
                        <div class="actions"><button class="danger" type="submit"><i class="fas fa-trash-alt"></i> Thu hồi</button></div>
                    </form>
                </section>

                <section class="panel" id="runtime-stats">
                    <h2><i class="fas fa-chart-line"></i> Cộng chỉ số runtime</h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                        <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                        <input type="hidden" name="action" value="add_stats">
                        <div class="form-grid">
                            <div class="field"><label>ID nhân vật</label><input type="number" name="player_id" min="1"></div>
                            <div class="field"><label>Tên nhân vật</label><input type="text" name="player_name" maxlength="50"></div>
                            <div class="field"><label>Sức mạnh</label><input type="number" name="power" min="0" value="0"></div>
                            <div class="field"><label>Tiềm năng</label><input type="number" name="potential" min="0" value="0"></div>
                            <div class="field"><label>HP gốc</label><input type="number" name="base_hp" min="0" value="0"></div>
                            <div class="field"><label>KI gốc</label><input type="number" name="base_mp" min="0" value="0"></div>
                            <div class="field"><label>Sức đánh gốc</label><input type="number" name="base_damage" min="0" value="0"></div>
                            <div class="field"><label>Giáp gốc</label><input type="number" name="base_defense" min="0" max="2147483647" value="0"></div>
                            <div class="field"><label>Chí mạng gốc</label><input type="number" name="base_critical" min="0" max="2147483647" value="0"></div>
                        </div>
                        <div class="actions"><button type="submit"><i class="fas fa-save"></i> Cập nhật chỉ số</button></div>
                    </form>
                </section>
            </div>

            <aside>
                <section class="panel">
                    <h2><i class="fas fa-sliders-h"></i> Cấu hình runtime</h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                        <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                        <input type="hidden" name="action" value="set_exp">
                        <label>Tỷ lệ EXP</label>
                        <input type="number" name="rate" min="1" max="1000" value="<?php echo runtime_h($status['exp_rate'] ?? 1); ?>" required>
                        <div class="actions"><button type="submit"><i class="fas fa-bolt"></i> Cập nhật EXP</button></div>
                    </form>
                    <form method="post" style="margin-top:16px">
                        <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                        <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                        <input type="hidden" name="action" value="set_event">
                        <label>Sự kiện hiện tại</label>
                        <select name="event_id">
                            <?php foreach ($eventNames as $eventId => $eventName): ?>
                                <option value="<?php echo $eventId; ?>" <?php echo (int)($status['pending_event_id'] ?? $status['event_id'] ?? 0) === $eventId ? 'selected' : ''; ?>><?php echo runtime_h($eventName); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="actions"><button class="orange" type="submit"><i class="fas fa-calendar-alt"></i> Áp dụng sự kiện</button></div>
                    </form>
                </section>

                <section class="panel">
                    <h2><i class="fas fa-sync-alt"></i> Tải lại dữ liệu</h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                        <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                        <input type="hidden" name="action" value="reload">
                        <div class="actions">
                            <button class="secondary" type="submit" name="reload_target" value="giftcodes"><i class="fas fa-gift"></i> Giftcode</button>
                            <button class="secondary" type="submit" name="reload_target" value="shops"><i class="fas fa-store"></i> Shop</button>
                            <button class="secondary" type="submit" name="reload_target" value="drops"><i class="fas fa-box"></i> Drop</button>
                        </div>
                    </form>
                </section>

                <section class="panel">
                    <h2><i class="fas fa-bullhorn"></i> Thông báo</h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                        <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                        <input type="hidden" name="action" value="broadcast">
                        <textarea name="message" maxlength="500" required></textarea>
                        <div class="actions"><button type="submit"><i class="fas fa-paper-plane"></i> Gửi toàn server</button></div>
                    </form>
                </section>

                <section class="panel">
                    <h2><i class="fas fa-tools"></i> Bảo trì</h2>
                    <form method="post" onsubmit="return confirm('Xác nhận bắt đầu đếm ngược bảo trì?')">
                        <input type="hidden" name="csrf_token" value="<?php echo runtime_h($csrfToken); ?>">
                        <input type="hidden" name="target_server" value="<?php echo runtime_h($targetServer); ?>">
                        <input type="hidden" name="action" value="maintenance">
                        <label>Số phút đếm ngược</label>
                        <input type="number" name="minutes" min="1" max="60" value="2" required>
                        <div class="check"><input id="confirm-maintenance" type="checkbox" name="confirm_maintenance" required><label for="confirm-maintenance">Xác nhận bảo trì</label></div>
                        <div class="actions"><button class="danger" type="submit"><i class="fas fa-power-off"></i> Bắt đầu bảo trì</button></div>
                    </form>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
