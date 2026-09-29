# Leantime Library

Leantime Library is a registry that lets enabled plugins contribute tabs and inline sections to the To-do detail modal and gives administrators one place to set their order.

## Install

Install this project so the manifest is at `app/Plugins/LeantimeLib/composer.json` (the plugin directory must be named exactly `LeantimeLib`). Leantime discovers each direct child folder of `app/Plugins/` that contains a valid `composer.json`; placing this repository folder one level too high or using the repository folder name will prevent discovery. Enable it in **My Apps**. The settings page is available from the plugin controls.

## Current integration

Leantime 3.10.0 exposes `ticketTabs` and `ticketTabsContent` events from the To-do detail modal. The Library listens to those supported hooks and aggregates contributions from enabled plugins with the filter key:

```php
use Leantime\Core\Events\EventDispatcher;

EventDispatcher::add_filter_listener('leantime.plugins.leantimelib.todo.detail.tabs', function (array $tabs, array $params): array {
    $tabs[] = [
        'id' => 'vendor-schedule', // Stable unique ID; letters, digits, dash, underscore. Don't use dots (jQuery UI treats them as selectors).
        'label' => 'Schedule',
        'icon' => 'fa-solid fa-calendar', // Optional icon classes.
        'order' => 100, // Initial/default order before an administrator customizes it.
        'render' => function ($ticket, array $params): string {
            return '<section>Plugin-owned Schedule panel for To-do #'.(int) $ticket->id.'</section>';
        },
    ];

    return $tabs;
});
```

The `render` callback owns its panel content. Return trusted HTML from the contributing plugin's view renderer; the Library escapes tab IDs, labels, and icon classes, and wraps each panel in the matching tab target. `$params['ticket']` is the current ticket model when rendering tabs, and `null` when the contribution list is shown in Library settings. Register contributions regardless of the current ticket so the settings list remains stable.

Contributed tabs are ordered by their saved Library preference. New tabs not yet in that preference are appended by their declared `order`, then ID. Leantime's built-in Details, Files, and Time Tracking tabs remain in their native positions; the tab adapter orders plugin-contributed tabs only.

## Plugin contract

`leantime.plugins.leantimelib.todo.detail.tabs` is the first contribution point. Contributors append metadata; they do not patch Leantime templates or call another plugin's UI code directly. The Library owns collection, validation, ordering, and rendering into the native tab events. Future placements should get separate, target-specific filter keys and Leantime hook adapters rather than a generic nested UI schema.

### To-do inline sections

Plugins can also contribute an inline section using `leantime.plugins.leantimelib.todo.detail.sections`. The Library orders and renders these contributions at Leantime 3.10.0's native `beforeEndRightColumn` hook, which is after the built-in Schedule section. The Library settings page orders tabs and inline sections in separate lists because they occupy different native UI regions. Leantime's built-in Organization and Schedule sections cannot be moved by this hook and remain in their native positions.

```php
EventDispatcher::add_filter_listener('leantime.plugins.leantimelib.todo.detail.sections', function (array $sections, array $params): array {
    $sections[] = [
        'id' => 'github',
        'label' => 'GitHub',
        'icon' => 'fa-brands fa-github',
        'order' => 100,
        'render' => static fn ($ticket, array $params): string => app(GitHubTodoSection::class)->render($ticket),
    ];
    return $sections;
});
```

### Project integrations panels

Enabled plugins may contribute a project-scoped panel to the native **Project Settings → Integrations** content. The Library replaces only the body of that stock Integrations panel with the registered plugin panels; other project settings tabs remain Leantime's native view. The endpoint checks `projects.view` for the requested project before rendering providers. Provider save routes must enforce their own write permission.

Register a panel with the filter `leantime.plugins.leantimelib.project.integrations.panels`:

```php
EventDispatcher::add_filter_listener('leantime.plugins.leantimelib.project.integrations.panels', function (array $panels, array $params): array {
    $panels[] = [
        'id' => 'github',
        'label' => 'GitHub',
        'render' => static fn (int $projectId): string => app(GitHubPanel::class)->render($projectId),
    ];
    return $panels;
});
```

`render` must return trusted HTML for the given project ID. Keep credentials in the provider plugin, validate each request there, and escape user/provider values in its view. This is the first panel contract and is intentionally limited to project integrations.

## Leantime App menu

Leantime's Apps sidebar item is supplied through the `menuStructures.company.administration` filter. A plugin can change that menu link to `/plugins/myapps` without overwriting core files. That only changes the sidebar entry; the Explore Apps tab and direct `/plugins/marketplace` route remain available.

## License

All rights reserved. See [LICENSE](LICENSE).
