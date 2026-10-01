<?php

namespace Leantime\Plugins\LeanLib\Services;

use Leantime\Domain\Plugins\Services\Plugins as PluginService;
use Throwable;

/**
 * Checks Composer metadata before Leantime's plugin service constructs or stores a plugin record.
 *
 * This validates registration metadata only; it does not execute or statically certify plugin code.
 */
class PluginPreflight
{
    /**
     * @return array{valid:bool,errors:list<string>,metadata:array}
     */
    public function inspect(string $folder): array
    {
        $errors = [];
        if ($folder === '' || basename($folder) !== $folder || !preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $folder)) {
            return ['valid' => false, 'errors' => ['The plugin folder name is invalid.'], 'metadata' => []];
        }

        $pluginRoot = realpath(ROOT.'/../app/Plugins');
        if ($pluginRoot === false || !is_dir($pluginRoot)) {
            return ['valid' => false, 'errors' => ['Leantime plugin directory is unavailable.'], 'metadata' => []];
        }

        $pluginPath = realpath($pluginRoot.DIRECTORY_SEPARATOR.$folder);
        if ($pluginPath === false || !is_dir($pluginPath) || !str_starts_with($pluginPath, $pluginRoot.DIRECTORY_SEPARATOR)) {
            return ['valid' => false, 'errors' => ['The plugin directory is missing or outside Leantime plugin directory.'], 'metadata' => []];
        }

        $composerPath = realpath($pluginPath.DIRECTORY_SEPARATOR.'composer.json');
        if ($composerPath === false || !is_file($composerPath) || !str_starts_with($composerPath, $pluginPath.DIRECTORY_SEPARATOR)) {
            $archive = realpath($pluginPath.DIRECTORY_SEPARATOR.$folder.'.phar');
            if ($archive === false || !is_file($archive) || !str_starts_with($archive, $pluginPath.DIRECTORY_SEPARATOR)) {
                return ['valid' => false, 'errors' => ['composer.json was not found in the plugin folder or archive.'], 'metadata' => []];
            }
            $composerPath = 'phar://'.$archive.DIRECTORY_SEPARATOR.'composer.json';
        }

        try {
            $raw = @file_get_contents($composerPath);
        } catch (Throwable) {
            $raw = false;
        }
        if (!is_string($raw)) {
            return ['valid' => false, 'errors' => ['composer.json could not be read.'], 'metadata' => []];
        }
        try {
            $metadata = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['valid' => false, 'errors' => ['composer.json is not valid JSON.'], 'metadata' => []];
        }
        if (!is_array($metadata)) {
            return ['valid' => false, 'errors' => ['composer.json must contain a JSON object.'], 'metadata' => []];
        }

        foreach (['name', 'description', 'version', 'homepage'] as $field) {
            if (!array_key_exists($field, $metadata) || !is_string($metadata[$field])) {
                $errors[] = sprintf('Add a string "%s" field to composer.json.', $field);
            } elseif ($field === 'name' && trim($metadata[$field]) === '') {
                $errors[] = 'The name field must not be empty.';
            } elseif ($field === 'version' && trim($metadata[$field]) === '') {
                $errors[] = 'The version field must not be empty.';
            }
        }
        if (is_string($metadata['homepage'] ?? null) && trim($metadata['homepage']) !== '') {
            $homepage = trim($metadata['homepage']);
            if (filter_var($homepage, FILTER_VALIDATE_URL) === false
                || !in_array(strtolower((string) parse_url($homepage, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                $errors[] = 'The homepage field must be an HTTP or HTTPS URL, or an empty string.';
            }
        }

        if (!array_key_exists('authors', $metadata) || !is_array($metadata['authors'])) {
            $errors[] = 'Add an authors array to composer.json (it may be empty).';
        } else {
            foreach ($metadata['authors'] as $index => $author) {
                if (!is_array($author)) {
                    $errors[] = sprintf('Author entry %d must be an object.', $index + 1);
                    continue;
                }
                if (!is_string($author['name'] ?? null) || trim($author['name']) === '') {
                    $errors[] = sprintf('Author entry %d needs a non-empty name.', $index + 1);
                }
                if (!array_key_exists('email', $author) || !is_string($author['email'])) {
                    $errors[] = sprintf('Author entry %d needs an email string (an empty string is allowed).', $index + 1);
                } elseif (trim($author['email']) !== '' && filter_var(trim($author['email']), FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = sprintf('Author entry %d has an invalid email address.', $index + 1);
                }
            }
        }

        return ['valid' => $errors === [], 'errors' => array_values(array_unique($errors)), 'metadata' => $metadata];
    }

    /**
     * Scan candidates individually so malformed metadata cannot abort discovery of the whole manager page.
     *
     * @param list<object> $installed
     * @return list<object>
     */
    public function discover(PluginService $plugins, array $installed): array
    {
        $pluginRoot = realpath(ROOT.'/../app/Plugins');
        if ($pluginRoot === false || !is_dir($pluginRoot)) {
            return [];
        }

        $installedFolders = [];
        foreach ($installed as $plugin) {
            if (is_object($plugin) && is_string($plugin->foldername ?? null)) {
                $installedFolders[strtolower($plugin->foldername)] = true;
            }
        }

        $directories = @scandir($pluginRoot);
        if (!is_array($directories)) {
            return [];
        }
        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);

        $candidates = [];
        foreach ($directories as $folder) {
            if ($folder === '.' || $folder === '..' || isset($installedFolders[strtolower($folder)])) {
                continue;
            }
            $path = realpath($pluginRoot.DIRECTORY_SEPARATOR.$folder);
            if ($path === false || !is_dir($path) || !str_starts_with($path, $pluginRoot.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $result = $this->inspect($folder);
            if ($result['valid']) {
                try {
                    $pluginModel = $plugins->createPluginFromComposer($folder);
                    $candidate = (object) get_object_vars($pluginModel);
                    $candidate->preflightValid = true;
                    $candidate->preflightErrors = [];
                    $candidates[] = $candidate;
                    continue;
                } catch (Throwable) {
                    $result['errors'][] = 'Leantime could not construct a plugin record from this metadata.';
                }
            }

            $candidate = new \stdClass();
            $candidate->foldername = $folder;
            $candidate->name = is_string($result['metadata']['name'] ?? null) && trim($result['metadata']['name']) !== ''
                ? trim($result['metadata']['name'])
                : $folder;
            $candidate->description = is_string($result['metadata']['description'] ?? null)
                ? $result['metadata']['description']
                : '';
            $candidate->version = is_string($result['metadata']['version'] ?? null)
                ? $result['metadata']['version']
                : '';
            $candidate->homepage = is_string($result['metadata']['homepage'] ?? null)
                ? $result['metadata']['homepage']
                : '';
            $candidate->authors = is_array($result['metadata']['authors'] ?? null)
                ? $result['metadata']['authors']
                : [];
            $candidate->preflightValid = false;
            $candidate->preflightErrors = array_values(array_unique($result['errors']));
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /** @param list<object> $installed @return array<string,array{valid:bool,errors:list<string>,metadata:array}> */
    public function checksForDisabled(array $installed): array
    {
        $checks = [];
        foreach ($installed as $plugin) {
            if (!is_object($plugin) || !empty($plugin->enabled) || !isset($plugin->id) || !is_string($plugin->foldername ?? null)) {
                continue;
            }
            $checks[(string) $plugin->id] = $this->inspect($plugin->foldername);
        }
        return $checks;
    }
}
