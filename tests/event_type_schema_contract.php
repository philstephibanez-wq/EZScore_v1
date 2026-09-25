<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migration = file_get_contents($root.'/migrations/Version20260925223000.php');

if (!is_string($migration)) {
    fwrite(STDERR, "FAIL: migration missing\n");
    exit(1);
}

$checks = [
    "type VARCHAR(32) NOT NULL" => 'type has no database default',
    "PRAGMA foreign_keys = OFF" => 'foreign keys disabled during SQLite table rebuild',
    "CREATE INDEX IDX_EVENT_PLAYLIST" => 'playlist index recreated',
    "CREATE INDEX IDX_EVENT_GROUP" => 'group index recreated',
    "CREATE INDEX IDX_EVENT_CREATED_BY" => 'creator index recreated',
    "CREATE INDEX IDX_EVENT_STARTS_AT" => 'start date index recreated',
];

foreach ($checks as $needle => $message) {
    if (!str_contains($migration, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

if (str_contains($migration, "type VARCHAR(32) NOT NULL DEFAULT")) {
    fwrite(STDERR, "FAIL: type still has a database default\n");
    exit(1);
}

fwrite(STDOUT, "\nR20.1 mapping alignment contract passed.\n");
