<?php

function forum_post_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $matched = preg_match_all('/./us', $value, $characters);
    return $matched === false ? strlen($value) : $matched;
}

function forum_post_ini_size_to_bytes($value): int
{
    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $size = (float)$value;
    switch ($unit) {
        case 'g':
            $size *= 1024;
            // Fall through.
        case 'm':
            $size *= 1024;
            // Fall through.
        case 'k':
            $size *= 1024;
    }

    return (int)$size;
}

function forum_post_normalize_legacy_text($value): string
{
    return html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function forum_post_csrf_token(): string
{
    if (empty($_SESSION['forum_post_csrf']) || !is_string($_SESSION['forum_post_csrf'])) {
        $_SESSION['forum_post_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['forum_post_csrf'];
}

function forum_post_verify_csrf($token): bool
{
    return is_string($token)
        && isset($_SESSION['forum_post_csrf'])
        && is_string($_SESSION['forum_post_csrf'])
        && hash_equals($_SESSION['forum_post_csrf'], $token);
}

function forum_post_set_flash(string $type, string $message): void
{
    $_SESSION['forum_post_flash'] = [
        'type' => $type === 'success' ? 'success' : 'error',
        'message' => $message,
    ];
}

function forum_post_can_edit(string $postAuthor, ?string $accountUsername,
                             ?string $playerName, bool $isAdmin): bool
{
    if ($isAdmin) {
        return true;
    }

    return ($accountUsername !== null && hash_equals($postAuthor, $accountUsername))
        || ($playerName !== null && $playerName !== '' && hash_equals($postAuthor, $playerName));
}
