<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Ui;

final class ThemeManagerService
{
    public const UI_CONTRACT_VERSION = '1';

    public function __construct(
        private readonly string $projectRoot,
        private readonly ThemeRegistry $themeRegistry
    ) {
    }

    /**
     * @return list<array{id:string,name:string,description:string,stylesheet:string,menu_style:string,active:bool,built_in:bool,version:string,manifest:bool}>
     */
    public function listThemes(string $activeThemeId): array
    {
        $themes = [];
        foreach ($this->themeRegistry->all() as $theme) {
            $manifest = $this->manifestForTheme($theme->id());
            $themes[] = [
                'id' => $theme->id(),
                'name' => $theme->name(),
                'description' => $theme->description(),
                'stylesheet' => $theme->asset('stylesheet'),
                'menu_style' => $theme->defaultMenuStyle(),
                'active' => $theme->id() === $activeThemeId,
                'built_in' => in_array($theme->id(), ThemeRegistry::BUILT_IN_THEME_IDS, true),
                'version' => trim((string)($manifest['version'] ?? '')),
                'manifest' => is_array($manifest),
            ];
        }

        usort($themes, static function (array $left, array $right): int {
            if ($left['built_in'] !== $right['built_in']) {
                return $left['built_in'] ? -1 : 1;
            }

            return strcmp($left['name'], $right['name']);
        });

        return $themes;
    }

    /**
     * @return array{id:string,name:string,version:string,description:string}
     */
    public function installUploadedTheme(string $uploadedFile, string $originalName): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive is not available. Theme package install requires the PHP zip extension.');
        }

        if (!is_file($uploadedFile) || !is_readable($uploadedFile)) {
            throw new \RuntimeException('Uploaded theme package is not readable.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($uploadedFile) !== true) {
            throw new \RuntimeException('Could not open the uploaded theme package: ' . $originalName);
        }

        $entries = [];
        $entryMap = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !isset($stat['name'])) {
                continue;
            }

            $originalEntryName = (string)$stat['name'];
            $entryName = str_replace('\\', '/', $originalEntryName);
            if ($entryName === '' || str_ends_with($entryName, '/')) {
                continue;
            }

            if ($this->isUnsafeArchivePath($entryName)) {
                $zip->close();
                throw new \RuntimeException('Theme package contains an unsafe path: ' . $entryName);
            }

            $entries[] = $entryName;
            $entryMap[$entryName] = $originalEntryName;
        }

        $manifestEntry = $this->resolveManifestEntry($entries);
        if ($manifestEntry === null) {
            $zip->close();
            throw new \RuntimeException('Theme package must include theme.json at the archive root or inside one top-level folder.');
        }

        $manifestJson = $zip->getFromName($entryMap[$manifestEntry] ?? $manifestEntry);
        if (!is_string($manifestJson) || $manifestJson === '') {
            $zip->close();
            throw new \RuntimeException('Theme package manifest could not be read.');
        }

        $manifest = json_decode($manifestJson, true);
        if (!is_array($manifest)) {
            $zip->close();
            throw new \RuntimeException('Theme package manifest is not valid JSON.');
        }

        $manifestDir = dirname($manifestEntry);
        $manifestDir = $manifestDir === '.' ? '' : $manifestDir;
        $metadata = $this->validateManifest($manifest, $manifestDir, $entries);
        $themeId = $metadata['id'];

        if (in_array($themeId, ThemeRegistry::BUILT_IN_THEME_IDS, true)) {
            $zip->close();
            throw new \RuntimeException('Built-in theme IDs cannot be replaced through package upload.');
        }

        $themeRoot = $this->themeRoot();
        $targetDir = $themeRoot . DIRECTORY_SEPARATOR . $themeId;
        if (is_dir($targetDir)) {
            $zip->close();
            throw new \RuntimeException('A theme with ID "' . $themeId . '" is already installed.');
        }

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'a2bp-theme-' . bin2hex(random_bytes(6));
        $installDir = $tempDir . DIRECTORY_SEPARATOR . $themeId;
        $this->ensureDirectory($installDir);

        try {
            foreach ($entries as $entry) {
                $relativeEntry = $manifestDir !== '' && str_starts_with($entry, $manifestDir . '/')
                    ? substr($entry, strlen($manifestDir) + 1)
                    : $entry;

                if ($relativeEntry === '' || $this->isUnsafeArchivePath($relativeEntry)) {
                    continue;
                }

                $destination = $installDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeEntry);
                $this->ensureDirectory(dirname($destination));

                $contents = $zip->getFromName($entryMap[$entry] ?? $entry);
                if (!is_string($contents) || file_put_contents($destination, $contents) === false) {
                    throw new \RuntimeException('Could not extract theme file: ' . $relativeEntry);
                }
            }
        } catch (\Throwable $exception) {
            $zip->close();
            $this->removeDirectory($tempDir);
            throw $exception;
        }

        $zip->close();

        try {
            $this->ensureDirectory($themeRoot);
            if (!@rename($installDir, $targetDir)) {
                $this->copyDirectory($installDir, $targetDir);
            }
        } catch (\Throwable $exception) {
            $this->removeDirectory($targetDir);
            $this->removeDirectory($tempDir);
            throw $exception;
        }

        $this->removeDirectory($tempDir);

        return $metadata;
    }

    private function themeRoot(): string
    {
        return rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'admin'
            . DIRECTORY_SEPARATOR . 'Public'
            . DIRECTORY_SEPARATOR . 'ui'
            . DIRECTORY_SEPARATOR . 'themes';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manifestForTheme(string $themeId): ?array
    {
        $manifestPath = $this->themeRoot() . DIRECTORY_SEPARATOR . $themeId . DIRECTORY_SEPARATOR . 'theme.json';
        if (!is_file($manifestPath)) {
            return null;
        }

        $json = file_get_contents($manifestPath);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $manifest = json_decode($json, true);
        return is_array($manifest) ? $manifest : null;
    }

    /**
     * @param list<string> $entries
     * @return array{id:string,name:string,version:string,description:string}
     */
    private function validateManifest(array $manifest, string $manifestDir, array $entries): array
    {
        $id = trim((string)($manifest['id'] ?? ''));
        $name = trim((string)($manifest['name'] ?? ''));
        $version = trim((string)($manifest['version'] ?? ''));
        $description = trim((string)($manifest['description'] ?? ''));
        $stylesheet = trim((string)($manifest['stylesheet'] ?? 'theme.css'));
        $uiContractVersion = trim((string)($manifest['ui_contract_version'] ?? ''));

        if ($id === '' || preg_match('/^[a-z0-9_-]+$/', $id) !== 1) {
            throw new \RuntimeException('Theme manifest must define a lowercase ID using only letters, numbers, underscores, or hyphens.');
        }

        if ($name === '') {
            throw new \RuntimeException('Theme manifest must define a display name.');
        }

        if ($version === '') {
            throw new \RuntimeException('Theme manifest must define a version.');
        }

        if ($uiContractVersion !== self::UI_CONTRACT_VERSION) {
            throw new \RuntimeException(
                'Theme manifest ui_contract_version must be ' . self::UI_CONTRACT_VERSION . '.'
            );
        }

        if ($stylesheet === '' || $this->isUnsafeArchivePath($stylesheet)) {
            throw new \RuntimeException('Theme manifest stylesheet path is invalid.');
        }

        $stylesheetEntry = $manifestDir !== '' ? $manifestDir . '/' . ltrim($stylesheet, '/') : ltrim($stylesheet, '/');
        if (!in_array($stylesheetEntry, $entries, true)) {
            throw new \RuntimeException('Theme package is missing the stylesheet declared in theme.json.');
        }

        return [
            'id' => $id,
            'name' => $name,
            'version' => $version,
            'description' => $description,
        ];
    }

    /**
     * @param list<string> $entries
     */
    private function resolveManifestEntry(array $entries): ?string
    {
        foreach ($entries as $entry) {
            if ($entry === 'theme.json') {
                return $entry;
            }
        }

        foreach ($entries as $entry) {
            if (preg_match('#^[^/]+/theme\.json$#', $entry) === 1) {
                return $entry;
            }
        }

        return null;
    }

    private function isUnsafeArchivePath(string $path): bool
    {
        return $path === ''
            || str_contains($path, '../')
            || str_contains($path, '..\\')
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:/', $path) === 1;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('Could not create directory: ' . $path);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
                continue;
            }

            @unlink($child);
        }

        @rmdir($path);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException('Theme install source directory is missing: ' . $source);
        }

        $this->ensureDirectory($destination);
        $items = scandir($source);
        if (!is_array($items)) {
            throw new \RuntimeException('Could not read directory during theme install: ' . $source);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath);
                continue;
            }

            if (!@copy($sourcePath, $destinationPath)) {
                throw new \RuntimeException('Could not copy installed theme file into place: ' . $item);
            }
        }
    }
}
