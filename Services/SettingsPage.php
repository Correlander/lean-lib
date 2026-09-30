<?php

namespace Leantime\Plugins\LeantimeLib\Services;

/** Fluent, low-boilerplate definition for a provider's shared settings page. */
final class SettingsPage
{
    public const API_VERSION = 1;

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

    public function supportUrl(string $url): self
    {
        $this->definition['supportUrl'] = $url;
        return $this;
    }

    /** Trusted provider-rendered header actions, for example a support link and metadata refresh button. */
    public function headerActions(callable $render): self
    {
        $this->definition['headerActions'] = $render;
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
