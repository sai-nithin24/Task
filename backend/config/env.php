<?php
declare(strict_types=1);

/**
 * Minimal .env loader — reads KEY=VALUE pairs from a .env file
 * into $_ENV and putenv(), skipping comments and blank lines.
 *
 * @param string $path  Absolute path to the .env file.
 */
function loadEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return; // silently skip; production may use real env vars
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes
        if (
            strlen($value) >= 2 &&
            (($value[0] === '"'  && $value[-1] === '"') ||
             ($value[0] === "'"  && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}
