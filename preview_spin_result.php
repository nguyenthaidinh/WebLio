<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/lucky_rewards.php';

// Mock logged in user
$is_logged_in = true;
$account_id = 1;
$remaining_spins = 5;
$pending_gold = 20;
$checked_in_today = false;
$checkin_table_ready = true;
$csrf_token = 'test_token';
$message = '';
$message_type = 'info';

// Load rewards
$lucky_rewards = lucky_rewards_load($conn);
if (empty($lucky_rewards)) {
    $lucky_rewards = LUCKY_REWARD_DEFAULTS;
}

// Build equal segments
$wheel_segments = lucky_build_wheel_segments($lucky_rewards);

function lucky_build_wheel_segments_preview($rewards) {
    $count = count($rewards);
    if ($count <= 0) return [];
    $deg_per_slice = 360.0 / $count;
    $segments = [];
    $index = 0;
    foreach ($rewards as $reward) {
        $start = $index * $deg_per_slice;
        $end = ($index + 1) * $deg_per_slice;
        $segments[] = [
            'reward_key' => (string)$reward['reward_key'],
            'label' => (string)$reward['label'],
            'amount' => (int)$reward['amount'],
            'color' => (string)$reward['color'],
            'weight' => (int)$reward['weight'],
            'start' => $start,
            'end' => $end,
            'degrees' => $deg_per_slice,
            'center' => $start + ($deg_per_slice / 2),
        ];
        $index++;
    }
    return $segments;
}

// Mock spin result matching user's screenshot: 20 TV
$spin_result = [
    'type' => 'win',
    'reward_key' => 'gold_20',
    'label' => '20 TV',
    'amount' => 20,
    'total_amount' => 20,
];

// Let's include the actual app/vong-quay.php via session mock or render
$_SESSION['id'] = 1;
$_SESSION['lucky_spin_result'] = $spin_result;
header("Location: /app/vong-quay.php");
exit();
