<?php

declare(strict_types=1);

/**
 * Application configuration. Contains NO secrets, so it is safe to commit.
 *
 * Secrets (API keys, DB password) come from the real environment or from a
 * git-ignored `.env` file in this folder. Copy `.env.example` to `.env`.
 */
(static function (): void {
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }
    $contents = (string) file_get_contents($envFile);
    if (strncmp($contents, "\xEF\xBB\xBF", 3) === 0) {
        $contents = substr($contents, 3); // Notepad's UTF-8 BOM
    }
    foreach (preg_split('/\R/', $contents) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, " \t\"'");
        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
})();

function envValue(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : (string) $value;
}

define('GROQ_API_KEY', envValue('GROQ_API_KEY'));
define('GROQ_API_URL', envValue('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions'));
define('GROQ_MODEL', envValue('GROQ_MODEL', 'openai/gpt-oss-20b'));
