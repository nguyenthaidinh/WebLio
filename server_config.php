<?php

function game_server_configs(): array
{
    return [
        '1' => [
            'name' => 'Server 1',
            'host' => '103.67.197.241',
            'port' => (int)(getenv('NRO_DB_S1_PORT') ?: 14445),
            'game_port' => 14445,
            'database' => 'team2026',
            'username' => 'liodev',
            'password' => 'liopass',
            'admin_api_url' => getenv('NRO_ADMIN_API_S1_URL') ?: 'http://127.0.0.1:18081',
            'admin_api_token' => getenv('NRO_ADMIN_API_S1_TOKEN') ?: '',
        ],
        '2' => [
            'name' => 'Server 2',
            'host' => '103.67.197.241',
            'port' => (int)(getenv('NRO_DB_S2_PORT') ?: 14446),
            'game_port' => 14446,
            'database' => 'awnv3',
            'username' => 'liodev',
            'password' => 'liopass',
            'admin_api_url' => getenv('NRO_ADMIN_API_S2_URL') ?: 'http://127.0.0.1:18082',
            'admin_api_token' => getenv('NRO_ADMIN_API_S2_TOKEN') ?: '',
        ],
    ];
}

function game_server_config(string $serverId): ?array
{
    $servers = game_server_configs();

    return $servers[$serverId] ?? null;
}

function current_game_server_id(): string
{
    return (string)($_SESSION['server_id'] ?? '1');
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
