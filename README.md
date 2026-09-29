# Leantime Library

Leantime Library gives administrators one place to arrange the To-do modal and manage compatible plugin additions. It supports Leantime 3.10.0.

## Install

1. Copy the plugin into `app/Plugins/LeantimeLib/`. Keep the folder name exactly `LeantimeLib`.
2. Enable it from **My Apps**.
3. Open the Library settings from the plugin controls.

When updating, copy the whole plugin folder, including `dist/` and `dist/mix-manifest.json`. Those files are required for the browser assets and their cache versions.

## To-do layout

The Library settings editor previews the To-do modal. Drag tabs, fields, sidebar groups, plugin additions, and below-form sections into position, or park items to hide them. The Save controls stay in place. Changes save automatically; **Reset to defaults** restores the original layout.

Projects use the instance layout by default. In **Project Settings → Integrations**, enable **Override Library To-do layout for this project** to customize one project. Turn the override off or choose **Use Library defaults** to restore inheritance.

Sidebar headers and their icons are configured instance-wide. Project overrides can move and group available sidebar items, but cannot rename headers or add new ones.

## Add To-do content from a plugin

Plugins can contribute a complete To-do tab or an inline sidebar section. The Library collects and orders contributions, then applies the saved layout. Providers supply the content and remain responsible for their own data and permissions.

Use stable, unique IDs containing letters, digits, dashes, or underscores. Avoid the reserved native tab IDs `ticketdetails`, `files`, and `timesheet`. Return trusted HTML from the `render` callback, preferably using the provider's view renderer.

### Tab

Register on `leantime.plugins.leantimelib.todo.detail.tabs`:

```php
use Leantime\Core\Events\EventDispatcher;

EventDispatcher::add_filter_listener('leantime.plugins.leantimelib.todo.detail.tabs', function (array $tabs, array $params): array {
    $tabs[] = [
        'id' => 'vendor-schedule',
        'label' => 'Schedule',
        'icon' => 'fa-solid fa-calendar', // Optional icon classes.
        'order' => 100, // Initial order; admins can change it.
        'render' => static function ($ticket, array $params): string {
            return app(ScheduleTab::class)->render($ticket);
        },
    ];

    return $tabs;
});
```

### Inline sidebar section

Register on `leantime.plugins.leantimelib.todo.detail.sections`:

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

Contributions should be registered even when there is no active ticket; the settings editor also reads them. A tab's `render` callback receives the ticket and parameters, and an inline section is rendered in the To-do sidebar. The Library handles placement and visibility after collecting the contributions.

Plugins cannot currently add arbitrary native form fields or items below Save. Native fields are managed by the Library.

## Project integration panels

Plugins can add a panel to **Project Settings → Integrations** with the `leantime.plugins.leantimelib.project.integrations.panels` filter:

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

The Library checks `projects.view` before displaying panels. Provider routes must check write permissions themselves, validate requests, keep credentials in the provider plugin, and escape user or service data in rendered views.

## Other options

- **Hide Explore Apps:** hides the Explore Apps tab and directs the Apps menu to **My Apps**.
- **Fast Onboarding:** simplifies first-run setup, applies saved invite defaults, and skips starter-project and sample-content creation. It also enables the **Work schedule** tab under Profile Settings; that tab is hidden when Fast Onboarding is off.

These options use Leantime hooks and a request-scoped binding where needed; they do not modify Leantime core files.

## License

All rights reserved. See [LICENSE](LICENSE).
