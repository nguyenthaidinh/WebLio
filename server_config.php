<?php

function game_server_configs(): array
{
    return [
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
        // Server 2 (awnv3) is intentionally omitted until its database is ready.
    ];
}

function game_server_config(string $serverId): ?array
{
    $servers = game_server_configs();

    return $servers[$serverId] ?? null;
}

/**
 * Servers exposed to the Java Admin API dashboard.
 *
 * Keep this list separate from game_server_configs(): Server 2 can be managed
 * from the admin dashboard without enabling it for website login/registration.
 */
function admin_runtime_server_configs(): array
{
    $gameServers = game_server_configs();

    return [
        '1' => $gameServers['1'],
        '2' => [
            'name' => 'Server 2',
            'game_port' => 14446,
            'database' => 'awnv3',
            'admin_api_url' => getenv('NRO_ADMIN_API_S2_URL') ?: 'http://127.0.0.1:18082',
            'admin_api_token' => getenv('NRO_ADMIN_API_S2_TOKEN') ?: '',
        ],
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
