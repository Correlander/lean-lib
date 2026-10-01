<?php

namespace Leantime\Plugins\LeanLib\Services;

/** Fluent, low-boilerplate definition for a provider's shared settings page. */
final class SettingsPage
{
    public const API_VERSION = 3;

    private array $definition = [];
    /** @var SettingsPageBlock[] */
    private array $blocks = [];

    private function __construct(private string $pluginFolder)
    {
        $this->definition['pluginFolder'] = $pluginFolder;
    }

    public static function forPlugin(string $pluginFolder): self
    {
        return new self($pluginFolder);
    }

    /** Add the standard form footer. The caller owns autosave behavior and persistence. */
    public function footer(bool $autosave, string $submitLabel = 'Save'): self
    {
        $this->definition['footer'] = [
            'autosave' => $autosave,
            'submitLabel' => $submitLabel,
        ];
        return $this;
    }

    public function title(string $title): self
    {
        $this->definition['title'] = $title;
        return $this;
    }

    /** Page-level introduction; plugin identity and metadata default from composer.json. */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;
        return $this;
    }

    /** Override Composer values with header metadata only; presentation stays Library-owned. */
    public function metadata(array $metadata): self
    {
        $allowed = ['title', 'description', 'version', 'authors', 'license', 'homepage', 'sourceUrl', 'supportUrl', 'contributionsUrl'];
        foreach ($metadata as $key => $_value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException('Settings page metadata contains an unsupported field.');
            }
        }
        $this->definition['metadata'] = array_replace($this->definition['metadata'] ?? [], $metadata);
        return $this;
    }

    public function supportUrl(string $url): self
    {
        $this->definition['supportUrl'] = $url;
        return $this;
    }

    public function contributionsUrl(string $url): self
    {
        $this->definition['contributionsUrl'] = $url;
        return $this;
    }

    /** The Version link target is supplied as-is by the plugin author. */
    public function sourceUrl(string $url): self
    {
        $this->definition['sourceUrl'] = $url;
        return $this;
    }

    /** Add the Library's standard footer action, rendered in the shared footer layout. */
    public function footerAction(string $label, string $endpoint, string $csrfToken): self
    {
        $this->definition['footerAction'] = [
            'label' => $label,
            'endpoint' => $endpoint,
            'csrfToken' => $csrfToken,
        ];
        return $this;
    }

    public function insert(SettingsPageBlock ...$blocks): self
    {
        array_push($this->blocks, ...$blocks);
        return $this;
    }

    public function render(array $values = [], array $errors = []): string
    {
        $definition = $this->definition;
        $definition['blocks'] = array_map(static fn (SettingsPageBlock $block): array => $block->toArray(), $this->blocks);
        return app(SettingsPageRenderer::class)->render($definition, $values, $errors);
    }
}
