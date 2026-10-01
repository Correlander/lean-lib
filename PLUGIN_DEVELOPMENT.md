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

The `data` callback is optional when a view needs no project-specific data. The provider supplies the view and its data; the Library owns the shared outer layout, project ordering, and override UI. A trusted `render` callback remains available for unusual panels. Multiple panels are supported; use stable provider-prefixed IDs to avoid collisions. Keep project repository settings and validation in the provider panel. The Library does not determine whether a provider allows one or multiple repository connections.

## GUI customization surfaces

The Library settings page discovers editable surfaces from the `plugins.leantimelib.gui.surfaces` filter. To-do modal, Project Settings → Integrations, and Company Settings are registered by the Library itself. A provider contributes an editor when it has a real settings preview/editor and apply path:

```php
use Leantime\Core\Events\EventDispatcher;

EventDispatcher::add_filter_listener(
    'leantime.plugins.leantimelib.gui.surfaces',
    static function (array $surfaces): array {
        $surfaces[] = [
            'apiVersion' => 2,
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

For data-driven layout editors, model tabs, widgets, regions, and placement constraints independently. Tabs are named containers whose visible content panels share the selected tab state. Widgets with `placement: 'any'` may move between any compatible tab and region; widgets with a required region or container type, such as sidebar dropdowns, remain restricted to that region. Parked widgets use one shared pool across tabs, but retain their declared constraints and preferred destination so restoring them returns to a valid location. Generic containers should not acquire tab-specific identity unless their behavior requires it. All IDs and placement values are provider-owned stable metadata; user-authored HTML/templates are never executed.

Providers may contribute Company Settings content blocks through `plugins.leantimelib.gui.companySettings.widgets`. Each definition has a stable ID, label, required home `region`, an optional default `tab`, a `placement` value (`any` or `region`), and either a trusted `render` callback or a generic template (`content`, `notice`, or `link`) with a `data` callback. Company Settings uses deliberate tab definitions based on Leantime 3.10.0: Details exposes `content` and `sidebar`; API Keys and Integrations expose `content`. The home region must exist on the chosen tab; unsupported region names are skipped and logged. `placement: 'any'` permits movement among regions that actually exist on the destination tab; `placement: 'region'` keeps the widget in its declared region. Parked widgets are a shared pool, separate from active destination regions, and retain the last valid destination. The Library stores order and placement only; provider code remains responsible for the content and behavior. Callbacks receive a stable `widgetId`, not the current tab, so their content remains tab-agnostic as the widget moves. Provider callbacks are trusted plugin code; no user-authored PHP, Blade, JavaScript, or HTML is executed. Company Settings tab definitions come from Leantime 3.10.0 and are not user-created by this editor. Its editor is generated from this explicit page definition, rather than attempting to infer semantic regions from arbitrary Blade/DOM output. A genuinely new semantic region needs an explicit surface adapter that maps it to a real page destination.

Providers may also contribute tab-agnostic content widgets to the To-do modal through `plugins.leantimelib.gui.todo.widgets`:

```php
EventDispatcher::add_filter_listener(
    'plugins.leantimelib.gui.todo.widgets',
    static function (array $widgets): array {
        $widgets[] = [
            'id' => 'myplugin.status',
            'label' => 'My Plugin status',
            'tab' => 'ticketdetails',
            'region' => 'content',
            'placement' => 'any',
            'template' => 'notice',
            'data' => static fn ($ticket, array $context): array => [
                'title' => 'Status',
                'body' => 'Status for ticket '.($ticket->id ?? ''),
            ],
        ];
        return $widgets;
    }
);
```

The `tab` and `region` define a widget's initial destination. To-do Details exposes its actual `content`, `main`, `sidebar`, and `auxiliary` insertion regions. Files, Timesheet, and provider tabs expose `content`. The home region must exist on the chosen tab; unsupported regions are skipped and logged. `placement: 'any'` allows movement to regions available on the destination tab; `placement: 'region'` restricts the widget to its declared region. The editor renders only regions supported by the selected tab. Widgets share one parked pool, which means they have no active destination until restored; their last valid destination is retained. `template` may be `content`, `notice`, or `link`; the Library escapes text data and only emits HTTP(S) or site-local links. For complex markup, provide a trusted `render` callback instead; it receives `($ticket, $context)`, where `$context['widgetId']` is stable and no active-tab value is supplied. The callback must return a string of provider-owned HTML and must not emit nested forms inside the native ticket form. Project overrides store placement and parked destinations separately from instance defaults.

To-do section and widget callbacks render provider-owned content on the server. Providers can include their own loading, error, or empty UI and a “more details” link to a provider-owned destination; the Library does not fetch provider data or define an async state protocol. Section callbacks return the full section body, while widget callbacks return the widget body inside a stable Library wrapper. Keep network requests, credentials, and provider-specific behavior in the provider.

For general data-driven editors, providers should describe controls/components with stable IDs, labels, types, defaults, and supported placement/visibility capabilities. The Library cannot safely infer controls by inspecting arbitrary Blade output. A provider-specific editor and apply adapter is the supported fallback for complex UI.

The To-do canvas uses Library widget/tab registries and the Library editor. Its tab strip is selectable and parked widgets share one pool; native Files/Timesheet and contributed tabs are previewed as provider-owned containers, while native detail fields remain constrained to ticket details and their declared main/sidebar/auxiliary regions. Project Settings → Integrations ordering is also a built-in surface: the Library controls the instance order, and projects can opt into an order-only override. Company Settings uses the core `tabs`/`tabsContent` dispatch events to expose native settings panels and a Library-owned Integrations tab. Integration content is server-rendered into its selected destination using the same permission check as plugin management. When it is in the default Integrations content region, the widget is emitted directly into that panel so its visibility does not depend on the client-side layout adapter; moving it to another destination still uses the adapter. The editors appear under the same surface selector in Library settings.

## Runtime and project defaults

Contribution discovery happens when the relevant Library page or Leantime surface is rendered. Register definitions during plugin boot; keep expensive network/database work out of contribution callbacks. The Library must be enabled for its central editors and render adapters to run. Providers that can operate without it should keep their backend routes independent and feature-detect the UI capability where needed.

GUI surface configuration has instance defaults and can declare project-level override capabilities. Projects without an explicit override should inherit the current instance default. Persist only project changes, rather than copying instance values into every project.
