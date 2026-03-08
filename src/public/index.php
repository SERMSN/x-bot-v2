<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define("LARAVEL_START", microtime(true));

function readEnvValue(string $envPath, string $key): ?string
{
    if (!is_file($envPath) || !is_readable($envPath)) {
        return null;
    }

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return null;
    }

    $prefix = $key . "=";
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }
        if (!str_starts_with($line, $prefix)) {
            continue;
        }

        $value = trim(substr($line, strlen($prefix)));
        return trim($value, " \t\n\r\0\x0B\"'");
    }

    return null;
}

// HOSTING=1 for hosting layout (public_html + src), HOSTING=0 for local.
$hosting = 0;
$hostingFromEnv = readEnvValue(__DIR__ . "/../src/.env", "HOSTING");
if ($hostingFromEnv === null) {
    $hostingFromEnv = readEnvValue(dirname(__DIR__) . "/.env", "HOSTING");
}
if ($hostingFromEnv !== null) {
    $hosting = (int) $hostingFromEnv;
}

$basePath = $hosting === 1 ? __DIR__ . "/../src" : dirname(__DIR__);

if (
    file_exists($maintenance = $basePath . "/storage/framework/maintenance.php")
) {
    require $maintenance;
}

require $basePath . "/vendor/autoload.php";

/** @var Application $app */
$app = require_once $basePath . "/bootstrap/app.php";

$app->handleRequest(Request::capture());
