# lean-library

lean-library is a shared extension point and administration interface for coordinating compatible Leantime plugins and interface customizations. The design is intended to support multiple parts of Leantime; the To-do modal is the first interface currently available for visual customization.

It also provides a shared registry for plugin panels in **Project Settings → Integrations**. Plugin authors contribute content through the Library, which gives administrators a central place to manage compatible additions.

## Install

1. Copy the plugin into `app/Plugins/LeantimeLib/`. Keep the folder name exactly `LeantimeLib`.
2. Enable it from **My Apps**.
3. Open the Library settings from the plugin controls.

When updating, copy the whole plugin folder, including `dist/` and `dist/mix-manifest.json`. Those files are required for browser assets and cache versions.

## GUI customizations and insertions

The **GUI customization** editor is designed as a shared home for visual previews and layout controls as more interfaces are supported. Today, its preview represents the To-do modal: administrators can arrange tabs, fields, sidebar groups, and plugin additions, or park items to hide them. Save controls remain fixed, and changes save automatically. **Reset to defaults** restores the standard layout.

Projects inherit the instance layout. In **Project Settings → Integrations**, enable **Override Library To-do layout for this project** to customize one project's To-do modal. Disable the override or choose **Use Library defaults** to restore inheritance. Sidebar headers and icons are instance-wide; project overrides can move and group available sidebar items.

<!-- Add a screenshot of this editor when a representative image is available. -->

## Plugin developers

Plugins use Leantime's event filters to register additions with the Library. Each contribution needs a stable unique ID, a display label, and a renderer for its content. The Library collects contributions and manages their layout; the provider remains responsible for its content, data access, and write permissions.

| Contribution | Filter | Current use |
| --- | --- | --- |
| To-do tab | `leantime.plugins.leantimelib.todo.detail.tabs` | A tab with a plugin-rendered panel in the To-do modal. |
| To-do sidebar section | `leantime.plugins.leantimelib.todo.detail.sections` | An inline plugin panel in the To-do sidebar. |
| Project integration panel | `leantime.plugins.leantimelib.project.integrations.panels` | A plugin settings panel under Project Settings → Integrations. |

The To-do tab and sidebar section contributions can be arranged in the shared To-do layout editor. Project integration panels currently render in the order their providers register them; a Library ordering control is not implemented yet.

Detailed contribution contracts and examples can live in the [GitHub Wiki](https://github.com/Correlander/leantime-lib/wiki) as they are documented. The README will keep the overview and compact contribution index.

## Other options

- **Hide Explore Apps:** hides the Explore Apps tab and directs the Apps menu to **My Apps**.
- **Fast Onboarding:** simplifies first-run setup, applies saved invite defaults, and skips starter-project and sample-content creation. It also enables the **Work schedule** tab under Profile Settings; that tab is hidden when Fast Onboarding is off.

These options use Leantime hooks and a request-scoped binding where needed; they do not modify Leantime core files.

## License

<small>All rights reserved for now. The intent is to permit non-commercial use; the author is considering a future open-source license that requires derivatives to retain the same license and non-commercial terms.</small>
