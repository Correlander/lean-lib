<?php

namespace Leantime\Plugins\LeantimeLib\Services;

/**
 * Renders the shared, declarative portion of a provider's settings page.
 * Providers remain responsible for routes, persistence, permissions, and validation.
 */
class SettingsPageRenderer
{
    public const API_VERSION = 1;

    /**
     * Render a settings page definition as safe HTML.
     *
     * Supported block types are section, title, description, checkbox, text, url,
     * number, select, secret, action, and custom. Custom content is executable provider
     * code and must never contain user-authored templates or untrusted HTML.
     *
     * @param array{title?:string,description?:string,supportUrl?:string,headerActions?:callable,blocks?:array} $definition
     * @param array<string,mixed> $values Current provider-owned values. Secrets should be booleans such as `apiKeyConfigured`.
     * @param array<string,string|array> $errors Provider-generated validation errors, keyed by field ID.
     */
    public function render(array $definition, array $values = [], array $errors = []): string
    {
        $normalized = $this->normalize($definition);
        if (is_string($definition['pluginFolder'] ?? null)) {
            $metadata = array_filter($this->readPluginMetadata($definition['pluginFolder']), static fn ($value): bool => $value !== null);
            foreach ($metadata as $key => $value) {
                if (! array_key_exists($key, $definition)) {
                    $normalized[$key] = $value;
                }
            }
        }

        return view()->file(__DIR__.'/../Templates/shared-settings-page.blade.php', [
            'definition' => $normalized,
            'values' => $values,
            'errors' => $errors,
        ])->render();
    }

    private function normalize(array $definition): array
    {
        $title = $definition['title'] ?? 'Plugin settings';
        if (! is_string($title) || trim($title) === '') $title = 'Plugin settings';

        $blocks = $definition['blocks'] ?? [];
        if (! is_array($blocks)) {
            throw new \InvalidArgumentException('Settings page blocks must be an array.');
        }

        $normalizedBlocks = [];
        $seen = [];
        foreach ($blocks as $index => $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                throw new \InvalidArgumentException('Every settings page block must have a type.');
            }
            $type = $block['type'];
            if (! in_array($type, ['section', 'title', 'description', 'checkbox', 'text', 'url', 'number', 'select', 'secret', 'action', 'custom'], true)) {
                throw new \InvalidArgumentException('Unsupported settings page block type at index '.$index.'.');
            }

            if (in_array($type, ['checkbox', 'text', 'url', 'number', 'select', 'secret', 'action'], true)) {
                $id = $block['id'] ?? null;
                if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,119}$/', $id) || isset($seen[$id])) {
                    throw new \InvalidArgumentException('Settings field IDs must be unique and use letters, numbers, dots, underscores, or hyphens.');
                }
                $seen[$id] = true;
                if (! is_string($block['label'] ?? null) || trim($block['label']) === '') {
                    throw new \InvalidArgumentException('Every settings field requires a label.');
                }
                if ($type === 'select' && ! is_array($block['options'] ?? null)) {
                    throw new \InvalidArgumentException('Select fields require an options array.');
                }
                if ($type === 'action' && (
                    ! is_string($block['actionUrl'] ?? null)
                    || (! str_starts_with($block['actionUrl'], '/')
                        && ! $this->isWebUrl($block['actionUrl']))
                )) {
                    throw new \InvalidArgumentException('Action fields require an action URL.');
                }
            }

            if ($type === 'custom' && ! is_callable($block['render'] ?? null)) {
                throw new \InvalidArgumentException('Custom settings blocks require a provider render callback.');
            }

            $normalizedBlocks[] = $block;
        }

        $supportUrl = $definition['supportUrl'] ?? null;
        if ($supportUrl !== null && (! is_string($supportUrl) || ! $this->isWebUrl($supportUrl))) {
            throw new \InvalidArgumentException('Support URL must be an absolute URL.');
        }
        $headerActions = $definition['headerActions'] ?? null;
        if ($headerActions !== null && ! is_callable($headerActions)) {
            throw new \InvalidArgumentException('Custom settings header actions must be a trusted render callback.');
        }

        return [
            'title' => trim($title),
            'description' => is_string($definition['description'] ?? null) ? trim($definition['description']) : '',
            'supportUrl' => $supportUrl,
            'headerActions' => $headerActions,
            'headerClass' => is_string($definition['headerClass'] ?? null) ? trim($definition['headerClass']) : '',
            'headerCopyClass' => is_string($definition['headerCopyClass'] ?? null) ? trim($definition['headerCopyClass']) : '',
            'sectionClass' => is_string($definition['sectionClass'] ?? null) ? trim($definition['sectionClass']) : '',
            'version' => is_string($definition['version'] ?? null) ? trim($definition['version']) : '',
            'author' => is_string($definition['author'] ?? null) ? trim($definition['author']) : '',
            'homepage' => is_string($definition['homepage'] ?? null) && $this->isWebUrl($definition['homepage']) ? $definition['homepage'] : '',
            'blocks' => $normalizedBlocks,
        ];
    }

    /** Read identity fields from the provider's installed Composer metadata. */
    private function readPluginMetadata(string $pluginFolder): array
    {
        if ($pluginFolder === '' || basename($pluginFolder) !== $pluginFolder) return [];
        $pluginRoot = realpath(ROOT.'/../app/Plugins');
        if ($pluginRoot === false || ! is_dir($pluginRoot)) return [];
        $path = realpath($pluginRoot.DIRECTORY_SEPARATOR.$pluginFolder.DIRECTORY_SEPARATOR.'composer.json');
        if ($path === false || ! str_starts_with($path, $pluginRoot.DIRECTORY_SEPARATOR)) return [];

        $metadata = json_decode((string) @file_get_contents($path), true);
        if (! is_array($metadata)) return [];
        $authors = array_values(array_filter($metadata['authors'] ?? [], static fn ($author): bool =>
            is_array($author) && is_string($author['name'] ?? null) && trim($author['name']) !== ''
        ));
        $authorNames = array_map(static fn (array $author): string => trim($author['name']), $authors);

        $support = is_array($metadata['support'] ?? null) ? $metadata['support'] : [];
        $supportUrl = $support['issues'] ?? $support['source'] ?? $support['docs'] ?? null;

        return [
            'title' => is_string($metadata['name'] ?? null) && trim($metadata['name']) !== '' ? trim($metadata['name']) : null,
            'description' => is_string($metadata['description'] ?? null) && trim($metadata['description']) !== '' ? trim($metadata['description']) : null,
            'version' => is_string($metadata['version'] ?? null) && trim($metadata['version']) !== '' ? trim($metadata['version']) : null,
            'author' => $authorNames === [] ? null : implode(', ', $authorNames),
            'homepage' => is_string($metadata['homepage'] ?? null) && $this->isWebUrl($metadata['homepage']) ? $metadata['homepage'] : null,
            'supportUrl' => is_string($supportUrl) && $this->isWebUrl($supportUrl) ? $supportUrl : null,
        ];
    }

    private function isWebUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
