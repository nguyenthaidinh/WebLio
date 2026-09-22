<?php

require_once __DIR__ . '/../server_config.php';

function admin_api_request(string $serverId, string $method, string $path, ?array $payload = null): array
{
    $config = game_server_config($serverId);
    if (!$config) {
        return admin_api_failure(0, 'Máy chủ không tồn tại.');
    }

    $baseUrl = rtrim((string)($config['admin_api_url'] ?? ''), '/');
    $token = (string)($config['admin_api_token'] ?? '');
    if ($baseUrl === '' || $token === '') {
        return admin_api_failure(0, 'Máy chủ chưa được cấu hình Admin API.');
    }

    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }

    $url = $baseUrl . $path;
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
        'X-Admin-User: ' . admin_api_actor(),
    ];
    $body = null;
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return admin_api_failure(0, 'Không thể tạo dữ liệu JSON gửi tới máy chủ.');
        }
        $headers[] = 'Content-Type: application/json; charset=UTF-8';
    }

    if (function_exists('curl_init')) {
        return admin_api_request_curl($url, $method, $headers, $body);
    }

    return admin_api_request_stream($url, $method, $headers, $body);
}

function admin_api_request_curl(string $url, string $method, array $headers, ?string $body): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($responseBody === false) {
        return admin_api_failure($status, 'Không kết nối được Admin API: ' . $error);
    }

    return admin_api_decode_response($status, $responseBody);
}

function admin_api_request_stream(string $url, string $method, array $headers, ?string $body): array
{
    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => 30,
            'ignore_errors' => true,
            'follow_location' => 0,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    if (!empty($http_response_header[0])
        && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $status = (int)$matches[1];
    }

    if ($responseBody === false) {
        return admin_api_failure($status, 'Không kết nối được Admin API của máy chủ.');
    }

    return admin_api_decode_response($status, $responseBody);
}

function admin_api_decode_response(int $status, string $body): array
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return admin_api_failure($status, 'Admin API trả về dữ liệu không hợp lệ.');
    }

    if (!empty($decoded['ok'])) {
        return [
            'ok' => true,
            'status' => $status,
            'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
            'error' => null,
        ];
    }

    return admin_api_failure($status, (string)($decoded['error'] ?? 'Lệnh quản trị không thành công.'));
}

function admin_api_failure(int $status, string $message): array
{
    return [
        'ok' => false,
        'status' => $status,
        'data' => [],
        'error' => $message,
    ];
}

function admin_api_actor(): string
{
    $actor = (string)($_SESSION['username'] ?? $_SESSION['account'] ?? 'admin');
    $actor = preg_replace('/[^a-zA-Z0-9_.@-]/', '', $actor);
    return substr($actor ?: 'admin', 0, 64);
}
