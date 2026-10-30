<?php

declare(strict_types=1);

use App\Core\Env;

$env = Env::string('APP_ENV', 'production');
$root = dirname(__DIR__);

// Some platforms inject a single DATABASE_URL instead of DB_* variables; DB_* wins when both exist.
$databaseUrl = Env::string('DATABASE_URL');
$url = $databaseUrl !== '' ? (parse_url($databaseUrl) ?: []) : [];
$dbFromUrl = [
    'host' => $url['host'] ?? null,
    'port' => $url['port'] ?? null,
    'database' => isset($url['path']) ? ltrim($url['path'], '/') : null,
    'username' => isset($url['user']) ? rawurldecode($url['user']) : null,
    'password' => isset($url['pass']) ? rawurldecode($url['pass']) : null,
];

return [
    'app' => [
        'env' => $env,
        // Debug output is never allowed in production, whatever APP_DEBUG says.
        'debug' => $env !== 'production' && Env::bool('APP_DEBUG', false),
        'url' => rtrim(Env::string('APP_URL', 'http://localhost:8000'), '/'),
        'key' => Env::string('APP_KEY'),
        'timezone' => Env::string('APP_TIMEZONE', 'Africa/Kampala'),
        'root' => $root,
    ],
    'db' => [
        'host' => Env::string('DB_HOST', $dbFromUrl['host'] ?? '127.0.0.1'),
        'port' => Env::int('DB_PORT', (int) ($dbFromUrl['port'] ?? 3306)),
        'database' => $env === 'testing'
            ? Env::string('DB_TEST_DATABASE', 'kaziug_test')
            : Env::string('DB_DATABASE', $dbFromUrl['database'] ?? 'kaziug'),
        'username' => Env::string('DB_USERNAME', $dbFromUrl['username'] ?? 'kaziug'),
        'password' => Env::string('DB_PASSWORD', $dbFromUrl['password'] ?? ''),
        // CA certificate path for TLS to a remote database server; empty for a local (localhost) server.
        'ssl_ca' => Env::string('DB_SSL_CA'),
    ],
    'session' => [
        'cookie' => Env::string('SESSION_COOKIE', 'kaziug_session'),
        'idle_minutes' => Env::int('SESSION_IDLE_MINUTES', 30),
        'lifetime_minutes' => Env::int('SESSION_LIFETIME_MINUTES', 120),
        'secure' => $env === 'production',
    ],
    'http' => [
        'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', Env::string('TRUSTED_PROXIES'))))),
    ],
    'log' => [
        'path' => $root . '/storage/logs',
        'level' => Env::string('LOG_LEVEL', 'info'),
    ],
    'mail' => [
        'timeout' => Env::int('MAIL_TIMEOUT', 15),
    ],
    'newsletter' => [
        'rate_per_minute' => max(1, Env::int('NEWSLETTER_RATE_PER_MINUTE', 60)),
        'max_attempts' => 3,
        // Minutes to wait before retry 1, 2 (and so on). The last value repeats.
        'backoff_minutes' => [1, 5, 15],
    ],
    'paths' => [
        'views' => $root . '/views',
        'migrations' => $root . '/database/migrations',
        'seeds' => $root . '/database/seeds',
        'storage' => $root . '/storage',
    ],
];
