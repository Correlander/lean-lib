# Leantime Library

Leantime Library is a proof-of-concept registry that lets enabled plugins contribute tabs to the To-do detail modal and gives administrators one place to set the order of those contributed tabs.

## Install

Install this project in a folder named exactly `LeantimeLib` under Leantime's `app/Plugins/` directory. Enable it in **My Apps**. The settings page is available from the plugin controls.

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

Contributed tabs are ordered by their saved Library preference. New tabs not yet in that preference are appended by their declared `order`, then ID. Leantime's built-in Details, Files, and Time Tracking tabs remain in their native positions; this first adapter orders plugin-contributed tabs only.

## Plugin contract

`leantime.plugins.leantimelib.todo.detail.tabs` is the first contribution point. Contributors append metadata; they do not patch Leantime templates or call another plugin's UI code directly. The Library owns collection, validation, ordering, and rendering into the native tab events. Future placements should get separate, target-specific filter keys and Leantime hook adapters rather than a generic nested UI schema.

## Leantime App menu

Leantime's Apps sidebar item is supplied through the `menuStructures.company.administration` filter. A plugin can change that menu link to `/plugins/myapps` without overwriting core files. That only changes the sidebar entry; the Explore Apps tab and direct `/plugins/marketplace` route remain available.
