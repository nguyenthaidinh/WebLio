<?php
$__lucky_ajax_bootstrap = (
    strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || strpos(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json') !== false
    || (isset($_POST['ajax']) && (string)$_POST['ajax'] === '1')
    || (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1')
);
$__lucky_ajax_response_sent = false;

if ($__lucky_ajax_bootstrap) {
    ob_start();
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    register_shutdown_function(function () use (&$__lucky_ajax_response_sent) {
        $error = error_get_last();
        if (
            $error
            && !$__lucky_ajax_response_sent
            && in_array((int)$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'message' => 'Máy chủ lỗi khi xử lý vòng quay. Vui lòng thử lại hoặc báo admin kiểm tra log.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    });
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../server_config.php';
require_server_one_feature($__lucky_ajax_bootstrap);

require_once __DIR__ . '/../forum_data.php';
include_once __DIR__ . '/account_info.php';
require_once __DIR__ . '/../lucky_rewards.php';

if ($__lucky_ajax_bootstrap) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

const LUCKY_GOLD_ITEM_ID = 457;
const CHECKIN_SPIN_REWARD = 1;
const BULK_SPIN_COUNT = 100;

$reward_config_error = '';
try {
    $lucky_rewards = lucky_rewards_load($conn);
    if (lucky_rewards_total_weight($lucky_rewards) <= 0) {
        throw new Exception('Tổng tỉ lệ vòng quay phải lớn hơn 0.');
    }
} catch (Exception $e) {
    $reward_config_error = $e->getMessage();
    error_log($reward_config_error);
    $lucky_rewards = LUCKY_REWARD_DEFAULTS;
}

$message = $_SESSION['lucky_spin_message'] ?? '';
$message_type = $_SESSION['lucky_spin_message_type'] ?? '';
$spin_result = $_SESSION['lucky_spin_result'] ?? null;
unset($_SESSION['lucky_spin_message'], $_SESSION['lucky_spin_message_type'], $_SESSION['lucky_spin_result']);

if (empty($_SESSION['lucky_spin_csrf_token'])) {
    $_SESSION['lucky_spin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['lucky_spin_csrf_token'];

function lucky_set_message($message, $type = 'error', $result = null) {
    $_SESSION['lucky_spin_message'] = $message;
    $_SESSION['lucky_spin_message_type'] = $type;
    if ($result !== null) {
        $_SESSION['lucky_spin_result'] = $result;
    }
}

function lucky_ensure_checkin_table($conn) {
    $sql = "
        CREATE TABLE IF NOT EXISTS lucky_checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            checkin_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_account_date (account_id, checkin_date),
            KEY idx_account_id (account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($sql)) {
        throw new Exception('Không thể khởi tạo bảng điểm danh: ' . $conn->error);
    }
}

function lucky_decode_items_bag($raw_items_bag) {
    $outer_slots = json_decode($raw_items_bag ?: '[]', true);
    if (!is_array($outer_slots)) {
        return [];
    }

    $items = [];
    foreach ($outer_slots as $slot) {
        if (is_string($slot)) {
            $item = json_decode($slot, true);
            $items[] = (json_last_error() === JSON_ERROR_NONE && is_array($item) && count($item) >= 4)
                ? $item
                : [-1, 0, '[]', 0];
            continue;
        }

        $items[] = (is_array($slot) && count($slot) >= 4) ? $slot : [-1, 0, '[]', 0];
    }

    return $items;
}

function lucky_encode_items_bag($items) {
    $encoded_slots = [];
    foreach ($items as $item) {
        $encoded_slots[] = json_encode($item, JSON_UNESCAPED_UNICODE);
    }

    return json_encode($encoded_slots, JSON_UNESCAPED_UNICODE);
}

function lucky_add_gold_to_bag($items, $amount) {
    $empty_slot_index = -1;

    foreach ($items as $index => &$item) {
        if (is_array($item) && isset($item[0]) && (int)$item[0] === LUCKY_GOLD_ITEM_ID) {
            $item[1] = (int)($item[1] ?? 0) + $amount;
            unset($item);
            return $items;
        }

        if (
            $empty_slot_index === -1
            && is_array($item)
            && isset($item[0], $item[1], $item[2])
            && (int)$item[0] === -1
            && (int)$item[1] === 0
            && $item[2] === '[]'
        ) {
            $empty_slot_index = $index;
        }
    }
    unset($item);

    $gold_item = [
        LUCKY_GOLD_ITEM_ID,
        $amount,
        json_encode([[73, 0]], JSON_UNESCAPED_UNICODE),
        round(microtime(true) * 1000)
    ];

    if ($empty_slot_index !== -1) {
        $items[$empty_slot_index] = $gold_item;
    } else {
        $items[] = $gold_item;
    }

    return $items;
}

function lucky_pick_reward($rewards) {
    $total_weight = 0;
    foreach ($rewards as $reward) {
        $total_weight += (int)$reward['weight'];
    }

    $roll = random_int(1, $total_weight);
    $cursor = 0;

    foreach ($rewards as $reward) {
        $cursor += (int)$reward['weight'];
        if ($roll <= $cursor) {
            return $reward;
        }
    }

    return $rewards[0];
}

function lucky_format_degrees($degrees) {
    return rtrim(rtrim(number_format((float)$degrees, 4, '.', ''), '0'), '.');
}

function lucky_is_ajax_request() {
    $requested_with = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requested_with === 'xmlhttprequest'
        || strpos($accept, 'application/json') !== false
        || (isset($_POST['ajax']) && (string)$_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1');
}

function lucky_json_response($payload, $status_code = 200) {
    global $__lucky_ajax_response_sent;

    $__lucky_ajax_response_sent = true;
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $json_flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $json_flags);
    if ($json === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":false,"message":"Không thể mã hóa dữ liệu vòng quay."}';
        exit();
    }

    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
    exit();
}

function lucky_build_wheel_segments($rewards) {
    $total_weight = lucky_rewards_total_weight($rewards);
    if ($total_weight <= 0) {
        return [];
    }

    $segments = [];
    $cursor = 0.0;
    foreach ($rewards as $reward) {
        $weight = max(0, (int)$reward['weight']);
        if ($weight <= 0) {
            continue;
        }

        $degrees = ($weight / $total_weight) * 360;
        $start = $cursor;
        $end = $cursor + $degrees;
        $segments[] = [
            'reward_key' => (string)$reward['reward_key'],
            'label' => (string)$reward['label'],
            'amount' => (int)$reward['amount'],
            'color' => (string)$reward['color'],
            'weight' => $weight,
            'start' => $start,
            'end' => $end,
            'degrees' => $degrees,
            'center' => $start + ($degrees / 2),
        ];
        $cursor = $end;
    }

    if (!empty($segments)) {
        $last_index = count($segments) - 1;
        $segments[$last_index]['end'] = 360.0;
        $segments[$last_index]['degrees'] = $segments[$last_index]['end'] - $segments[$last_index]['start'];
        $segments[$last_index]['center'] = $segments[$last_index]['start'] + ($segments[$last_index]['degrees'] / 2);
    }

    return $segments;
}

function lucky_find_wheel_segment($segments, $reward_key) {
    foreach ($segments as $segment) {
        if ((string)$segment['reward_key'] === (string)$reward_key) {
            return $segment;
        }
    }

    return null;
}

function lucky_wheel_target_for_reward($segments, $reward_key) {
    $segment = lucky_find_wheel_segment($segments, $reward_key);
    if ($segment === null) {
        return null;
    }

    $start = (float)$segment['start'];
    $end = (float)$segment['end'];
    $span = max(0.0, $end - $start);
    $landing_angle = $start + ($span / 2);

    if ($span > 3) {
        $padding = min(12.0, max(1.2, $span * 0.14));
        if (($end - $padding) > ($start + $padding)) {
            $min = (int)round(($start + $padding) * 1000);
            $max = (int)round(($end - $padding) * 1000);
            $landing_angle = random_int($min, $max) / 1000;
        }
    }

    $target_modulo = fmod(360 - fmod($landing_angle, 360), 360);
    if ($target_modulo < 0) {
        $target_modulo += 360;
    }

    $turns = random_int(7, 10);

    return [
        'landing_angle' => lucky_format_degrees($landing_angle),
        'target_rotation' => lucky_format_degrees($target_modulo),
        'turns' => $turns,
    ];
}

function lucky_segment_json($segments) {
    $data = [];
    foreach ($segments as $segment) {
        $data[] = [
            'reward_key' => $segment['reward_key'],
            'label' => $segment['label'],
            'amount' => (int)$segment['amount'],
            'start' => (float)$segment['start'],
            'end' => (float)$segment['end'],
            'center' => (float)$segment['center'],
        ];
    }

    return $data;
}

function lucky_get_account_state($conn, $account_id) {
    $state = ['luotquay' => 0, 'thoi_vang' => 0];
    if (!$account_id) {
        return $state;
    }

    $stmt = $conn->prepare("SELECT luotquay, thoi_vang FROM account WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return $state;
    }

    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $state['luotquay'] = (int)($row['luotquay'] ?? 0);
        $state['thoi_vang'] = (int)($row['thoi_vang'] ?? 0);
    }
    $stmt->close();

    return $state;
}

function lucky_has_checked_in_today($conn, $account_id) {
    if (!$account_id) {
        return false;
    }

    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT id FROM lucky_checkins WHERE account_id = ? AND checkin_date = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("is", $account_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $checked = $result && $result->num_rows > 0;
    $stmt->close();

    return $checked;
}

function lucky_handle_daily_checkin($conn, $account_id) {
    if (!$account_id) {
        throw new Exception('Không tìm thấy tài khoản.');
    }

    $today = date('Y-m-d');
    $conn->begin_transaction();

    try {
        $stmt_checkin = $conn->prepare("INSERT INTO lucky_checkins (account_id, checkin_date) VALUES (?, ?)");
        if (!$stmt_checkin) {
            throw new Exception('Loi prepare diem danh: ' . $conn->error);
        }

        $stmt_checkin->bind_param("is", $account_id, $today);
        if (!$stmt_checkin->execute()) {
            $error_no = $stmt_checkin->errno;
            $error_message = $stmt_checkin->error;
            $stmt_checkin->close();
            if ($error_no === 1062) {
                throw new Exception('Hôm nay bạn đã điểm danh rồi.');
            }
            throw new Exception('Không thể điểm danh: ' . $error_message);
        }
        $stmt_checkin->close();

        $stmt_update = $conn->prepare("UPDATE account SET luotquay = luotquay + ? WHERE id = ?");
        if (!$stmt_update) {
            throw new Exception('Lỗi prepare cộng lượt quay: ' . $conn->error);
        }
        $spin_reward = CHECKIN_SPIN_REWARD;
        $stmt_update->bind_param("ii", $spin_reward, $account_id);
        if (!$stmt_update->execute() || $stmt_update->affected_rows === 0) {
            $stmt_update->close();
            throw new Exception('Không thể cộng lượt quay.');
        }
        $stmt_update->close();

        $conn->commit();
        return $spin_reward;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function lucky_handle_spin($conn, $account_id, $rewards) {
    if (!$account_id) {
        throw new Exception('Không tìm thấy tài khoản.');
    }

    $conn->begin_transaction();

    try {
        $stmt_account = $conn->prepare("SELECT luotquay, thoi_vang FROM account WHERE id = ? FOR UPDATE");
        if (!$stmt_account) {
            throw new Exception('Loi prepare tai khoan: ' . $conn->error);
        }
        $stmt_account->bind_param("i", $account_id);
        $stmt_account->execute();
        $account_result = $stmt_account->get_result();
        $account = $account_result ? $account_result->fetch_assoc() : null;
        $stmt_account->close();

        if (!$account) {
            throw new Exception('Tai khoan khong ton tai.');
        }

        $current_spins = (int)($account['luotquay'] ?? 0);
        $current_gold = (int)($account['thoi_vang'] ?? 0);
        if ($current_spins <= 0) {
            throw new Exception('Bạn chưa có lượt quay.');
        }

        $reward = lucky_pick_reward($rewards);
        $reward_amount = (int)$reward['amount'];

        $stmt_update = $conn->prepare("UPDATE account SET luotquay = luotquay - 1, thoi_vang = thoi_vang + ? WHERE id = ? AND luotquay > 0");
        if (!$stmt_update) {
            throw new Exception('Loi prepare cap nhat quay: ' . $conn->error);
        }
        $stmt_update->bind_param("ii", $reward_amount, $account_id);
        if (!$stmt_update->execute() || $stmt_update->affected_rows === 0) {
            $stmt_update->close();
            throw new Exception('Không thể trừ lượt quay.');
        }
        $stmt_update->close();

        $conn->commit();
        $_SESSION['luotquay'] = $current_spins - 1;
        $_SESSION['thoi_vang'] = $current_gold + $reward_amount;

        return [
            'reward' => $reward,
            'remaining_spins' => $current_spins - 1,
            'pending_gold' => $current_gold + $reward_amount,
        ];
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function lucky_handle_bulk_spin($conn, $account_id, $rewards, $spin_count = BULK_SPIN_COUNT) {
    $spin_count = (int)$spin_count;
    if ($spin_count !== BULK_SPIN_COUNT) {
        throw new Exception('Số lượt quay nhanh không hợp lệ.');
    }

    if (!$account_id) {
        throw new Exception('Không tìm thấy tài khoản.');
    }

    $conn->begin_transaction();

    try {
        $stmt_account = $conn->prepare("SELECT luotquay, thoi_vang FROM account WHERE id = ? FOR UPDATE");
        if (!$stmt_account) {
            throw new Exception('Lỗi prepare tài khoản: ' . $conn->error);
        }
        $stmt_account->bind_param("i", $account_id);
        $stmt_account->execute();
        $account_result = $stmt_account->get_result();
        $account = $account_result ? $account_result->fetch_assoc() : null;
        $stmt_account->close();

        if (!$account) {
            throw new Exception('Tài khoản không tồn tại.');
        }

        $current_spins = (int)($account['luotquay'] ?? 0);
        $current_gold = (int)($account['thoi_vang'] ?? 0);
        if ($current_spins < $spin_count) {
            throw new Exception('Bạn cần đủ ' . number_format($spin_count, 0, ',', '.') . ' lượt quay để quay nhanh.');
        }

        $summary_by_key = [];
        $total_amount = 0;
        $last_reward = null;

        for ($i = 0; $i < $spin_count; $i++) {
            $reward = lucky_pick_reward($rewards);
            $last_reward = $reward;
            $reward_key = (string)$reward['reward_key'];
            $reward_amount = (int)$reward['amount'];

            if (!isset($summary_by_key[$reward_key])) {
                $summary_by_key[$reward_key] = [
                    'reward_key' => $reward_key,
                    'label' => (string)$reward['label'],
                    'amount' => $reward_amount,
                    'color' => (string)$reward['color'],
                    'count' => 0,
                    'total_amount' => 0,
                ];
            }

            $summary_by_key[$reward_key]['count']++;
            $summary_by_key[$reward_key]['total_amount'] += $reward_amount;
            $total_amount += $reward_amount;
        }

        if ($last_reward === null) {
            throw new Exception('Không thể random phần thưởng.');
        }

        $stmt_update = $conn->prepare("UPDATE account SET luotquay = luotquay - ?, thoi_vang = thoi_vang + ? WHERE id = ? AND luotquay >= ?");
        if (!$stmt_update) {
            throw new Exception('Lỗi prepare cập nhật quay nhanh: ' . $conn->error);
        }
        $stmt_update->bind_param("iiii", $spin_count, $total_amount, $account_id, $spin_count);
        if (!$stmt_update->execute() || $stmt_update->affected_rows === 0) {
            $stmt_update->close();
            throw new Exception('Không thể trừ lượt quay nhanh.');
        }
        $stmt_update->close();

        $summary = array_values($summary_by_key);
        usort($summary, function ($a, $b) {
            if ((int)$b['total_amount'] !== (int)$a['total_amount']) {
                return (int)$b['total_amount'] <=> (int)$a['total_amount'];
            }

            return (int)$b['count'] <=> (int)$a['count'];
        });

        $conn->commit();
        $_SESSION['luotquay'] = $current_spins - $spin_count;
        $_SESSION['thoi_vang'] = $current_gold + $total_amount;

        return [
            'reward' => $last_reward,
            'spin_count' => $spin_count,
            'total_amount' => $total_amount,
            'summary' => $summary,
            'remaining_spins' => $current_spins - $spin_count,
            'pending_gold' => $current_gold + $total_amount,
        ];
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function lucky_handle_withdraw_gold($conn, $account_id, $player_id) {
    $amount = (int)($_POST['withdraw_amount'] ?? 0);

    if (!$account_id) {
        throw new Exception('Không tìm thấy tài khoản.');
    }

    if (!$player_id) {
        throw new Exception('Bạn chưa có nhân vật trong game.');
    }

    if ($amount <= 0) {
        throw new Exception('Số thỏi vàng rút phải lớn hơn 0.');
    }

    if ($amount > 1000000) {
        throw new Exception('Số thỏi vàng mỗi lần rút quá lớn.');
    }

    $conn->begin_transaction();

    try {
        $stmt_account = $conn->prepare("SELECT thoi_vang FROM account WHERE id = ? FOR UPDATE");
        if (!$stmt_account) {
            throw new Exception('Lỗi prepare kho thỏi vàng: ' . $conn->error);
        }
        $stmt_account->bind_param("i", $account_id);
        $stmt_account->execute();
        $account_result = $stmt_account->get_result();
        $account = $account_result ? $account_result->fetch_assoc() : null;
        $stmt_account->close();

        if (!$account) {
            throw new Exception('Tai khoan khong ton tai.');
        }

        $current_gold = (int)($account['thoi_vang'] ?? 0);
        if ($current_gold < $amount) {
            throw new Exception('Kho thỏi vàng không đủ để rút.');
        }

        $stmt_player = $conn->prepare("SELECT items_bag FROM player WHERE id = ? AND account_id = ? FOR UPDATE");
        if (!$stmt_player) {
            throw new Exception('Loi prepare nhan vat: ' . $conn->error);
        }
        $stmt_player->bind_param("ii", $player_id, $account_id);
        $stmt_player->execute();
        $player_result = $stmt_player->get_result();
        $player = $player_result ? $player_result->fetch_assoc() : null;
        $stmt_player->close();

        if (!$player) {
            throw new Exception('Nhân vật không tồn tại hoặc không thuộc tài khoản này.');
        }

        $items = lucky_decode_items_bag($player['items_bag'] ?? '[]');
        $items = lucky_add_gold_to_bag($items, $amount);
        $new_items_bag = lucky_encode_items_bag($items);

        if ($new_items_bag === false || json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Không thể mã hóa túi đồ.');
        }

        $stmt_update_account = $conn->prepare("UPDATE account SET thoi_vang = thoi_vang - ? WHERE id = ? AND thoi_vang >= ?");
        if (!$stmt_update_account) {
            throw new Exception('Lỗi prepare trừ kho thỏi vàng: ' . $conn->error);
        }
        $stmt_update_account->bind_param("iii", $amount, $account_id, $amount);
        if (!$stmt_update_account->execute() || $stmt_update_account->affected_rows === 0) {
            $stmt_update_account->close();
            throw new Exception('Không thể trừ kho thỏi vàng.');
        }
        $stmt_update_account->close();

        $stmt_update_bag = $conn->prepare("UPDATE player SET items_bag = ? WHERE id = ? AND account_id = ?");
        if (!$stmt_update_bag) {
            throw new Exception('Lỗi prepare cập nhật túi đồ: ' . $conn->error);
        }
        $stmt_update_bag->bind_param("sii", $new_items_bag, $player_id, $account_id);
        if (!$stmt_update_bag->execute()) {
            $stmt_update_bag->close();
            throw new Exception('Không thể cập nhật túi đồ.');
        }
        $stmt_update_bag->close();

        $conn->commit();
        $_SESSION['thoi_vang'] = $current_gold - $amount;
        return $amount;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

$checkin_table_ready = true;
$checkin_table_error = '';
if ($is_logged_in) {
    try {
        lucky_ensure_checkin_table($conn);
    } catch (Exception $e) {
        $checkin_table_ready = false;
        $checkin_table_error = $e->getMessage();
        error_log($checkin_table_error);
    }
}

$wheel_segments = lucky_build_wheel_segments($lucky_rewards);
$is_ajax_request = lucky_is_ajax_request();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_logged_in) {
        if ($is_ajax_request) {
            lucky_json_response(['ok' => false, 'message' => 'Bạn cần đăng nhập để thao tác.'], 401);
        }

        lucky_set_message('Bạn cần đăng nhập để thao tác.', 'error');
        header("Location: /app/vong-quay.php");
        exit();
    }

    try {
        $posted_token = $_POST['csrf_token'] ?? '';
        if (!is_string($posted_token) || !hash_equals($csrf_token, $posted_token)) {
            throw new Exception('Phiên thao tác không hợp lệ, vui lòng tải lại trang.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'daily_checkin') {
            if (!$checkin_table_ready) {
                throw new Exception('Điểm danh tạm thời chưa sẵn sàng.');
            }
            $spin_reward = lucky_handle_daily_checkin($conn, $account_id);
            lucky_set_message('Điểm danh thành công, bạn nhận ' . $spin_reward . ' lượt quay.', 'success');
            if ($is_ajax_request) {
                lucky_json_response([
                    'ok' => true,
                    'message' => 'Điểm danh thành công, bạn nhận ' . $spin_reward . ' lượt quay.',
                    'state' => lucky_get_account_state($conn, $account_id),
                    'checked_in_today' => true,
                ]);
            }
        } elseif ($action === 'lucky_spin') {
            $spin = lucky_handle_spin($conn, $account_id, $lucky_rewards);
            $reward = $spin['reward'];
            $visual_target = lucky_wheel_target_for_reward($wheel_segments, $reward['reward_key']);
            if ($visual_target === null) {
                throw new Exception('Không thể xác định vị trí phần thưởng trên vòng quay.');
            }

            if ((int)$reward['amount'] > 0) {
                $result_payload = [
                    'type' => 'win',
                    'reward_key' => $reward['reward_key'],
                    'label' => $reward['label'],
                    'amount' => (int)$reward['amount'],
                ];
                lucky_set_message(
                    'Chúc mừng! Bạn trúng ' . $reward['label'] . '.',
                    'success',
                    $result_payload
                );
            } else {
                $result_payload = [
                    'type' => 'miss',
                    'reward_key' => $reward['reward_key'],
                    'label' => $reward['label'],
                    'amount' => 0,
                ];
                lucky_set_message(
                    'Chúc may mắn lần sau.',
                    'warning',
                    $result_payload
                );
            }

            if ($is_ajax_request) {
                lucky_json_response([
                    'ok' => true,
                    'message' => (int)$reward['amount'] > 0
                        ? 'Chúc mừng! Bạn trúng ' . $reward['label'] . '.'
                        : 'Chúc may mắn lần sau.',
                    'result' => array_merge($result_payload, $visual_target),
                    'state' => [
                        'luotquay' => (int)$spin['remaining_spins'],
                        'thoi_vang' => (int)$spin['pending_gold'],
                    ],
                ]);
            }
        } elseif ($action === 'lucky_spin_bulk') {
            $bulk_spin = lucky_handle_bulk_spin($conn, $account_id, $lucky_rewards, BULK_SPIN_COUNT);
            $reward = $bulk_spin['reward'];
            $visual_target = lucky_wheel_target_for_reward($wheel_segments, $reward['reward_key']);
            if ($visual_target === null) {
                throw new Exception('Không thể xác định vị trí phần thưởng trên vòng quay.');
            }

            $result_payload = [
                'type' => 'bulk',
                'reward_key' => $reward['reward_key'],
                'label' => $reward['label'],
                'amount' => (int)$reward['amount'],
                'spin_count' => (int)$bulk_spin['spin_count'],
                'total_amount' => (int)$bulk_spin['total_amount'],
                'summary' => $bulk_spin['summary'],
            ];

            lucky_set_message(
                'Quay ' . number_format(BULK_SPIN_COUNT, 0, ',', '.') . ' lần hoàn tất. Tổng nhận ' . number_format((int)$bulk_spin['total_amount'], 0, ',', '.') . ' TV.',
                'success',
                $result_payload
            );

            if ($is_ajax_request) {
                lucky_json_response([
                    'ok' => true,
                    'message' => 'Quay ' . number_format(BULK_SPIN_COUNT, 0, ',', '.') . ' lần hoàn tất.',
                    'result' => array_merge($result_payload, $visual_target),
                    'state' => [
                        'luotquay' => (int)$bulk_spin['remaining_spins'],
                        'thoi_vang' => (int)$bulk_spin['pending_gold'],
                    ],
                ]);
            }
        } elseif ($action === 'withdraw_gold') {
            $withdrawn_amount = lucky_handle_withdraw_gold($conn, $account_id, $current_player_id);
            lucky_set_message('Rút thành công ' . number_format($withdrawn_amount, 0, ',', '.') . ' TV vào túi đồ.', 'success');
            if ($is_ajax_request) {
                lucky_json_response([
                    'ok' => true,
                    'message' => 'Rút thành công ' . number_format($withdrawn_amount, 0, ',', '.') . ' TV vào túi đồ.',
                    'state' => lucky_get_account_state($conn, $account_id),
                ]);
            }
        } else {
            throw new Exception('Thao tác không hợp lệ.');
        }
    } catch (Exception $e) {
        if ($is_ajax_request) {
            lucky_json_response(['ok' => false, 'message' => $e->getMessage()], 400);
        }

        lucky_set_message($e->getMessage(), 'error');
    }

    header("Location: /app/vong-quay.php");
    exit();
}

$account_state = $is_logged_in ? lucky_get_account_state($conn, $account_id) : ['luotquay' => 0, 'thoi_vang' => 0];
$remaining_spins = (int)$account_state['luotquay'];
$pending_gold = (int)$account_state['thoi_vang'];
$checked_in_today = $is_logged_in && $checkin_table_ready ? lucky_has_checked_in_today($conn, $account_id) : false;

$wheel_gradient_parts = [];
foreach ($wheel_segments as $segment) {
    $wheel_gradient_parts[] = $segment['color'] . ' ' . lucky_format_degrees($segment['start']) . 'deg ' . lucky_format_degrees($segment['end']) . 'deg';
}
$wheel_gradient = !empty($wheel_gradient_parts) ? implode(', ', $wheel_gradient_parts) : '#64748b 0deg 360deg';
$wheel_segment_count = max(1, count($wheel_segments));
$wheel_result_key = is_array($spin_result) ? (string)($spin_result['reward_key'] ?? '') : '';
$wheel_settled_target = $wheel_result_key !== '' ? lucky_wheel_target_for_reward($wheel_segments, $wheel_result_key) : null;
$wheel_settled_rotation_text = $wheel_settled_target !== null ? lucky_format_degrees($wheel_settled_target['target_rotation']) : '';
$wheel_class = 'wheel' . ($wheel_settled_rotation_text !== '' ? ' has-result' : '');
$wheel_config = [
    'segments' => lucky_segment_json($wheel_segments),
    'spinDurationMs' => 6200,
    'bulkSpinCount' => BULK_SPIN_COUNT,
];
$wheel_config_json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $wheel_config_json_flags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$wheel_config_json = json_encode($wheel_config, $wheel_config_json_flags);
if ($wheel_config_json === false) {
    $wheel_config_json = '{"segments":[],"spinDurationMs":6200}';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vòng Quay May Mắn - Lio Universe</title>
    <link rel="icon" href="/images/favicon-48x48.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/view/static/css/template.css?v=2.0">
    <link rel="stylesheet" href="/view/static/css/eff.css?v=1.00">
    <link rel="stylesheet" href="/view/static/css/w3.css?v=1.01">
    <link rel="stylesheet" href="/view/static/css/styleSheet.css?v=2.1">
    <link rel="stylesheet" href="/view/static/css/forum.css?v=2.1">
    <script src="/view/static/js/disable_devtools.js"></script>
    <style>
        .lucky-wrap {
            max-width: 820px;
            margin: 0 auto;
        }

        .lucky-panel {
            background: rgba(255, 255, 255, 0.96) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1.5px solid rgba(226, 232, 240, 0.95) !important;
            border-radius: 28px !important;
            padding: 30px 24px !important;
            margin: 10px auto !important;
            text-align: center;
            box-shadow: 0 15px 35px -5px rgba(2, 132, 199, 0.12), 0 0 0 1px rgba(255, 255, 255, 0.8) inset !important;
            position: relative;
            overflow: hidden;
        }

        .lucky-panel::before {
            content: "";
            position: absolute;
            top: -80px;
            left: 50%;
            transform: translateX(-50%);
            width: 360px;
            height: 180px;
            background: radial-gradient(ellipse, rgba(249, 115, 22, 0.15) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .lucky-panel h2 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif !important;
            font-size: 26px !important;
            font-weight: 900 !important;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 45%, #f59e0b 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            margin: 0 0 16px !important;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            filter: drop-shadow(0 2px 8px rgba(249, 115, 22, 0.25));
        }

        .lucky-status {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 12px 0 14px;
        }

        .lucky-status span {
            background: #ffffff !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 8px 14px !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03) !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #334155 !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .lucky-status span:hover {
            transform: translateY(-1px);
            border-color: #cbd5e1;
        }

        .lucky-status strong {
            font-weight: 800;
        }

        #remainingSpinsText {
            color: #f97316 !important;
        }

        #pendingGoldText {
            color: #b45309 !important;
        }

        .lucky-note {
            margin: 0 auto 16px;
            max-width: 620px;
            background: #fffbeb;
            border: 1px solid #fef08a;
            border-radius: 12px;
            padding: 9px 16px;
            color: #92400e;
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1.5;
        }

        .action-row {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 16px 0 18px;
        }

        .checkin-button,
        .spin-button,
        .spin-bulk-button {
            border: none !important;
            border-radius: 12px !important;
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-weight: 800 !important;
            font-size: 13.5px !important;
            padding: 11px 22px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            letter-spacing: 0.3px !important;
            transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-sizing: border-box !important;
        }

        .checkin-button {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3) !important;
        }
        .checkin-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(16, 185, 129, 0.45) !important;
        }

        .spin-button {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.35) !important;
        }
        .spin-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 7px 22px rgba(249, 115, 22, 0.5) !important;
        }

        .spin-bulk-button {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35) !important;
        }
        .spin-bulk-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 7px 22px rgba(239, 68, 68, 0.5) !important;
        }

        .checkin-button:disabled,
        .spin-button:disabled,
        .spin-bulk-button:disabled {
            background: #cbd5e1 !important;
            color: #64748b !important;
            box-shadow: none !important;
            cursor: not-allowed !important;
            transform: none !important;
        }

        .withdraw-box {
            background: #fffbeb !important;
            border: 1.5px solid #fde68a !important;
            border-radius: 16px !important;
            padding: 16px 20px !important;
            margin: 14px auto 18px !important;
            max-width: 440px !important;
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.12) !important;
        }

        .withdraw-box strong {
            color: #92400e !important;
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 800;
        }

        .withdraw-form {
            display: grid !important;
            grid-template-columns: 1fr auto !important;
            gap: 10px !important;
        }

        .withdraw-form input {
            border: 1.5px solid #fcd34d !important;
            border-radius: 10px !important;
            padding: 10px 14px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            color: #78350f !important;
            background: #ffffff !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }

        .withdraw-form input:focus {
            border-color: #f59e0b !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25) !important;
        }

        .withdraw-button {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 10px 20px !important;
            font-weight: 800 !important;
            font-size: 13.5px !important;
            cursor: pointer !important;
            box-shadow: 0 3px 10px rgba(217, 119, 6, 0.3) !important;
            transition: all 0.2s ease !important;
        }

        .withdraw-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(217, 119, 6, 0.45) !important;
        }

        /* ================================================================
           THE FORTUNE WHEEL STYLING
           ================================================================ */
        .wheel-area {
            position: relative;
            width: min(86vw, 420px);
            aspect-ratio: 1;
            margin: 20px auto 14px;
            border-radius: 50%;
            padding: 14px;
            box-sizing: border-box;
            background: radial-gradient(circle, #fef3c7 0%, #fed7aa 58%, transparent 72%);
            box-shadow: 0 16px 40px -10px rgba(249, 115, 22, 0.25);
        }

        .wheel-area::before {
            content: "";
            position: absolute;
            inset: 3px;
            border-radius: 50%;
            border: 8px dotted rgba(245, 158, 11, 0.7);
            filter: drop-shadow(0 0 4px rgba(251, 191, 36, 0.85));
            pointer-events: none;
            z-index: 1;
        }

        .wheel-pointer {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 34px;
            height: 50px;
            z-index: 10;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.35));
            transform-origin: 50% 12px;
        }

        .wheel-pointer::before {
            content: "";
            position: absolute;
            top: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-top: 38px solid #dc2626;
            filter: drop-shadow(0 1px 2px rgba(185, 28, 28, 0.6));
        }

        .wheel-pointer::after {
            content: "";
            position: absolute;
            top: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, #fff 0%, #fbbf24 60%, #b45309 100%);
            border: 2px solid #ffffff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .wheel-area.is-spinning .wheel-pointer {
            animation: pointerTick 120ms linear infinite;
        }

        @keyframes pointerTick {
            0%, 100% { transform: translateX(-50%) rotate(0deg); }
            50% { transform: translateX(-50%) rotate(-8deg); }
        }

        .wheel {
            position: relative;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            border-radius: 50%;
            overflow: hidden;
            background:
                radial-gradient(circle at 50% 36%, rgba(255,255,255,0.38), transparent 26%),
                conic-gradient(from -90deg, <?php echo htmlspecialchars($wheel_gradient); ?>);
            border: 12px solid #b45309;
            box-shadow:
                inset 0 0 0 3px #fbbf24,
                inset 0 0 0 6px #78350f,
                inset 0 0 30px rgba(0, 0, 0, 0.25),
                0 0 0 3px #fef08a,
                0 14px 35px rgba(180, 83, 9, 0.35);
            transform: rotate(0deg);
            will-change: transform;
        }

        .wheel::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background:
                radial-gradient(circle at 35% 24%, rgba(255,255,255,0.32), transparent 22%),
                radial-gradient(circle at 50% 50%, transparent 0 54%, rgba(0,0,0,0.14) 100%);
            pointer-events: none;
            z-index: 3;
        }

        .wheel-separator {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 50%;
            height: 1.5px;
            background: rgba(255, 255, 255, 0.85);
            transform: rotate(calc(var(--angle) - 90deg));
            transform-origin: 0 50%;
            z-index: 1;
            pointer-events: none;
            box-shadow: 0 0 3px rgba(0, 0, 0, 0.25);
        }

        .wheel-label {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 86px;
            min-height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translate(-50%, -50%) rotate(var(--angle)) translateY(-138px) rotate(var(--orient, 90deg));
            transform-origin: center;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 11.5px;
            font-weight: 900;
            letter-spacing: 0.4px;
            line-height: 1;
            text-align: center;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.75), 0 0 6px rgba(0, 0, 0, 0.45);
            z-index: 1;
            pointer-events: none;
            white-space: nowrap;
        }

        /* Perfectly concentric 3D Center Button */
        .wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 104px;
            height: 104px;
            z-index: 6;
            border-radius: 50%;
            background: radial-gradient(circle at 36% 28%, #fff7ed 0%, #fdba74 25%, #f97316 65%, #c2410c 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            font-weight: 900;
            font-size: 17px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border: 4px solid #ffffff;
            box-shadow:
                0 0 0 4px #fbbf24,
                0 0 0 10px #ffffff,
                0 0 0 13px #f59e0b,
                0 10px 25px rgba(180, 83, 9, 0.4),
                inset 0 3px 5px rgba(255, 255, 255, 0.75),
                inset 0 -4px 8px rgba(154, 52, 18, 0.5);
            cursor: pointer;
            outline: 0;
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-shadow: 0 2px 4px rgba(124, 45, 18, 0.6);
        }

        .wheel-center:hover:not(:disabled) {
            transform: translate(-50%, -50%) scale(1.05);
            box-shadow:
                0 0 0 4px #fbbf24,
                0 0 0 10px #ffffff,
                0 0 0 14px #ea580c,
                0 14px 30px rgba(234, 88, 12, 0.55),
                inset 0 3px 5px rgba(255, 255, 255, 0.9),
                inset 0 -4px 8px rgba(154, 52, 18, 0.5);
            filter: brightness(1.05);
        }

        .wheel-center:active:not(:disabled) {
            transform: translate(-50%, -50%) scale(0.96);
        }

        .wheel-center:disabled {
            background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%) !important;
            border-color: #f1f5f9 !important;
            box-shadow:
                0 0 0 4px #cbd5e1,
                0 0 0 10px #ffffff,
                0 0 0 13px #94a3b8 !important;
            cursor: not-allowed !important;
            color: #64748b !important;
            text-shadow: none !important;
        }

        .spin-live-status {
            min-height: 28px;
            margin: 8px auto 4px;
            color: #92400e;
            font-size: 13px;
            font-weight: 800;
        }
        .spin-live-status.is-active {
            color: #ea580c;
        }

        .reward-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin: 14px auto 16px;
            max-width: 680px;
        }

        .reward-pill {
            align-items: center;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 20px;
            color: #334155;
            display: inline-flex;
            font-size: 12px;
            font-weight: 800;
            gap: 6px;
            padding: 6px 12px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
        }

        .reward-pill:hover {
            transform: translateY(-2px);
            border-color: #f97316;
            box-shadow: 0 4px 10px rgba(249, 115, 22, 0.15);
        }

        .reward-pill i {
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.85);
            display: inline-block;
            height: 10px;
            width: 10px;
        }

        .quick-links {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        @media (max-width: 480px) {
            .lucky-panel {
                padding: 20px 14px !important;
                border-radius: 20px !important;
            }
            .lucky-panel h2 {
                font-size: 21px !important;
            }
            .wheel-area {
                padding: 10px;
            }
            .wheel-label {
                width: 70px;
                font-size: 10px;
                transform: translate(-50%, -50%) rotate(var(--angle)) translateY(-120px) rotate(var(--orient, 90deg));
            }
            .wheel-center {
                width: 88px;
                height: 88px;
                font-size: 15px;
            }
            .withdraw-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="snowEffect">
        <canvas id="snowcanvas" height="100%" width="100%"></canvas>
    </div>

    <div class="body_body">
        <a href="#" id="backTop"><img id='backTopimg' src='/images/favicon-32x32.png' alt='top' /></a>

        <div class="div-12">
            <img height=12 src="/images/18-1.png" style="vertical-align: middle;" />
            <span style="vertical-align: middle;">Dành cho người chơi trên 18 tuổi. Chơi quá 180 phút mỗi ngày sẽ hại sức khỏe.</span>
        </div>

        <div class="body-content">
            <div class="bg-content2">
                <h1 class="a">
                    <a href="/" title="Lio Universe - Chiến Binh Vũ Trụ">
                        <img height=105 src="/images/logo_liodev.svg" alt="Lio Universe - Chiến Binh Vũ Trụ" />
                    </a>
                </h1>

                <div id="top">
                    <div class="link-more">
                        <div class="h">
                            <div class="menu2">
                                <table width="100%" cellspacing="4">
                                    <tr class="menu">
                                        <td><a href="/trang-chu.php">Trang Chủ</a></td>
                                        <td><a href="/gioi-thieu.php">Giới Thiệu</a></td>
                                        <td><a href="/forum.php" title="Diễn Đàn">Diễn Đàn</a></td>
                                        <td><a href="https://www.facebook.com/ntdinh24/" target="_blank">Fanpage</a></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="body">
                    <div class="lucky-wrap">
                        <div class="lucky-panel">
                            <h2><i class="bi bi-stars"></i> Vòng Quay May Mắn</h2>

                                    <?php if (!$is_logged_in): ?>
                                        <div class="message error">Bạn cần đăng nhập để quay.</div>
                                        <div class="quick-links">
                    <a href="/forum.php" class="user-action-btn standard"><i class="bi bi-chat-left-text"></i> Về diễn đàn</a>
                    <a href="/app/nap-ngoc.php" class="user-action-btn primary"><i class="bi bi-wallet2"></i> Nạp tiền</a>
                    <a href="/app/doi-vang.php" class="user-action-btn gold"><i class="bi bi-cash-stack"></i> Đổi thỏi vàng</a>
                </div>
            <?php endif; ?>

                                        <div id="spinResultSlot">
                                            <?php if (is_array($spin_result)): ?>
                                                <?php
                                                    $result_raw_type = (string)($spin_result['type'] ?? '');
                                                    $result_type = in_array($result_raw_type, ['win', 'bulk'], true) ? $result_raw_type : 'miss';
                                                    $result_label = (string)($spin_result['label'] ?? '');
                                                    $result_amount = (int)($spin_result['amount'] ?? 0);
                                                    $result_spin_count = (int)($spin_result['spin_count'] ?? BULK_SPIN_COUNT);
                                                    $result_total_amount = (int)($spin_result['total_amount'] ?? 0);
                                                    $result_summary = is_array($spin_result['summary'] ?? null) ? $spin_result['summary'] : [];
                                                ?>
                                                <div class="spin-result-card <?php echo htmlspecialchars($result_type); ?>" id="spinResult">
                                                    <?php if ($result_type === 'bulk'): ?>
                                                        <div class="result-eyebrow">Kết quả quay nhanh</div>
                                                        <div class="result-title">Đã random đủ <?php echo number_format($result_spin_count, 0, ',', '.'); ?> lần</div>
                                                        <div class="result-prize"><?php echo number_format($result_total_amount, 0, ',', '.'); ?> TV</div>
                                                        <div class="result-desc">
                                                            Tổng TV đã được cộng vào kho chờ rút. Kết quả bên dưới là thống kê từng phần thưởng trúng trong <?php echo number_format($result_spin_count, 0, ',', '.'); ?> lượt.
                                                        </div>
                                                        <?php if (!empty($result_summary)): ?>
                                                            <div class="bulk-summary">
                                                                <?php foreach ($result_summary as $summary_item): ?>
                                                                    <div class="bulk-summary-item">
                                                                        <strong><?php echo htmlspecialchars((string)($summary_item['label'] ?? '')); ?></strong>
                                                                        <span>x<?php echo number_format((int)($summary_item['count'] ?? 0), 0, ',', '.'); ?><?php if ((int)($summary_item['total_amount'] ?? 0) > 0): ?> · <?php echo number_format((int)$summary_item['total_amount'], 0, ',', '.'); ?> TV<?php endif; ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="result-actions">
                                                            <a class="result-link" href="#withdrawGold">Rút thỏi vàng</a>
                                                        </div>
                                                    <?php elseif ($result_type === 'win'): ?>
                                                        <div class="result-eyebrow">Kết quả quay</div>
                                                        <div class="result-title">Chúc mừng bạn đã trúng</div>
                                                        <div class="result-prize"><?php echo htmlspecialchars($result_label); ?></div>
                                                        <div class="result-desc">
                                                            <?php echo number_format($result_amount, 0, ',', '.'); ?> TV đã được cộng vào kho chờ rút. Hãy thoát game trước khi rút vào túi đồ.
                                                        </div>
                                                        <div class="result-actions">
                                                            <a class="result-link" href="#withdrawGold">Rút thỏi vàng</a>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="result-eyebrow">Kết quả quay</div>
                                                        <div class="result-title">Chúc bạn may mắn lần sau</div>
                                                        <div class="result-prize"><?php echo htmlspecialchars($result_label ?: 'Chúc may mắn'); ?></div>
                                                        <div class="result-desc">Lần này chưa trúng TV, bạn có thể điểm danh hoặc tích lũy nạp để nhận thêm lượt quay.</div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($message !== ''): ?>
                                                <div class="message <?php echo htmlspecialchars($message_type); ?>">
                                                    <?php echo htmlspecialchars($message); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="action-row">
                    <form method="post" action="/app/vong-quay.php">
                        <input type="hidden" name="action" value="daily_checkin">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button class="checkin-button" type="submit" <?php echo ($checked_in_today || !$checkin_table_ready) ? 'disabled' : ''; ?>>
                            <i class="bi bi-calendar-check-fill"></i> Điểm danh
                        </button>
                    </form>

                    <form id="luckySpinForm" method="post" action="/app/vong-quay.php">
                        <input type="hidden" name="action" value="lucky_spin">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button class="spin-button" type="submit" <?php echo $remaining_spins <= 0 ? 'disabled' : ''; ?>>
                            <i class="bi bi-play-circle-fill"></i> Quay ngay
                        </button>
                    </form>

                    <form id="luckySpinBulkForm" method="post" action="/app/vong-quay.php">
                        <input type="hidden" name="action" value="lucky_spin_bulk">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button class="spin-bulk-button" type="submit" <?php echo $remaining_spins < BULK_SPIN_COUNT ? 'disabled' : ''; ?>>
                            <i class="bi bi-lightning-charge-fill"></i> Quay 100 lần
                        </button>
                    </form>
                </div>

                <div id="withdrawGoldSlot">
                                        <?php if ($pending_gold > 0): ?>
                                            <div class="withdraw-box" id="withdrawGold">
                                                <strong>Rút thỏi vàng vào túi đồ</strong>
                                                <form class="withdraw-form" method="post" action="/app/vong-quay.php">
                                                    <input type="hidden" name="action" value="withdraw_gold">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input name="withdraw_amount" type="number" min="1" max="<?php echo $pending_gold; ?>" value="<?php echo $pending_gold; ?>" required>
                                                    <button class="withdraw-button" type="submit">Rút</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        </div>

                                        <div class="wheel-area" id="wheelArea">
                                            <div class="wheel-pointer"></div>
                                            <div id="luckyWheel" class="<?php echo htmlspecialchars($wheel_class); ?>" data-current-rotation="<?php echo htmlspecialchars($wheel_settled_rotation_text !== '' ? $wheel_settled_rotation_text : '0'); ?>"<?php if ($wheel_settled_rotation_text !== ''): ?> style="--settled-rotation: <?php echo htmlspecialchars($wheel_settled_rotation_text); ?>deg;" data-settled-rotation="<?php echo htmlspecialchars($wheel_settled_rotation_text); ?>"<?php endif; ?>>
                                                <?php foreach ($wheel_segments as $segment): ?>
                                        <?php
                                            $deg = (float)$segment['center'];
                                            // Left side: 180 to 360 deg -> orient is 90deg
                                            // Right side: 0 to 180 deg -> orient is -90deg
                                            $is_left = ($deg >= 180 && $deg < 360);
                                            $label_orient = $is_left ? '90deg' : '-90deg';
                                        ?>
                                        <span class="wheel-separator" style="--angle: <?php echo htmlspecialchars(lucky_format_degrees($segment['start'])); ?>deg;"></span>
                                        <?php if ((float)$segment['degrees'] >= 7): ?>
                                            <span class="wheel-label" style="--angle: <?php echo htmlspecialchars(lucky_format_degrees($segment['center'])); ?>deg; --orient: <?php echo $label_orient; ?>;">
                                                <?php echo htmlspecialchars($segment['label']); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                </div>
                <button id="wheelCenterSpin" class="wheel-center" type="button" <?php echo $remaining_spins <= 0 ? 'disabled' : ''; ?>>Quay</button> class="wheel-center" type="button" <?php echo $remaining_spins <= 0 ? 'disabled' : ''; ?>>Quay</button>
                                        </div>
                                        <div class="spin-live-status" id="spinLiveStatus"></div>

                                        <div class="reward-pills">
                                            <?php foreach ($wheel_segments as $segment): ?>
                                                <span class="reward-pill"><i style="background: <?php echo htmlspecialchars($segment['color']); ?>"></i><?php echo htmlspecialchars($segment['label']); ?></span>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="quick-links">
                                            <a href="/forum.php">Về diễn đàn</a>
                                            <a href="/app/nap-ngoc.php">Nạp tiền</a>
                                            <a href="/app/doi-vang.php">Đổi thỏi vàng</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><br>
            </div>
        </div>
        <div class="left_b_bottom">
            <div class="right_b_bottom">
                <div class="footer"><div class="left_bottom"></div><div class="right_bottom"></div></div>
            </div>
        </div>
    </div>

    <script>
        window.luckyWheelConfig = <?php echo $wheel_config_json; ?>;
    </script>
    <script>
        (function () {
            var form = document.getElementById('luckySpinForm');
            var bulkForm = document.getElementById('luckySpinBulkForm');
            var wheel = document.getElementById('luckyWheel');
            var wheelArea = document.getElementById('wheelArea');
            var centerButton = document.getElementById('wheelCenterSpin');
            var resultSlot = document.getElementById('spinResultSlot');
            var liveStatus = document.getElementById('spinLiveStatus');
            var remainingSpinsText = document.getElementById('remainingSpinsText');
            var pendingGoldText = document.getElementById('pendingGoldText');
            var withdrawGoldSlot = document.getElementById('withdrawGoldSlot');
            var config = window.luckyWheelConfig || {};
            var baseDuration = Number(config.spinDurationMs || 6200);
            var bulkSpinCount = Number(config.bulkSpinCount || 100);
            var currentRotation = Number(wheel ? wheel.getAttribute('data-current-rotation') : 0) || 0;

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatNumber(value) {
                return new Intl.NumberFormat('vi-VN').format(Number(value || 0));
            }

            function normalizeDegrees(value) {
                var result = Number(value) % 360;
                return result < 0 ? result + 360 : result;
            }

            function secureRandomUnit() {
                if (window.crypto && window.crypto.getRandomValues) {
                    var data = new Uint32Array(1);
                    window.crypto.getRandomValues(data);
                    return data[0] / 4294967295;
                }

                return Math.random();
            }

            function setLiveStatus(text, active) {
                if (!liveStatus) {
                    return;
                }

                liveStatus.textContent = text || '';
                liveStatus.classList.toggle('is-active', !!active);
            }

            function getCurrentRemainingSpins() {
                if (!remainingSpinsText) {
                    return 0;
                }

                return Number(String(remainingSpinsText.textContent || '0').replace(/\D/g, '')) || 0;
            }

            function setSpinningState(isSpinning) {
                var remaining = getCurrentRemainingSpins();
                var submitButton = form ? form.querySelector('button[type="submit"]') : null;
                var bulkButton = bulkForm ? bulkForm.querySelector('button[type="submit"]') : null;
                if (submitButton) {
                    submitButton.disabled = isSpinning || remaining <= 0;
                    submitButton.textContent = isSpinning ? 'Đang quay...' : 'Quay ngay';
                }
                if (bulkButton) {
                    bulkButton.disabled = isSpinning || remaining < bulkSpinCount;
                    bulkButton.textContent = isSpinning ? 'Đang quay...' : 'Quay 100 lần';
                }
                if (centerButton) {
                    centerButton.disabled = isSpinning || remaining <= 0;
                    centerButton.textContent = isSpinning ? 'Đang quay' : 'Quay';
                }
                if (wheelArea) {
                    wheelArea.classList.toggle('is-spinning', isSpinning);
                }
                if (wheel) {
                    wheel.classList.toggle('is-spinning', isSpinning);
                }
            }

            function updateState(state) {
                if (!state) {
                    return;
                }

                var remaining = Number(state.luotquay || 0);
                var pendingGold = Number(state.thoi_vang || 0);
                if (remainingSpinsText) {
                    remainingSpinsText.textContent = formatNumber(remaining);
                }
                if (pendingGoldText) {
                    pendingGoldText.textContent = formatNumber(pendingGold);
                }
                renderWithdrawBox(pendingGold);
            }

            function renderWithdrawBox(pendingGold) {
                if (!withdrawGoldSlot) {
                    return;
                }

                if (pendingGold <= 0) {
                    withdrawGoldSlot.innerHTML = '';
                    return;
                }

                var token = form ? form.querySelector('input[name="csrf_token"]').value : '';
                withdrawGoldSlot.innerHTML =
                    '<div class="withdraw-box" id="withdrawGold">' +
                        '<strong>Rút thỏi vàng vào túi đồ</strong>' +
                        '<form class="withdraw-form" method="post" action="/app/vong-quay.php">' +
                            '<input type="hidden" name="action" value="withdraw_gold">' +
                            '<input type="hidden" name="csrf_token" value="' + escapeHtml(token) + '">' +
                            '<input name="withdraw_amount" type="number" min="1" max="' + pendingGold + '" value="' + pendingGold + '" required>' +
                            '<button class="withdraw-button" type="submit">Rút</button>' +
                        '</form>' +
                    '</div>';
            }

            function resultHtml(result) {
                var label = escapeHtml(result.label || 'Chúc may mắn');
                var amount = Number(result.amount || 0);

                if (result.type === 'bulk') {
                    var spinCount = Number(result.spin_count || bulkSpinCount);
                    var totalAmount = Number(result.total_amount || 0);
                    var summary = Array.isArray(result.summary) ? result.summary : [];
                    var summaryHtml = '';

                    summary.forEach(function (item) {
                        var itemTotal = Number(item.total_amount || 0);
                        summaryHtml += '' +
                            '<div class="bulk-summary-item">' +
                                '<strong>' + escapeHtml(item.label || '') + '</strong>' +
                                '<span>x' + formatNumber(item.count || 0) + (itemTotal > 0 ? ' · ' + formatNumber(itemTotal) + ' TV' : '') + '</span>' +
                            '</div>';
                    });

                    return '' +
                        '<div class="spin-result-card bulk" id="spinResult">' +
                            '<div class="result-eyebrow">Kết quả quay nhanh</div>' +
                            '<div class="result-title">Đã random đủ ' + formatNumber(spinCount) + ' lần</div>' +
                            '<div class="result-prize">' + formatNumber(totalAmount) + ' TV</div>' +
                            '<div class="result-desc">Tổng TV đã được cộng vào kho chờ rút. Bảng dưới là thống kê từng phần thưởng trúng trong ' + formatNumber(spinCount) + ' lượt.</div>' +
                            (summaryHtml ? '<div class="bulk-summary">' + summaryHtml + '</div>' : '') +
                            '<div class="result-actions"><a class="result-link" href="#withdrawGold">Rút thỏi vàng</a></div>' +
                        '</div>';
                }

                if (result.type === 'win') {
                    return '' +
                        '<div class="spin-result-card win" id="spinResult">' +
                            '<div class="result-eyebrow">Kết quả quay</div>' +
                            '<div class="result-title">Chúc mừng bạn đã trúng</div>' +
                            '<div class="result-prize">' + label + '</div>' +
                            '<div class="result-desc">' + formatNumber(amount) + ' TV đã được cộng vào kho chờ rút. Hãy thoát game trước khi rút vào túi đồ.</div>' +
                            '<div class="result-actions"><a class="result-link" href="#withdrawGold">Rút thỏi vàng</a></div>' +
                        '</div>';
                }

                return '' +
                    '<div class="spin-result-card miss" id="spinResult">' +
                        '<div class="result-eyebrow">Kết quả quay</div>' +
                        '<div class="result-title">Chúc bạn may mắn lần sau</div>' +
                        '<div class="result-prize">' + label + '</div>' +
                        '<div class="result-desc">Lần này chưa trúng TV, bạn có thể điểm danh hoặc tích lũy nạp để nhận thêm lượt quay.</div>' +
                    '</div>';
            }

            function showMessage(message, type) {
                if (!resultSlot) {
                    return;
                }

                resultSlot.innerHTML = '<div class="message ' + escapeHtml(type || 'error') + '">' + escapeHtml(message) + '</div>';
            }

            function easeOutPhysical(t) {
                return 1 - Math.pow(1 - t, 4.6);
            }

            function animateToRotation(finalRotation, duration) {
                return new Promise(function (resolve) {
                    var startRotation = currentRotation;
                    var travel = finalRotation - startRotation;
                    var startTime = null;

                    function frame(now) {
                        if (startTime === null) {
                            startTime = now;
                        }

                        var progress = Math.min(1, (now - startTime) / duration);
                        var eased = easeOutPhysical(progress);
                        var wobble = 0;
                        if (progress > 0.84 && progress < 0.995) {
                            var settle = (progress - 0.84) / 0.155;
                            wobble = Math.sin(settle * Math.PI * 5) * (1 - progress) * 2.6;
                        }

                        var rotation = startRotation + (travel * eased) + wobble;
                        wheel.style.transform = 'rotate(' + rotation + 'deg)';

                        if (progress < 1) {
                            window.requestAnimationFrame(frame);
                            return;
                        }

                        currentRotation = finalRotation;
                        wheel.style.transform = 'rotate(' + finalRotation + 'deg)';
                        wheel.style.setProperty('--settled-rotation', finalRotation + 'deg');
                        wheel.setAttribute('data-current-rotation', String(finalRotation));
                        wheel.setAttribute('data-settled-rotation', String(finalRotation));
                        wheel.classList.add('has-result');
                        resolve();
                    }

                    window.requestAnimationFrame(frame);
                });
            }

            function nextFinalRotation(targetModulo, turns) {
                var currentModulo = normalizeDegrees(currentRotation);
                var delta = normalizeDegrees(Number(targetModulo) - currentModulo);
                return currentRotation + (Number(turns || 8) * 360) + delta;
            }

            var existingResult = document.getElementById('spinResult');
            if (existingResult) {
                window.setTimeout(function () {
                    existingResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 120);
            }

            if (!form || !wheel) {
                return;
            }

            if (centerButton) {
                centerButton.addEventListener('click', function () {
                    var submitButton = form.querySelector('button[type="submit"]');
                    if (!submitButton || centerButton.disabled || submitButton.disabled) {
                        return;
                    }
                    submitButton.click();
                });
            }

            function submitSpin(event, activeForm, isBulk) {
                event.preventDefault();
                if (form.dataset.spinning === '1') {
                    return;
                }

                var submitButton = activeForm.querySelector('button[type="submit"]');
                if (submitButton && submitButton.disabled) {
                    return;
                }

                form.dataset.spinning = '1';
                if (bulkForm) {
                    bulkForm.dataset.spinning = '1';
                }
                wheel.classList.remove('has-result');
                setSpinningState(true);
                setLiveStatus(
                    isBulk
                        ? 'Đang random đủ 100 lần trên máy chủ... chờ bánh xe dừng ở kết quả cuối.'
                        : 'Đang quay... chờ bánh xe dừng để nhận kết quả.',
                    true
                );

                var formData = new FormData(activeForm);
                formData.set('ajax', '1');
                fetch('/app/vong-quay.php?ajax=1', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        return response.text().then(function (text) {
                            var data;
                            try {
                                data = text ? JSON.parse(text) : {};
                            } catch (error) {
                                var looksLikeHtml = /<!doctype|<html|<head|<body|<style|@import/i.test(text || '');
                                var cleanText = looksLikeHtml
                                    ? ''
                                    : text
                                        .replace(/<br\s*\/?>/gi, '\n')
                                        .replace(/<[^>]*>/g, ' ')
                                        .replace(/\s+/g, ' ')
                                        .trim();
                                data = {
                                    ok: false,
                                    message: cleanText
                                        ? cleanText.slice(0, 220)
                                        : 'Máy chủ đang trả về trang HTML thay vì kết quả quay. Vui lòng tải lại trang rồi thử lại.'
                                };
                            }

                            if (!response.ok || !data.ok) {
                                throw new Error(data.message || 'Không thể quay lúc này.');
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        var result = data.result || {};
                        var targetModulo = Number(result.target_rotation || 0);
                        var turns = Number(result.turns || 8);
                        var duration = baseDuration + Math.round(secureRandomUnit() * 700) + (isBulk ? 900 : 0);
                        var finalRotation = nextFinalRotation(targetModulo, turns);

                        return animateToRotation(finalRotation, duration).then(function () {
                            updateState(data.state);
                            if (resultSlot) {
                                resultSlot.innerHTML = resultHtml(result);
                                var card = document.getElementById('spinResult');
                                if (card) {
                                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                }
                            }
                            setLiveStatus(result.type === 'bulk' || result.type === 'win' ? 'Đã cộng thưởng vào kho chờ rút.' : 'Bánh xe đã dừng.', false);
                            return data;
                        });
                    })
                    .then(function (data) {
                        setSpinningState(false);
                    })
                    .catch(function (error) {
                        showMessage(error.message || 'Không thể quay lúc này.', 'error');
                        setLiveStatus('', false);
                        setSpinningState(false);
                    })
                    .finally(function () {
                        form.dataset.spinning = '0';
                        if (bulkForm) {
                            bulkForm.dataset.spinning = '0';
                        }
                    });
            }

            form.addEventListener('submit', function (event) {
                submitSpin(event, form, false);
            });

            if (bulkForm) {
                bulkForm.addEventListener('submit', function (event) {
                    submitSpin(event, bulkForm, true);
                });
            }
        })();
    </script>
</body>
</html>
