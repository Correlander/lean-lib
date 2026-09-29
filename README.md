# Leantime Library

Leantime Library owns the To-do modal layout. Its ticket-shaped editor treats the native modal tabs, individual To-do fields, and plugin sections as draggable widgets. Administrators arrange or park widgets in one canvas; projects inherit the instance layout until someone saves an override.

## Install

Install this project so the manifest is at `app/Plugins/LeantimeLib/composer.json` (the plugin directory must be named exactly `LeantimeLib`). Leantime discovers each direct child folder of `app/Plugins/` that contains a valid `composer.json`; placing this repository folder one level too high or using the repository folder name will prevent discovery. Enable it in **My Apps**. The settings page is available from the plugin controls.

When updating an existing install, copy the updated plugin folder contents, including all files in `dist/` and `dist/mix-manifest.json`. The manifest keeps assets at stable paths and adds version queries for browser cache busting; copying only `composer.json` or `register.php` will leave browser assets outdated.

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

The Library settings page previews Leantime's To-do modal with its title bar, tab strip, Details columns, fixed Save controls, and below-save Subtasks and Discussion. Native fields are draggable widgets; Organization and Schedule are section widgets with their own child fields, and plugin sidebar contributions can also be ordered or parked. Parked widgets sit in a rail beside the ticket preview and are hidden in the live modal while their form values remain intact. At least one top-level tab must stay visible. The same editor appears in **Project Settings → Integrations**; **Use Library defaults** clears the project override. A browser adapter applies the saved layout to Leantime's modal after its contents load.

The layout contract uses four visible zones plus **parked**: `tabs` for the modal's top tabs, `main` for standard detail fields, `sidebar` for grouped Organization/Schedule controls and plugin sections, `auxiliary` for Subtasks/Discussion below the Save controls, and `parked` for hidden widgets. Save controls remain fixed so the form can always be saved. Existing layouts from earlier versions are migrated into the auxiliary zone when loaded.

## Plugin contract

The currently supported To-do contribution types are `todo.tab` and `todo.detailSection`, exposed respectively through `leantime.plugins.leantimelib.todo.detail.tabs` and `leantime.plugins.leantimelib.todo.detail.sections`. A provider can add a complete tab panel or one inline sidebar section; it cannot yet register arbitrary individual form fields or a widget in the below-save area. Native To-do fields are registered separately as Library widgets. The Library owns collection, validation, visibility, ordering, placement, and saved layouts, while each provider owns its contribution's content and behavior. Provider plugins use the Library contract rather than editing Leantime templates or calling another plugin's UI code. The Library uses Leantime's native modal events and a browser adapter without changing Leantime core files.

### To-do inline sections

Plugins can contribute an inline sidebar widget using `leantime.plugins.leantimelib.todo.detail.sections`. The Library collects these contributions at Leantime 3.10.0's native `beforeEndRightColumn` event and the browser adapter places them alongside the individual native sidebar fields. Administrators can order or park each contributed widget in the shared layout editor.

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

Project integrations show a compact **Override Library To-do layout for this project** checkbox. The large ticket editor appears only after enabling the override. Saving writes the project's tab visibility, plugin widget visibility/order, and field positions under Leantime's `projectsettings.{projectId}.*` settings namespace; unchecking or selecting **Use Library defaults** clears the project layout and restores inheritance.

## Leantime App menu

The Library settings page has an optional **Hide Explore Apps and make My Apps the only Apps tab and destination** setting. When enabled, a supported `menuStructures.company` filter sends the Apps sidebar item to `/plugins/myapps`; a Library browser adapter hides the Explore Apps tab in Leantime's shared Apps navigation and redirects direct `/plugins/marketplace` visits to `/plugins/myapps`. **Fast Onboarding** is one toggle that keeps account setup, applies the invite's saved defaults for appearance and schedule, and prevents automatic starter-project/sample-content creation. When enabled, the Library adds a self-service **Work schedule** tab under **Profile settings**; that tab is not registered when Fast Onboarding is off. Leantime 3.10.0 has no cancellation hook around the Help service, so the Library uses a narrow request-scoped service binding for starter-project suppression. No Leantime core files are changed.

## License

All rights reserved. See [LICENSE](LICENSE).
