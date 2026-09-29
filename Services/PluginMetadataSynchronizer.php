<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Illuminate\Support\Facades\Cache;
use Leantime\Core\Db\Db as DbCore;
use Leantime\Domain\Plugins\Repositories\Plugins as PluginRepository;
use RuntimeException;

/**
 * Refreshes the display metadata Leantime copied from installed plugin packages.
 * Leantime 3.10.0's Plugins repository has no update method, so this service uses
 * its database connection with an explicit metadata-only column allowlist.
 */
class PluginMetadataSynchronizer
{
    private $db;

    private PluginRepository $plugins;

    public function __construct(DbCore $db, PluginRepository $plugins)
    {
        $this->db = $db->getConnection();
        $this->plugins = $plugins;
    }

    /**
     * @return array{updated:int, unchanged:int, skipped:int, message:string}
     */
    public function syncInstalledPluginMetadata(): array
    {
        $pluginRoot = realpath(ROOT.'/../app/Plugins/');
        if ($pluginRoot === false || ! is_dir($pluginRoot)) {
            throw new RuntimeException('The installed plugin directory is not available.');
        }

        $pendingUpdates = [];
        $unchanged = 0;
        $skipped = 0;

        foreach ($this->plugins->getAllPlugins(false) as $plugin) {
            if (! isset($plugin->id) || (int) $plugin->id < 1 || ! is_string($plugin->foldername ?? null)) {
                // Config-only plugins have no database row to refresh.
                $skipped++;
                continue;
            }

            $folder = $plugin->foldername;
            if ($folder === '' || basename($folder) !== $folder) {
                $skipped++;
                continue;
            }

            $pluginPath = realpath($pluginRoot.DIRECTORY_SEPARATOR.$folder);
            if ($pluginPath === false || ! is_dir($pluginPath)
                || ! str_starts_with($pluginPath, $pluginRoot.DIRECTORY_SEPARATOR)) {
                $skipped++;
                continue;
            }

            if (($plugin->format ?? '') === 'phar') {
                $archivePath = realpath($pluginPath.DIRECTORY_SEPARATOR.$folder.'.phar');
                if ($archivePath === false || ! str_starts_with($archivePath, $pluginPath.DIRECTORY_SEPARATOR)) {
                    $skipped++;
                    continue;
                }
                $composerPath = 'phar://'.$archivePath.DIRECTORY_SEPARATOR.'composer.json';
            } else {
                $composerPath = realpath($pluginPath.DIRECTORY_SEPARATOR.'composer.json');
                if ($composerPath === false || ! str_starts_with($composerPath, $pluginPath.DIRECTORY_SEPARATOR)) {
                    $skipped++;
                    continue;
                }
            }

            $metadata = json_decode((string) @file_get_contents($composerPath), true);
            if (! is_array($metadata)
                || ! is_string($metadata['name'] ?? null)
                || trim($metadata['name']) === ''
                || ! is_string($metadata['version'] ?? null)
                || trim($metadata['version']) === '') {
                $skipped++;
                continue;
            }

            $authors = [];
            foreach (is_array($metadata['authors'] ?? null) ? $metadata['authors'] : [] as $author) {
                if (! is_array($author)) {
                    continue;
                }

                // Leantime 3.10.0's InstalledPlugin::getMetadataLinks() reads
                // both properties without checking they exist. Composer permits
                // email to be omitted, so normalize it to an empty string before
                // persisting the author object to zp_plugins.
                $author['name'] = is_string($author['name'] ?? null) ? $author['name'] : '';
                $author['email'] = is_string($author['email'] ?? null) ? $author['email'] : '';
                $authors[] = $author;
            }
            $fields = [
                'name' => trim($metadata['name']),
                'description' => is_string($metadata['description'] ?? null) ? $metadata['description'] : '',
                'version' => trim($metadata['version']),
                'homepage' => is_string($metadata['homepage'] ?? null) ? $metadata['homepage'] : '',
                'authors' => json_encode($authors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ];
            $currentAuthors = is_array($plugin->authors ?? null)
                ? json_encode($plugin->authors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : (string) ($plugin->authors ?? '');

            if ($fields['name'] === ($plugin->name ?? null)
                && $fields['description'] === ($plugin->description ?? null)
                && $fields['version'] === ($plugin->version ?? null)
                && $fields['homepage'] === ($plugin->homepage ?? null)
                && $fields['authors'] === $currentAuthors) {
                $unchanged++;
                continue;
            }

            $pendingUpdates[] = ['id' => (int) $plugin->id, 'fields' => $fields];
        }

        $this->db->transaction(function () use ($pendingUpdates): void {
            foreach ($pendingUpdates as $update) {
                $this->db->table('zp_plugins')
                    ->where('id', $update['id'])
                    ->update($update['fields']);
            }
        });

        // Plugin display metadata may be present in Leantime's enabled-plugin cache.
        Cache::store('installation')->forget('plugins.enabledPlugins');

        $updated = count($pendingUpdates);
        return [
            'updated' => $updated,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'message' => sprintf(
                'Refreshed metadata for %d plugin(s); %d already current; %d skipped. Plugin code, enablement, licenses, and install dates were left unchanged.',
                $updated,
                $unchanged,
                $skipped
            ),
        ];
    }
}
