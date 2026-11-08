<?php

declare(strict_types=1);

const BUNKER_DB_PATH = __DIR__ . '/../bunker.db';

function bunkerDb(): PDO
{
    static $db = null;

    if ($db instanceof PDO) {
        return $db;
    }

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('Для работы API требуется расширение PHP pdo_sqlite');
    }

    $db = new PDO('sqlite:' . BUNKER_DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec(
        "CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            avatar_url TEXT,
            registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_games INTEGER DEFAULT 0,
            wins INTEGER DEFAULT 0,
            role TEXT NOT NULL DEFAULT 'player'
        )"
    );
    $columns = $db->query('PRAGMA table_info(users)')->fetchAll();
    $hasRole = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'role') {
            $hasRole = true;
            break;
        }
    }
    if (!$hasRole) {
        $db->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'player'");
    }
    $db->exec(
        'CREATE TABLE IF NOT EXISTS bunker_rooms (
            room_code TEXT PRIMARY KEY,
            state_json TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )'
    );
    $db->exec(
        'CREATE INDEX IF NOT EXISTS idx_bunker_rooms_updated_at
         ON bunker_rooms(updated_at)'
    );

    return $db;
}
