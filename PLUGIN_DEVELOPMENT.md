# lean-library contribution API

This page describes the first shared UI contracts provided by lean-library. Each provider keeps its own routes and owns its data, permissions, validation, and persistence. The Library supplies shared rendering and centrally managed GUI surface configuration.

## Shared plugin settings page

A provider keeps its existing settings controller/URL. When that route is rendered, it can use `SettingsPageRenderer` for common blocks. This is a runtime dependency on lean-library for that route; provider bootstrap and unrelated backend routes do not need to call the renderer.

```php
use Leantime\Plugins\LeantimeLib\Services\SettingsPageRenderer;

$rendererClass = SettingsPageRenderer::class;
if (! class_exists($rendererClass) || $rendererClass::API_VERSION !== 1) {
    // Render the provider's own fallback view or show a clear setup message.
    return $this->tpl->display('myplugin.settings');
}

$settingsFragment = app()->make($rendererClass)->render([
    'pluginFolder' => 'MyPlugin', // Reads name, description, version, authors, homepage, and support from composer.json.
    'blocks' => [
        ['type' => 'title', 'text' => 'Connection'],
        ['type' => 'url', 'id' => 'endpoint', 'label' => 'API endpoint', 'help' => 'The provider validates and saves this value.'],
        ['type' => 'secret', 'id' => 'token', 'label' => 'API token'],
        ['type' => 'checkbox', 'id' => 'enabled', 'label' => 'Enable this integration'],
    ],
], [
    'endpoint' => $endpoint,
    'tokenConfigured' => $tokenIsSaved,
    'enabled' => $enabled,
], $fieldErrors);
$this->tpl->assign('settingsContent', $settingsFragment);
return $this->tpl->display('myplugin.settings');
```

The renderer returns a Blade-rendered section, not a full page, form, or route. The provider places it in its own view, wraps it in its own form (with its own CSRF token), validates the submission, and saves settings. Supported block types are `title`, `description`, `checkbox`, `text`, `url`, `number`, `select`, `secret`, `action`, and `custom`. A secret value is never rendered; pass `<fieldId>Configured` as a boolean to show a saved-key placeholder. `custom` accepts a trusted provider callback and is for provider code only, never user-authored templates or HTML.

The Library autoloader must be available for the route that invokes this class. Use feature detection and keep a provider fallback if the Library is optional. Do not call it from `register.php` or assume plugin filesystem adjacency makes classes available.

## GUI customization surfaces

The Library settings page discovers editable surfaces from the `plugins.leantimelib.gui.surfaces` filter. To-do modal is registered by the Library itself. A provider contributes an editor when it has a real settings preview/editor and apply path:

```php
use Leantime\Core\Events\EventDispatcher;

EventDispatcher::add_filter_listener(
    'leantime.plugins.leantimelib.gui.surfaces',
    static function (array $surfaces): array {
        $surfaces[] = [
            'apiVersion' => 1,
            'id' => 'myplugin-project-panel',
            'label' => 'My Plugin panel',
            'icon' => 'fa-solid fa-code-branch',
            'description' => 'Choose where this panel appears in project settings.',
            'order' => 100,
            'overrideCapabilities' => ['order'],
            'provider' => 'My Plugin',
            'renderEditor' => static fn (array $context): string => view('plugins.myplugin.gui-editor', $context)->render(),
        ];
        return $surfaces;
    }
);
```

Surface IDs must be stable and unique. `overrideCapabilities` may include `order`, `visibility`, and/or `content`; only declare controls the provider can actually apply. The editor callback receives a context array and returns a trusted Blade-rendered fragment, without a nested `<form>`. Its save endpoint remains provider-owned. Invalid or unsupported contributions are logged and skipped without taking down other surfaces.

For simple, data-driven editors, providers should describe controls/components with stable IDs, labels, types, defaults, and supported placement/visibility capabilities. The current API registers and renders surface editors; generic shared layout storage is a follow-on implementation. The Library cannot safely infer controls by inspecting arbitrary Blade output. A provider-specific editor and apply adapter is the supported fallback for complex UI.

The To-do canvas currently uses the Library's specialized editor and To-do registries. Project Settings → Integrations ordering is also a built-in surface: the Library controls the instance order, and projects can opt into an order-only override.

## Runtime and project defaults

Contribution discovery happens when the relevant Library page or Leantime surface is rendered. Register definitions during plugin boot; keep expensive network/database work out of contribution callbacks. The Library must be enabled for its central editors and render adapters to run. Providers that can operate without it should keep their backend routes independent and feature-detect the UI capability where needed.

GUI surface configuration has instance defaults and can declare project-level override capabilities. Projects without an explicit override should inherit the current instance default. Persist only project changes, rather than copying instance values into every project.
