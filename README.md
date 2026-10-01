# lean-lib

lean-lib is a shared extension point and administration interface for coordinating compatible Leantime plugins and interface customizations. Its GUI editors cover the To-do modal and Company Settings, with a Library-owned Integrations destination.

It also provides a shared registry for plugin panels in **Project Settings → Integrations**. Plugin authors contribute content through the Library, which gives administrators a central place to manage compatible additions.

## Install

1. Copy the plugin into `app/Plugins/LeanLib/`. The Composer package name is `lean-lib`; Leantime's installed folder/ID is `LeanLib` because it derives PHP namespaces and lifecycle class names from that folder.
2. Enable it from **My Apps**.
3. Open the Library settings from the plugin controls.

Existing installations use the previous folder ID `LeantimeLib`. Leantime treats `LeanLib` as a new plugin record rather than renaming that record. Disable the old entry, install and enable `LeanLib`, then verify the Library settings; the Library's saved setting keys are unchanged.

Version 0.24.0 keeps the `lean-lib` Composer package name while using Leantime’s normalized `LeanLib` installed folder ID. It includes settings API v3 and GUI surface API v2, tab-aware To-do and Company Settings editors, provider-contributed Company Settings widgets, a provider-neutral Connected accounts tab, and a Library plugin manager in Company Settings → Integrations. The manager lists plugins alphabetically, shows metadata and settings links, preflights Composer metadata before install/enable, and delegates lifecycle actions to Leantime. Native My Apps, Marketplace, details, and marketplace install routes redirect to the Library while it is enabled. Removing a plugin registration may run its uninstall handler but leaves the plugin folder on disk. Preflight validates registration metadata only; it does not inspect or sandbox plugin code. The Library-wide action refreshes stored display metadata and does not update plugin code. The default Company Settings Integrations destination is rendered server-side before the layout adapter runs. Account connection state/actions and plugin-specific behavior remain provider-owned. When updating, copy the whole plugin folder, including `dist/` and `dist/mix-manifest.json`.

## GUI customizations and insertions

The **GUI customization** editor provides tab-aware previews and layout controls. In the To-do modal, administrators can switch among tabs, arrange supported detail fields and sidebar groups, and move generic provider widgets between compatible tabs and regions. Company Settings exposes its native Details, API Keys, and Integrations tabs; generic widgets can move between tabs and regions, while region-bound widgets stay constrained. Both editors use shared parked pools and preserve a widget's destination when it is restored. Company Settings layouts have an explicit save action.

Projects inherit the instance layout. In **Project Settings → Integrations**, enable **Override instance To-do layout for this project** to customize one project's To-do modal. Disable the override or choose **Use instance defaults** to restore inheritance. Sidebar headers and icons are instance-wide; project overrides can move and group available sidebar items.

<!-- Add a screenshot of this editor when a representative image is available. -->

## Plugin developers

Plugins use Leantime's event filters to register additions with the Library. Each contribution needs a stable unique ID, a display label, and a renderer for its content. The Library collects contributions and manages their layout; the provider remains responsible for its content, data access, and write permissions.

| Contribution | Filter | Current use |
| --- | --- | --- |
| To-do tab | `leantime.plugins.leantimelib.todo.detail.tabs` | A tab with a plugin-rendered panel in the To-do modal. |
| To-do sidebar section | `leantime.plugins.leantimelib.todo.detail.sections` | An inline plugin panel in the To-do sidebar. |
| Project integration panel | `leantime.plugins.leantimelib.project.integrations.panels` | A plugin settings panel under Project Settings → Integrations. |
| Editable GUI surface | `leantime.plugins.leantimelib.gui.surfaces` | A provider-owned customization editor in the Library's GUI surface selector. |

The To-do tab and sidebar section contributions can be arranged in the shared To-do layout editor. Project integration panels use the order set in the Library’s **Project integrations** GUI editor; an individual project can enable an order-only override in its Integrations settings.

Company Settings widgets can register through `plugins.leantimelib.gui.companySettings.widgets`. Company Settings → Integrations now hosts the Library manager shell, backed by Leantime's plugin service for installed and discoverable apps. The shell is an incremental UI stage: lifecycle actions still await metadata preflight, and the native Apps navigation/pages are not yet hidden.

Provider settings pages use `SettingsPage` and `SettingsPageBlock` for standard page sections and fields, while keeping their existing routes and save logic. Project integration panels get a shared title/description/divider/content frame. Editable GUI surfaces register through `plugins.leantimelib.gui.surfaces`. See [Plugin development](PLUGIN_DEVELOPMENT.md) for examples.

The Library settings page uses the same renderer for its title, metadata, standard options, and section descriptions. Its draggable GUI editor remains a Library-owned custom block.

Detailed contribution contracts and examples can live in the [GitHub Wiki](https://github.com/Correlander/leantime-lib/wiki) as they are documented. The README will keep the overview and compact contribution index.

## Other options

- **Fast Onboarding:** simplifies first-run setup, applies saved invite defaults, and skips starter-project and sample-content creation. It also enables the **Work schedule** tab under Profile Settings; that tab is hidden when Fast Onboarding is off.

These options use Leantime hooks and a request-scoped binding where needed; they do not modify Leantime core files.

## License

<small>All rights reserved for now. The intent is to permit non-commercial use; a future open-source license that requires derivatives to retain the same license and non-commercial terms is probably the endpoint, but I don't want to focus on licensing details rn...</small>
