<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$db = bunkerDb();
$importedUsers = 0;
$importedRooms = 0;

$usersPath = __DIR__ . '/data/users.json';
if (is_file($usersPath)) {
    $users = json_decode((string) file_get_contents($usersPath), true);
    if (is_array($users)) {
        $statement = $db->prepare(
            'INSERT OR IGNORE INTO users (username, password_hash, role, registered_at)
             VALUES (:username, :password_hash, :role, :registered_at)'
        );
        foreach ($users as $user) {
            $username = trim((string) ($user['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $statement->execute([
                'username' => $username,
                'password_hash' => (string) ($user['passwordHash'] ?? ''),
                'role' => (string) ($user['role'] ?? 'player'),
                'registered_at' => (string) ($user['createdAt'] ?? date(DATE_ATOM)),
            ]);
            $importedUsers += $statement->rowCount();
        }
    }
}

$roomsPath = __DIR__ . '/data/rooms';
$roomStatement = $db->prepare(
    'INSERT OR IGNORE INTO bunker_rooms (room_code, state_json, created_at, updated_at)
     VALUES (:room_code, :state_json, :created_at, :updated_at)'
);
foreach (glob($roomsPath . '/*.json') ?: [] as $path) {
    $room = json_decode((string) file_get_contents($path), true);
    if (!is_array($room) || empty($room['code'])) {
        continue;
    }
    $updatedAt = (int) ($room['updatedAt'] ?? time());
    $roomStatement->execute([
        'room_code' => (string) $room['code'],
        'state_json' => json_encode($room, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => (int) ($room['createdAt'] ?? $updatedAt),
        'updated_at' => $updatedAt,
    ]);
    $importedRooms += $roomStatement->rowCount();
}

echo "Imported users: {$importedUsers}\n";
echo "Imported rooms: {$importedRooms}\n";
