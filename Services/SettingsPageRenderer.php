<?php

namespace Leantime\Plugins\LeanLib\Services;

/**
 * Renders the shared, declarative portion of a provider's settings page.
 * Providers remain responsible for routes, persistence, permissions, and validation.
 */
class SettingsPageRenderer
{
    public const API_VERSION = 3;

    /**
     * Render a settings page definition as safe HTML.
     *
     * Supported block types are section, title, description, alert, checkbox, text, url,
     * number, select, secret, action, and custom. Custom content is executable provider
     * code and must never contain user-authored templates or untrusted HTML.
     *
     * @param array{title?:string,description?:string,metadata?:array,footer?:array{autosave:bool,submitLabel:string},footerAction?:array,blocks?:array} $definition
     * @param array<string,mixed> $values Current provider-owned values. Secrets should be booleans such as `apiKeyConfigured`.
     * @param array<string,string|array> $errors Provider-generated validation errors, keyed by field ID.
     */
    public function render(array $definition, array $values = [], array $errors = []): string
    {
        $presenter = app(PluginMetadataRenderer::class);
        $metadataOverrides = $definition['metadata'] ?? [];
        if (! is_array($metadataOverrides)) {
            throw new \InvalidArgumentException('Settings page metadata must be an array.');
        }
        unset($definition['metadata']);

        $composerMetadata = is_string($definition['pluginFolder'] ?? null)
            ? $presenter->fromPluginFolder($definition['pluginFolder']) : [];
        $definition = array_replace($composerMetadata, $metadataOverrides, $definition);

        $normalized = $this->normalize($definition);
        $metadataHtml = $presenter->render($normalized + ['pluginFolder' => $definition['pluginFolder'] ?? null]);

        return view()->file(__DIR__.'/../Templates/shared-settings-page.blade.php', [
            'definition' => $normalized,
            'pluginMetadataHtml' => $metadataHtml,
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
            if (! in_array($type, ['section', 'title', 'description', 'alert', 'checkbox', 'text', 'url', 'number', 'select', 'secret', 'action', 'custom'], true)) {
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
            if ($type === 'alert' && (! is_string($block['text'] ?? null) || ! in_array($block['tone'] ?? 'danger', ['danger', 'warning', 'success', 'info'], true))) {
                throw new \InvalidArgumentException('Alert blocks require text and a supported tone.');
            }

            $normalizedBlocks[] = $block;
        }

        $supportUrl = $definition['supportUrl'] ?? null;
        if ($supportUrl !== null && (! is_string($supportUrl) || ! $this->isWebUrl($supportUrl))) {
            throw new \InvalidArgumentException('Support URL must be an absolute URL.');
        }
        $contributionsUrl = $definition['contributionsUrl'] ?? null;
        if ($contributionsUrl !== null && (! is_string($contributionsUrl) || ! $this->isWebUrl($contributionsUrl))) {
            throw new \InvalidArgumentException('Contributions URL must be an absolute URL.');
        }
        $sourceUrl = $definition['sourceUrl'] ?? null;
        if ($sourceUrl !== null && (! is_string($sourceUrl) || ! $this->isWebUrl($sourceUrl))) {
            throw new \InvalidArgumentException('Source URL must be an absolute HTTP or HTTPS URL.');
        }
        $footerAction = $definition['footerAction'] ?? null;
        if ($footerAction !== null && (! is_array($footerAction)
            || ! is_string($footerAction['label'] ?? null)
            || ! is_string($footerAction['endpoint'] ?? null)
            || (! str_starts_with($footerAction['endpoint'], '/') && ! $this->isWebUrl($footerAction['endpoint']))
            || ! is_string($footerAction['csrfToken'] ?? null))) {
            throw new \InvalidArgumentException('Footer actions require a label, endpoint, and CSRF token.');
        }
        $footer = $definition['footer'] ?? null;
        if ($footer !== null && (! is_array($footer)
            || ! is_bool($footer['autosave'] ?? null)
            || ! is_string($footer['submitLabel'] ?? null)
            || trim($footer['submitLabel']) === '')) {
            throw new \InvalidArgumentException('Settings page footers require an autosave flag and non-empty submit label.');
        }

        return [
            'title' => trim($title),
            'description' => is_string($definition['description'] ?? null) ? trim($definition['description']) : '',
            'supportUrl' => $supportUrl,
            'contributionsUrl' => $contributionsUrl,
            'footerAction' => $footerAction,
            'footer' => $footer,
            'headerClass' => is_string($definition['headerClass'] ?? null) ? trim($definition['headerClass']) : '',
            'headerCopyClass' => is_string($definition['headerCopyClass'] ?? null) ? trim($definition['headerCopyClass']) : '',
            'sectionClass' => is_string($definition['sectionClass'] ?? null) ? trim($definition['sectionClass']) : '',
            'version' => is_string($definition['version'] ?? null) ? trim($definition['version']) : '',
            'authors' => is_array($definition['authors'] ?? null) ? $definition['authors'] : [],
            'license' => is_string($definition['license'] ?? null) ? trim($definition['license']) : '',
            'sourceUrl' => $sourceUrl,
            'homepage' => is_string($definition['homepage'] ?? null) && $this->isWebUrl($definition['homepage']) ? $definition['homepage'] : '',
            'blocks' => $normalizedBlocks,
        ];
    }

    private function isWebUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
