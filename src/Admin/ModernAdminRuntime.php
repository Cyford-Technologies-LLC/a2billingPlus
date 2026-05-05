<?php

declare(strict_types=1);

namespace A2BillingPlus\Admin;

use A2BillingPlus\Module\Ui\Theme;
use A2BillingPlus\Module\Ui\ThemeRegistry;

final class ModernAdminRuntime
{
    /** @var null|array<string, string> */
    private ?array $envFileValues = null;

    private ThemeRegistry $themeRegistry;

    public function __construct(
        private readonly string $projectRoot,
        ?ThemeRegistry $themeRegistry = null
    ) {
        $this->themeRegistry = $themeRegistry ?? ThemeRegistry::default();
    }

    public function themeRegistry(): ThemeRegistry
    {
        return $this->themeRegistry;
    }

    public function activeTheme(): Theme
    {
        return $this->themeRegistry->resolve($this->envString('A2BP_UI_THEME'));
    }

    public function envPath(): string
    {
        return rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    }

    public function envString(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $file = getenv($key . '_FILE');
        if (is_string($file) && $file !== '' && is_readable($file)) {
            $contents = file_get_contents($file);
            if (is_string($contents)) {
                return trim($contents);
            }
        }

        $values = $this->envFileValues();
        return ($values[$key] ?? '') !== '' ? $values[$key] : $default;
    }

    public function saveUiTheme(string $requestedTheme): Theme
    {
        $theme = $this->themeRegistry->resolve($requestedTheme);
        $this->saveEnvValues(['A2BP_UI_THEME' => $theme->id()]);
        putenv('A2BP_UI_THEME=' . $theme->id());
        $this->envFileValues = null;

        return $theme;
    }

    public function pdo(): \PDO
    {
        $dsn = $this->envString('A2BP_DB_DSN');
        if ($dsn === '') {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $this->envString('A2BP_DB_HOST', 'db'),
                $this->envString('A2BP_DB_NAME', 'mya2billing')
            );
        }

        return new \PDO($dsn, $this->envString('A2BP_DB_USER', 'a2billinguser'), $this->envString('A2BP_DB_PASSWORD', 'a2billing'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * @param array<string, string> $values
     */
    private function saveEnvValues(array $values): void
    {
        $envPath = $this->envPath();
        if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
            throw new \RuntimeException('.env is not writable.');
        }

        $contents = is_file($envPath) ? (string)file_get_contents($envPath) : '';
        $contents = $this->mergeEnvValues($contents, $values);

        if (@file_put_contents($envPath, $contents) === false) {
            throw new \RuntimeException('Could not write .env.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function envFileValues(): array
    {
        if (is_array($this->envFileValues)) {
            return $this->envFileValues;
        }

        $this->envFileValues = [];
        $envPath = $this->envPath();
        if (!is_readable($envPath)) {
            return $this->envFileValues;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return $this->envFileValues;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $this->envFileValues[$key] = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
        }

        return $this->envFileValues;
    }

    /**
     * @param array<string, string> $values
     */
    private function mergeEnvValues(string $contents, array $values): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents);
        $lines = is_array($lines) ? $lines : [];
        $seen = [];

        foreach ($lines as $index => $line) {
            if (!preg_match('/^([A-Z0-9_]+)=/', $line, $matches)) {
                continue;
            }

            $key = $matches[1];
            if (array_key_exists($key, $values)) {
                $lines[$index] = $key . '=' . $this->envValue($values[$key]);
                $seen[$key] = true;
            }
        }

        foreach ($values as $key => $value) {
            if (!isset($seen[$key])) {
                $lines[] = $key . '=' . $this->envValue($value);
            }
        }

        return rtrim(implode(PHP_EOL, $lines)) . PHP_EOL;
    }

    private function envValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#="\']/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }
}
