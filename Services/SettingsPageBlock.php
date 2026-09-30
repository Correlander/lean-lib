<?php

namespace Leantime\Plugins\LeantimeLib\Services;

/** A standard, metadata-only block for a shared plugin settings page. */
final class SettingsPageBlock
{
    private static int $actionCount = 0;

    private function __construct(private array $definition) {}

    public static function section(string $title, string $description = ''): self
    {
        return new self(['type' => 'section', 'text' => $title, 'description' => $description]);
    }

    /** A title block is a standard titled section, optionally with a short description. */
    public static function title(string $title, string $description = ''): self
    {
        return self::section($title, $description);
    }

    public static function description(string $text): self
    {
        return new self(['type' => 'description', 'text' => $text]);
    }

    public static function alert(string $text, string $tone = 'danger'): self
    {
        return new self(['type' => 'alert', 'text' => $text, 'tone' => $tone]);
    }

    public static function checkbox(string $id, string $label, string $help = '', bool $default = false): self
    {
        return new self(['type' => 'checkbox', 'id' => $id, 'label' => $label, 'help' => $help, 'default' => $default]);
    }

    public static function text(string $id, string $label, array $options = []): self
    {
        return new self(array_merge(['type' => 'text', 'id' => $id, 'label' => $label], $options));
    }

    public static function url(string $id, string $label, array $options = []): self
    {
        return new self(array_merge(['type' => 'url', 'id' => $id, 'label' => $label], $options));
    }

    public static function number(string $id, string $label, array $options = []): self
    {
        return new self(array_merge(['type' => 'number', 'id' => $id, 'label' => $label], $options));
    }

    public static function select(string $id, string $label, array $options, string $help = ''): self
    {
        return new self(['type' => 'select', 'id' => $id, 'label' => $label, 'options' => $options, 'help' => $help]);
    }

    public static function secret(string $id, string $label, array $options = []): self
    {
        return new self(array_merge(['type' => 'secret', 'id' => $id, 'label' => $label], $options));
    }

    public static function action(string $label, string $url): self
    {
        return new self(['type' => 'action', 'id' => 'action-'.(++self::$actionCount), 'label' => $label, 'actionUrl' => $url]);
    }

    /** Trusted provider callback for a settings block with provider-specific behavior. */
    public static function custom(callable $render): self
    {
        return new self(['type' => 'custom', 'render' => $render]);
    }

    public function toArray(): array
    {
        return $this->definition;
    }
}
