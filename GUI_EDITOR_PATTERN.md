# GUI editor pattern for future Library settings

Use this pattern when adding another visual editor to Leantime Library.

## Start from Leantime's real UI

Inspect the Leantime 3.10.0 template and the rendered page in the logged-in browser before building a preview. Record the actual regions, controls, stable selectors, and supported events. Use generic sample values in the preview; never bake a real user's ticket title, date, or other data into the editor.

## Give each editable piece a stable widget identity

Register native controls and plugin contributions in Library services under stable IDs. Keep metadata and behavior separate:

- A provider plugin owns the content and actions of its contribution.
- The Library owns collection, validation, enablement, order, placement, grouping, and saved defaults/overrides.
- Header/container definitions are their own metadata records (stable ID, label, icon), separate from child widgets.

For the To-do editor, `TodoFieldRegistry` defines native IDs and zones; `TodoSectionRegistry` collects plugin sidebar widgets; `TodoSidebarSectionRegistry` stores sidebar header metadata; `TodoLayoutEditor` renders the editor; and `TodoTabRegistry` serializes the saved layout into the native modal hook output.

## Render one ticket-like canvas

`TodoLayoutEditor` should resemble the actual To-do modal, with widgets embedded in the same regions users recognize. Keep the HTML structure in one shared renderer so Library defaults and project overrides remain visually and behaviorally aligned. Use light generic placeholders rather than pretending the preview is a real record.

For the To-do modal, the layout contract is:

- `tabs`: ordered modal tabs
- `main`: main detail fields
- `sidebar`: section headers and ungrouped sidebar widgets
- `groups`: section ID to ordered child widget IDs
- `auxiliary`: widgets below the fixed Save controls
- `hidden`/`parked`: retained widgets hidden from the live modal

Section headers are movable containers. Widgets can move between any sidebar group and the raw sidebar. Removing a header unwraps its active children into the raw sidebar and clears hidden children’s group references. Parking a widget preserves its group so restore returns it to that section.

## Apply settings to the real UI through the hook adapter

Use Leantime's supported plugin events to emit saved metadata. The browser adapter should wait for the native modal's dynamically loaded DOM, map stable widget IDs to the real elements, then move/hide/order those elements without replacing Leantime's form controls. Preserve form values and native submit behavior. Do not implement a second source of truth in provider plugins.

## Keep editing predictable and responsive

- Use direct in-place controls for metadata visible in the canvas; use Leantime's existing icon picker when one is already present.
- New objects should use a reserved stable ID namespace, focus their required name field, and remain unsaved until valid.
- Autosave should send the complete normalized state, get an explicit JSON success/error response, debounce text edits, and surface status near the editor.
- Make destructive actions explicit and reversible where possible; keep deletion available from a parked/secondary state.
- Give controls fixed flex bases and `min-width: 0` to text columns; use `minmax(0, 1fr)`, wrapping, and a stacked mobile layout so icons/buttons do not collapse.
- Scope editor styling to Library classes and keep `dist/` assets and the asset manifest in sync with source files.

## Verify before handing off

Run PHP and JavaScript syntax checks, inspect the save payload against the server validator, check that defaults and project overrides use the same renderer, verify empty/deleted definitions load safely, and confirm source assets match `dist/`. Report when a visual pass was not performed in the live Leantime UI.
