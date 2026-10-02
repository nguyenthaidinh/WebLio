<?php

/**
 * Open a PDO connection for one configured game server.
 */
function game_server_pdo(array $serverConfig): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $serverConfig['host'],
        $serverConfig['port'],
        $serverConfig['database']
    );

    return new PDO($dsn, $serverConfig['username'], $serverConfig['password'], [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function registration_database_name(string $serverId): string
{
    if ($serverId === '1') {
        return 'team2026';
    }
    if ($serverId === '2') {
        return 'awnv3';
    }

    throw new InvalidArgumentException('Server đăng ký không hợp lệ.');
}

/**
 * Fail closed before an INSERT if a connection points at the wrong database.
 * This is the last safety boundary protecting SV1 and SV2 from cross-writes.
 */
function assert_registration_database(PDO $pdo, string $serverId): void
{
    $expectedDatabase = registration_database_name($serverId);
    $actualDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

    if (!hash_equals($expectedDatabase, $actualDatabase)) {
        throw new RuntimeException(sprintf(
            'Kết nối đăng ký %s không đúng database. Đã khóa thao tác.',
            $serverId === '1' ? 'Server 1' : 'Server 2'
        ));
    }
}

/**
 * Create an account using the schema that belongs to the selected server.
 *
 * Server 2 deliberately follows AWN htdocs/Api/Auth.php. Its account table is
 * not compatible with Server 1, so the two INSERT statements must stay split.
 */
function register_game_account(
    PDO $pdo,
    string $serverId,
    string $username,
    string $password,
    string $ipAddress
): void {
    assert_registration_database($pdo, $serverId);

    if ($serverId === '2') {
        $stmt = $pdo->prepare(
            'INSERT INTO account (
                username, password, ref_id, ip_address,
                vetuan, vethang, vethang_expire, vetuan_expire
            ) VALUES (
                :username, :password, 0, :ip_address,
                0, 0, 0, 0
            )'
        );
        $stmt->execute([
            ':username' => $username,
            ':password' => $password,
            ':ip_address' => $ipAddress,
        ]);
        return;
    }

    if ($serverId !== '1') {
        throw new InvalidArgumentException('Server đăng ký không hợp lệ.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO account (
            username, password, email, create_time, update_time, ban, is_admin,
            last_time_login, last_time_logout, ip_address, active, thoi_vang,
            server_login, bd_player, is_gift_box, gift_time, reward, vnd,
            tongnap, token, xsrf_token, newpass, luotquay, vang, event_point,
            vip, tichdiem, point_post, last_post, gioithieu, xacnhan_gioitheu,
            baiviet, xacminh, admin
        ) VALUES (
            :username, :password, '', NOW(), NOW(), 0, 0,
            '2002-07-31 00:00:00', '2002-07-31 00:00:00', :ip_address, 1, 0,
            1, 1, 0, '0', NULL, 0,
            0, '', '', '', 0, 0, 0,
            0, 0, 0, 0, NULL, 0,
            0, 0, 0
        )"
    );
    $stmt->execute([
        ':username' => $username,
        ':password' => $password,
        ':ip_address' => $ipAddress,
    ]);
}

