<?php

function game_server_configs(): array
{
    $servers = [
        '1' => [
            'name' => 'Server 1',
            // Website and MariaDB currently run on the same machine.
            // Keep these overridable so deployment does not confuse the game port
            // (14445) with the MySQL port (3306).
            'host' => getenv('NRO_DB_S1_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('NRO_DB_S1_PORT') ?: 3306),
            'game_port' => 14445,
            'database' => 'team2026',
            'username' => 'liodev',
            'password' => 'liopass',
            'admin_api_url' => getenv('NRO_ADMIN_API_S1_URL') ?: 'http://127.0.0.1:18081',
            'admin_api_token' => getenv('NRO_ADMIN_API_S1_TOKEN') ?: '',
        ],
    ];

    $servers['2'] = [
        'name' => 'Server 2',
        'host' => getenv('NRO_DB_S2_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('NRO_DB_S2_PORT') ?: 3306),
        'game_port' => 14446,
        'database' => 'awnv3',
        // The two local servers currently share one MariaDB account. Keep
        // dedicated SV2 environment variables so they can be split safely.
        'username' => getenv('NRO_DB_S2_USER') ?: $servers['1']['username'],
        'password' => getenv('NRO_DB_S2_PASSWORD') ?: $servers['1']['password'],
        'admin_api_url' => getenv('NRO_ADMIN_API_S2_URL') ?: 'http://127.0.0.1:18082',
        'admin_api_token' => getenv('NRO_ADMIN_API_S2_TOKEN') ?: '',
    ];

    return $servers;
}

function game_server_config(string $serverId): ?array
{
    $servers = game_server_configs();

    return $servers[$serverId] ?? null;
}

/**
 * Servers exposed to the Java Admin API dashboard.
 *
 * Kept as a separate function so runtime-only servers can be added later.
 */
function admin_runtime_server_configs(): array
{
    $gameServers = game_server_configs();

    return [
        '1' => $gameServers['1'],
        '2' => $gameServers['2'],
    ];
}

function admin_runtime_server_config(string $serverId): ?array
{
    $servers = admin_runtime_server_configs();

    return $servers[$serverId] ?? null;
}

function current_game_server_id(): string
{
    $serverId = (string)($_SESSION['server_id'] ?? '1');

    return game_server_config($serverId) !== null ? $serverId : '1';
}

function is_server_one(): bool
{
    return current_game_server_id() === '1';
}

function is_server_two(): bool
{
    return current_game_server_id() === '2';
}

function require_server_one_feature(bool $jsonResponse = false): void
{
    if (is_server_one()) {
        return;
    }

    http_response_code(403);

    if ($jsonResponse) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'ok' => false,
            'message' => 'Chức năng này hiện chỉ hỗ trợ Server 1.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $_SESSION['server_one_notice'] = 'Chức năng này hiện chỉ hỗ trợ Server 1.';
    header('Location: /forum.php');
    exit();
}

function server_two_recharge_disabled_message(): string
{
    return 'Server 2 hiện không hỗ trợ nạp tiền hoặc nạp thẻ.';
}

/**
 * Recharge belongs exclusively to Server 1. Block before any database
 * connection so a Server 2 session can never create a recharge request.
 */
function require_server_one_recharge(bool $jsonResponse = false): void
{
    if (is_server_one()) {
        return;
    }

    http_response_code(403);
    $message = server_two_recharge_disabled_message();

    if ($jsonResponse) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'ok' => false,
            'status' => 'error',
            'message' => $message,
            'server_id' => '2',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $_SESSION['server_two_notice'] = $message;
    header('Location: /app/server-2.php?notice=recharge-disabled');
    exit();
}
