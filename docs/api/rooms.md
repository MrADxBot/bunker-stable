# API `api/rooms.php`

Описание:

API для организации онлайн-комнат. Состояние комнат хранится в SQLite-базе `bunker.db` в таблице `bunker_rooms`.

Поддерживаемые действия (`action` в POST JSON):
- `create` — создать комнату (возвращает `clientId`, `roomCode`, `room`)
- `list` — вернуть краткие сводки комнат
- `join` — присоединиться к комнате (возвращает `clientId`, `username`, `room`)
- `poll` — опрос состояния комнаты (обновляет lastSeen)
- `setReady` — установить готовность участника
- `startGame` — хост запускает игровую сессию
- `gameAction` — набор действий игры (`resetGame`, `kickPlayer`, `sendMessage`, `proceedGame`, `revealAttribute`, `nextPlayer`, `castVote` и т.д.)
- `leave` — выйти из комнаты

Ключевые моменты реализации:
- Транзакционное хранение состояния комнат в SQLite.
- TTL для участника — `ROOM_TTL_SECONDS` (по умолчанию короткий интервал), устаревшие участники удаляются при опросах.
- Валидация кодов комнаты и паролей для приватных комнат.
- Исходник: [api/rooms.php](api/rooms.php#L1-L1200)
