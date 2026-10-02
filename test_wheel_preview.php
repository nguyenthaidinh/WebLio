<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/lucky_rewards.php';

try {
    $lucky_rewards = lucky_rewards_load($conn);
} catch (Exception $e) {
    $lucky_rewards = LUCKY_REWARD_DEFAULTS;
}
if (empty($lucky_rewards)) {
    $lucky_rewards = LUCKY_REWARD_DEFAULTS;
}

$is_logged_in = true;
$remaining_spins = 12;
$pending_gold = 20;
$checked_in_today = false;
$checkin_table_ready = true;
$csrf_token = 'test_token';
$message = '';
$message_type = 'info';

function lucky_format_degrees($degrees) {
    return rtrim(rtrim(number_format((float)$degrees, 4, '.', ''), '0'), '.');
}

function lucky_build_wheel_segments_test($rewards) {
    $count = count($rewards);
    if ($count <= 0) return [];
    $deg_per_slice = 360.0 / $count;
    $segments = [];
    $index = 0;
    foreach ($rewards as $reward) {
        $weight = max(0, (int)$reward['weight']);
        $start = $index * $deg_per_slice;
        $end = ($index + 1) * $deg_per_slice;
        $degrees = $deg_per_slice;
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
        $index++;
    }
    if (!empty($segments)) {
        $last_index = count($segments) - 1;
        $segments[$last_index]['end'] = 360.0;
        $segments[$last_index]['degrees'] = $segments[$last_index]['end'] - $segments[$last_index]['start'];
        $segments[$last_index]['center'] = $segments[$last_index]['start'] + ($segments[$last_index]['degrees'] / 2);
    }
    return $segments;
}

$wheel_segments = lucky_build_wheel_segments_test($lucky_rewards);

$wheel_gradient_parts = [];
foreach ($wheel_segments as $segment) {
    $wheel_gradient_parts[] = $segment['color'] . ' ' . lucky_format_degrees($segment['start']) . 'deg ' . lucky_format_degrees($segment['end']) . 'deg';
}
$wheel_gradient = !empty($wheel_gradient_parts) ? implode(', ', $wheel_gradient_parts) : '#64748b 0deg 360deg';

// Pointer target for 20 TV (gold_20)
// gold_20 is index 2 -> center is 100 deg -> target rotation = 360 - 100 = 260 deg
$wheel_settled_rotation_text = '260';
$wheel_class = 'wheel has-result';

$spin_result = [
    'type' => 'win',
    'reward_key' => 'gold_20',
    'label' => '20 TV',
    'amount' => 20,
    'total_amount' => 20,
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Vòng Quay - Lio Universe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/view/static/css/template.css?v=2.0">
    <link rel="stylesheet" href="/view/static/css/styleSheet.css?v=2.1">
    <link rel="stylesheet" href="/view/static/css/forum.css?v=2.1">
    <style>
        body {
            background: #f8fafc;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 20px;
        }
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
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #334155 !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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

        /* SPIN RESULT CARD - MODERN LUXURY GLASS STYLING */
        .spin-result-card {
            position: relative;
            max-width: 480px;
            margin: 16px auto 22px;
            padding: 24px 22px;
            border-radius: 22px;
            text-align: center;
            box-sizing: border-box;
            animation: resultCardPop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        @keyframes resultCardPop {
            0% {
                opacity: 0;
                transform: scale(0.92) translateY(12px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .spin-result-card.win {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(254, 243, 199, 0.95) 100%);
            border: 2px solid #f59e0b;
            box-shadow: 0 14px 35px -5px rgba(245, 158, 11, 0.35), 0 0 0 1px rgba(251, 191, 36, 0.4) inset;
        }

        .spin-result-card.win::before {
            content: "";
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            width: 280px;
            height: 120px;
            background: radial-gradient(ellipse, rgba(245, 158, 11, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .result-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 999px;
            font-family: 'Outfit', sans-serif;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }

        .spin-result-card.win .result-eyebrow {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #ffffff;
            box-shadow: 0 3px 10px rgba(245, 158, 11, 0.35);
        }

        .result-title {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 6px;
        }

        .result-prize {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            font-size: 36px;
            font-weight: 900;
            line-height: 1.15;
            margin: 6px 0 10px;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 50%, #b45309 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 3px 10px rgba(245, 158, 11, 0.35));
        }

        .result-desc {
            font-size: 13px;
            line-height: 1.55;
            color: #78350f;
            font-weight: 600;
            max-width: 410px;
            margin: 0 auto 12px;
        }

        .result-actions {
            margin-top: 14px;
        }

        .result-link {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            padding: 10px 22px !important;
            border-radius: 12px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 13.5px !important;
            font-weight: 800 !important;
            text-decoration: none !important;
            color: #ffffff !important;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35) !important;
            transition: all 0.22s ease !important;
        }

        .result-link:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 7px 20px rgba(217, 119, 6, 0.5) !important;
            color: #ffffff !important;
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
            box-sizing: border-box !important;
        }

        .checkin-button {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3) !important;
        }
        .spin-button {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.35) !important;
        }
        .spin-bulk-button {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35) !important;
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
        }
        .withdraw-button {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 10px 20px !important;
            font-weight: 800 !important;
            cursor: pointer !important;
        }

        /* WHEEL */
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

        .wheel {
            position: relative;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            border-radius: 50%;
            overflow: hidden;
            background:
                radial-gradient(circle at 50% 36%, rgba(255,255,255,0.38), transparent 26%),
                conic-gradient(from 0deg, <?php echo htmlspecialchars($wheel_gradient); ?>);
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
        .wheel.has-result {
            transform: rotate(var(--settled-rotation, 0deg));
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
            text-shadow: 0 2px 4px rgba(124, 45, 18, 0.6);
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
        }
        .reward-pill i {
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.85);
            display: inline-block;
            height: 10px;
            width: 10px;
        }
    </style>
</head>
<body>
    <div class="lucky-wrap">
        <div class="lucky-panel">
            <h2><i class="bi bi-stars"></i> Vòng Quay May Mắn</h2>

            <div class="lucky-status">
                <span><i class="bi bi-ticket-perforated-fill" style="color: #f97316;"></i> Lượt quay: <strong>12</strong></span>
                <span><i class="bi bi-box2-heart-fill" style="color: #eab308;"></i> Thỏi vàng chờ rút: <strong>20 TV</strong></span>
            </div>

            <div class="lucky-note">
                <i class="bi bi-shield-check"></i> <strong>Lưu ý:</strong> Vui lòng thoát game trước khi bấm rút thỏi vàng vào hành trang.
            </div>

            <div id="spinResultSlot">
                <div class="spin-result-card win" id="spinResult">
                    <div class="result-eyebrow"><i class="bi bi-trophy-fill"></i> Kết quả quay thưởng</div>
                    <div class="result-title">🎉 Chúc mừng bạn đã trúng thưởng!</div>
                    <div class="result-prize">20 TV</div>
                    <div class="result-desc">
                        <strong>20 TV</strong> đã được cộng vào kho chờ rút. Hãy thoát game trước khi bấm rút vào túi đồ.
                    </div>
                    <div class="result-actions">
                        <a class="result-link" href="#withdrawGold"><i class="bi bi-wallet2"></i> Rút thỏi vàng ngay</a>
                    </div>
                </div>
            </div>

            <div class="action-row">
                <button class="checkin-button" type="button"><i class="bi bi-calendar-check-fill"></i> Điểm danh</button>
                <button class="spin-button" type="button"><i class="bi bi-play-circle-fill"></i> Quay ngay</button>
                <button class="spin-bulk-button" type="button"><i class="bi bi-lightning-charge-fill"></i> Quay 100 lần</button>
            </div>

            <div class="withdraw-box" id="withdrawGold">
                <strong><i class="bi bi-box2-fill"></i> Rút thỏi vàng vào túi đồ</strong>
                <form class="withdraw-form" onsubmit="return false;">
                    <input name="withdraw_amount" type="number" min="1" max="20" value="20" required>
                    <button class="withdraw-button" type="submit">Rút</button>
                </form>
            </div>

            <div class="wheel-area" id="wheelArea">
                <div class="wheel-pointer"></div>
                <div id="luckyWheel" class="<?php echo htmlspecialchars($wheel_class); ?>" style="--settled-rotation: <?php echo htmlspecialchars($wheel_settled_rotation_text); ?>deg;">
                    <?php foreach ($wheel_segments as $segment): ?>
                        <?php
                            $deg = (float)$segment['center'];
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
                <button id="wheelCenterSpin" class="wheel-center" type="button">Quay</button>
            </div>

            <div class="reward-pills">
                <?php foreach ($wheel_segments as $segment): ?>
                    <span class="reward-pill"><i style="background: <?php echo htmlspecialchars($segment['color']); ?>"></i><?php echo htmlspecialchars($segment['label']); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
