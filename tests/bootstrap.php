<?php

require dirname(__DIR__).'/vendor/autoload.php';

$appEnv = phpunitEnv('APP_ENV') ?? $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: null;
$databaseUrl = phpunitEnv('DATABASE_URL') ?? $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: null;

if (is_string($appEnv)) {
    $_SERVER['APP_ENV'] = $appEnv;
    $_ENV['APP_ENV'] = $appEnv;
    putenv(sprintf('APP_ENV=%s', $appEnv));
}

if (is_string($databaseUrl)) {
    $_SERVER['DATABASE_URL'] = $databaseUrl;
    $_ENV['DATABASE_URL'] = $databaseUrl;
    putenv(sprintf('DATABASE_URL=%s', $databaseUrl));
}

function phpunitEnv(string $name): ?string
{
    $configurationPath = dirname(__DIR__).'/phpunit.xml.dist';
    if (!is_file($configurationPath)) {
        return null;
    }

    $configuration = file_get_contents($configurationPath);
    if (!is_string($configuration)) {
        return null;
    }

    $attribute = preg_quote($name, '/');
    if (1 !== preg_match('/<env\b(?=[^>]*\bname="'.$attribute.'")(?=[^>]*\bvalue="([^"]*)")[^>]*>/', $configuration, $matches)) {
        return null;
    }

    return html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1);
}

if ('test' === $appEnv && is_string($databaseUrl) && str_starts_with($databaseUrl, 'postgresql://')) {
    $database = parse_url($databaseUrl, PHP_URL_PATH);
    $databaseName = is_string($database) ? ltrim($database, '/') : '';

    if (str_ends_with($databaseName, '_test')) {
        $host = parse_url($databaseUrl, PHP_URL_HOST) ?: 'localhost';
        $port = parse_url($databaseUrl, PHP_URL_PORT) ?: 5432;
        $user = rawurldecode(parse_url($databaseUrl, PHP_URL_USER) ?: '');
        $password = rawurldecode(parse_url($databaseUrl, PHP_URL_PASS) ?: '');
        $quotedDatabaseName = '"'.str_replace('"', '""', $databaseName).'"';

        try {
            $pdo = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=postgres', $host, $port), $user, $password);
            $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :database');
            $statement->execute(['database' => $databaseName]);

            if (false === $statement->fetchColumn()) {
                $pdo->exec(sprintf('CREATE DATABASE %s', $quotedDatabaseName));
            }
        } catch (PDOException $exception) {
            throw new RuntimeException(sprintf('Unable to create the missing test database "%s". Check PostgreSQL availability and CREATEDB privileges for user "%s".', $databaseName, $user), 0, $exception);
        }
    }
}
