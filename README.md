# Leantime Library

Leantime Library owns the To-do modal layout. It combines Leantime’s native tabs and sidebar sections with enabled-plugin contributions, then lets administrators order all of them and hide plugin contributions from one settings page.

## Install

Install this project so the manifest is at `app/Plugins/LeantimeLib/composer.json` (the plugin directory must be named exactly `LeantimeLib`). Leantime discovers each direct child folder of `app/Plugins/` that contains a valid `composer.json`; placing this repository folder one level too high or using the repository folder name will prevent discovery. Enable it in **My Apps**. The settings page is available from the plugin controls.

When updating an existing install, copy the updated plugin folder contents, including `dist/todo-layout.js` and `dist/mix-manifest.json`. The manifest keeps the asset at the stable `dist/todo-layout.js` file path and adds a version query for browser cache busting; copying only `composer.json` or `register.php` will leave the modal adapter outdated.

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

The `render` callback owns only the panel content. Return trusted HTML from the contributing plugin's view renderer; the Library validates IDs and metadata, renders panel content, and handles the modal layout. `$params['ticket']` is the current ticket model when rendering tabs, and `null` when the contribution list is shown in Library settings. Register contributions regardless of the current ticket so the settings list remains stable.

The Library settings page orders the native Details, Files, and Time Tracking tabs together with plugin-contributed tabs. Administrators can also hide plugin tabs there. The saved order is applied to Leantime's native modal after its contents load.

## Plugin contract

`leantime.plugins.leantimelib.todo.detail.tabs` is a contribution point. Provider plugins append metadata and a content renderer; they do not inject content into Leantime templates or call another plugin's UI code. The Library owns collection, validation, visibility, ordering, rendering, and the saved layout. It uses Leantime's native modal tab events and a Library-owned browser adapter to apply the administrator's layout without changing Leantime core files.

### To-do inline sections

Plugins can also contribute an inline section using `leantime.plugins.leantimelib.todo.detail.sections`. The Library collects these contributions at Leantime 3.10.0's native `beforeEndRightColumn` event. Its browser adapter then orders them alongside Leantime's native Organization and Schedule sections according to the Library setting. Administrators can hide individual plugin sections there; core sections remain enabled.

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
