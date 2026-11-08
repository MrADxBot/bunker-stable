<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

const MAX_USERNAME_LENGTH = 30;

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function utf8Substr(string $value, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length);
    }

    if (!preg_match_all('/./us', $value, $matches)) {
        return substr($value, $start, $length);
    }

    return implode('', array_slice($matches[0], $start, $length));
}

function sanitizeUsername(string $username): string
{
    $username = trim($username);
    $username = utf8Substr($username, 0, MAX_USERNAME_LENGTH);
    return preg_replace('/\s+/', ' ', $username) ?? '';
}

function normalizeRole(string $role): string
{
    $allowed = ['player', 'host', 'admin'];
    return in_array($role, $allowed, true) ? $role : 'player';
}

function hashPassword(string $password): string
{
    // Хэш совпадает с клиентским вариантом из auth.js: base64 UTF-8 строки.
    return base64_encode($password);
}

function publicUser(array $user): array
{
    return [
        'username' => (string) ($user['username'] ?? ''),
        'role' => (string) ($user['role'] ?? 'player'),
        'createdAt' => (string) ($user['createdAt'] ?? $user['registered_at'] ?? ''),
    ];
}

function findUser(string $username): ?array
{
    $statement = bunkerDb()->prepare(
        "SELECT user_id, username, password_hash, registered_at, COALESCE(role, 'player') AS role
         FROM users WHERE lower(username) = lower(:username) LIMIT 1"
    );
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();
    return $user === false ? null : $user;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Только POST запросы поддерживаются'], 405);
}

$rawBody = file_get_contents('php://input');
$body = json_decode((string) $rawBody, true);
if (!is_array($body)) {
    $body = $_POST;
}

$action = (string) ($body['action'] ?? '');

try {
    $db = bunkerDb();
} catch (Throwable $error) {
    respond(['ok' => false, 'error' => $error->getMessage()], 500);
}

if ($action === 'list') {
    $users = $db->query(
        "SELECT username, registered_at, COALESCE(role, 'player') AS role
         FROM users ORDER BY registered_at, user_id"
    )->fetchAll();
    respond([
        'ok' => true,
        'users' => array_map('publicUser', $users),
    ]);
}

if ($action === 'register') {
    $username = sanitizeUsername((string) ($body['username'] ?? ''));
    $passwordHash = (string) ($body['passwordHash'] ?? '');
    $role = normalizeRole((string) ($body['role'] ?? 'player'));

    if ($username === '') {
        respond(['ok' => false, 'error' => 'Имя пользователя не задано'], 422);
    }
    if ($passwordHash === '') {
        respond(['ok' => false, 'error' => 'Пароль не задан'], 422);
    }

    if (findUser($username) !== null) {
        respond(['ok' => false, 'error' => 'Такой логин уже существует'], 409);
    }

    $statement = $db->prepare(
        'INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)'
    );
    $statement->execute([
        'username' => $username,
        'password_hash' => $passwordHash,
        'role' => $role,
    ]);
    $user = findUser($username);

    respond([
        'ok' => true,
        'user' => publicUser($user ?? []),
    ]);
}

if ($action === 'login') {
    $username = sanitizeUsername((string) ($body['username'] ?? ''));
    $passwordHash = (string) ($body['passwordHash'] ?? '');

    if ($username === '') {
        respond(['ok' => false, 'error' => 'Имя пользователя не задано'], 422);
    }

    $user = findUser($username);
    if ($user === null) {
        respond(['ok' => false, 'error' => 'Пользователь не найден'], 404);
    }

    if (($user['password_hash'] ?? '') !== $passwordHash) {
        respond(['ok' => false, 'error' => 'Неверный пароль'], 401);
    }

    respond([
        'ok' => true,
        'user' => publicUser($user),
    ]);
}

if ($action === 'updateRole') {
    $username = sanitizeUsername((string) ($body['username'] ?? ''));
    $newRole = normalizeRole((string) ($body['newRole'] ?? 'player'));

    $user = findUser($username);
    if ($user === null) {
        respond(['ok' => false, 'error' => 'Пользователь не найден'], 404);
    }

    $statement = $db->prepare('UPDATE users SET role = :role WHERE user_id = :user_id');
    $statement->execute(['role' => $newRole, 'user_id' => $user['user_id']]);
    $user = findUser($username);

    respond([
        'ok' => true,
        'user' => publicUser($user ?? []),
    ]);
}

if ($action === 'delete') {
    $username = sanitizeUsername((string) ($body['username'] ?? ''));
    $user = findUser($username);
    if ($user === null) {
        respond(['ok' => false, 'error' => 'Пользователь не найден'], 404);
    }

    $statement = $db->prepare('DELETE FROM users WHERE user_id = :user_id');
    $statement->execute(['user_id' => $user['user_id']]);

    respond(['ok' => true]);
}

respond(['ok' => false, 'error' => 'Неизвестное действие'], 400);