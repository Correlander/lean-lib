# lean-lib contribution API

This page describes the shared UI contracts provided by lean-lib (settings API v3). Each provider keeps its own routes and owns its data, permissions, validation, and persistence. The Library supplies shared rendering and centrally managed GUI surface configuration.

## Shared plugin settings page

A provider keeps its existing settings controller/URL. When that route is rendered, use `SettingsPage` and `SettingsPageBlock` to declare the common settings blocks. This is a runtime dependency on lean-lib for that route; provider bootstrap and unrelated backend routes do not need to call the renderer.

```php
use Leantime\Plugins\LeanLib\Services\SettingsPage;
use Leantime\Plugins\LeanLib\Services\SettingsPageBlock;

$settingsFragment = SettingsPage::forPlugin('MyPlugin')
    ->insert(
        SettingsPageBlock::title('Connection'),
        SettingsPageBlock::url('endpoint', 'API endpoint', ['help' => 'The provider validates and saves this value.']),
        SettingsPageBlock::secret('token', 'API token'),
        SettingsPageBlock::checkbox('enabled', 'Enable this integration')
    )
    ->render([
        'endpoint' => $endpoint,
        'tokenConfigured' => $tokenIsSaved,
        'enabled' => $enabled,
    ], $fieldErrors);
$this->tpl->assign('settingsContent', $settingsFragment);
return $this->tpl->display('myplugin.settings');
```

The shared builder reads the page title, description, authors and their optional emails, version, license, source, support URL, and funding URL from the provider's installed `composer.json`, unless explicitly overridden. Put one Composer author object per person; each object can have its own `email`. Composer `funding` supplies the optional contributions link, or call `->contributionsUrl(...)`. The Version link uses `support.source` exactly as provided, regardless of host or path; call `->sourceUrl(...)` to override it. Missing metadata gets a visible placeholder. The standard header, support/contributions area, metadata rows, and footer all use the same Library template. Every page that uses the shared footer calls `->footer(bool $autosave, string $submitLabel = 'Save')`: `true` renders the autosave status slot and `false` renders a submit button. The page/provider still owns its autosave implementation and persistence. The Library metadata refresh action uses `->footerAction(label, endpoint, csrfToken)` and shares the footer's divider and layout. The builder returns a Blade-rendered section, not a full page, form, or route. The provider places it in its own view, wraps it in its own form (with its own CSRF token), validates the submission, and saves settings. Standard blocks include section headings/descriptions, alerts, checkboxes, text/URL/number fields, selects, secrets, and actions. A secret value is never rendered; pass `<fieldId>Configured` as a boolean to show a saved-key placeholder. `custom` accepts a trusted provider callback and is for provider code only, never user-authored templates or HTML.

The Library autoloader must be available for the route that invokes this class. This is a runtime dependency for the settings route. Do not call it from `register.php` or assume plugin filesystem adjacency makes classes available.

## Project Settings → Integrations panels

The Library wraps each contributed panel in the standard title, optional description, divider, content, and spacing. A provider returns only its identity and rendered view:

```php
$panels[] = [
    'id' => 'myplugin',
    'label' => 'My Plugin',
    'description' => 'Configure this project connection.',
    'view' => 'myplugin::project-settings',
    'data' => static fn (int $projectId): array => ['projectId' => $projectId],
];
```

The `data` callback is optional when a view needs no project-specific data. The provider supplies the view and its data; the Library owns the shared outer layout, project ordering, and override UI. A trusted `render` callback remains available for unusual panels.

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

Surface IDs must be stable and unique. `overrideCapabilities` may include `order`, `visibility`, and/or `content`; only declare controls the provider can actually apply. The editor callback receives a context array and returns a trusted Blade-rendered fragment, without a nested `<form>`. In the Library page, the context is empty; in Project Settings → Integrations it includes `projectId` and `canEdit`. A surface that supports project overrides must render the project-specific controls when `projectId` is present, and reset those controls to the current instance default. Its save endpoint remains provider-owned. Invalid or unsupported contributions are logged and skipped without taking down other surfaces.

For simple, data-driven editors, providers should describe controls/components with stable IDs, labels, types, defaults, and supported placement/visibility capabilities. The current API registers and renders surface editors; generic shared layout storage is a follow-on implementation. The Library cannot safely infer controls by inspecting arbitrary Blade output. A provider-specific editor and apply adapter is the supported fallback for complex UI.

The To-do canvas currently uses the Library's specialized editor and To-do registries. Project Settings → Integrations ordering is also a built-in surface: the Library controls the instance order, and projects can opt into an order-only override. Both editors appear under the same surface selector in the Library settings page and in Project Settings → Integrations.

## Runtime and project defaults

Contribution discovery happens when the relevant Library page or Leantime surface is rendered. Register definitions during plugin boot; keep expensive network/database work out of contribution callbacks. The Library must be enabled for its central editors and render adapters to run. Providers that can operate without it should keep their backend routes independent and feature-detect the UI capability where needed.

GUI surface configuration has instance defaults and can declare project-level override capabilities. Projects without an explicit override should inherit the current instance default. Persist only project changes, rather than copying instance values into every project.
