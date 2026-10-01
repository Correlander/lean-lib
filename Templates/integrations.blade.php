@php
    $installedPlugins = is_array($installedPlugins ?? null) ? $installedPlugins : [];
    $newPlugins = is_array($newPlugins ?? null) ? $newPlugins : [];
    $managerEntries = [];

    foreach ($installedPlugins as $plugin) {
        if (!is_object($plugin) || !is_string($plugin->foldername ?? null)) continue;
        $managerEntries[] = ['plugin' => $plugin, 'state' => 'installed'];
    }
    foreach ($newPlugins as $plugin) {
        if (!is_object($plugin) || !is_string($plugin->foldername ?? null)) continue;
        $managerEntries[] = ['plugin' => $plugin, 'state' => 'available'];
    }
    usort($managerEntries, static fn (array $a, array $b): int => strcasecmp((string) ($a['plugin']->name ?? $a['plugin']->foldername), (string) ($b['plugin']->name ?? $b['plugin']->foldername)));

    $selectedFolder = request()->query('libraryPlugin');
    $selectedEntry = null;
    foreach ($managerEntries as $entry) {
        if (is_string($selectedFolder) && hash_equals($entry['plugin']->foldername, $selectedFolder)) {
            $selectedEntry = $entry;
            break;
        }
    }
    if ($selectedEntry === null && $managerEntries !== []) $selectedEntry = $managerEntries[0];

    $pluginSettingsUrl = null;
    $selectedPlugin = $selectedEntry['plugin'] ?? null;
    if ($selectedEntry !== null && $selectedEntry['state'] === 'installed'
        && !empty($selectedPlugin->enabled)
        && preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $selectedPlugin->foldername)) {
        $pluginFolder = $selectedPlugin->foldername;
        $pluginRoot = realpath(ROOT.'/../app/Plugins');
        $settingsFile = $pluginRoot !== false
            ? realpath($pluginRoot.DIRECTORY_SEPARATOR.$pluginFolder.DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR.'Settings.php')
            : false;
        if ($settingsFile !== false && str_starts_with($settingsFile, $pluginRoot.DIRECTORY_SEPARATOR)) {
            $pluginSettingsUrl = rtrim(BASE_URL, '/').'/'.rawurlencode($pluginFolder).'/settings';
        }
    }

    $authors = $selectedPlugin->authors ?? [];
    if (is_string($authors)) {
        $authors = json_decode($authors, true) ?: [];
    }
    if (is_object($authors)) $authors = (array) $authors;
    if (!is_array($authors)) $authors = [];
@endphp

<section class="lt-library-manager" data-library-manager>
    <header class="lt-library-manager__hero">
        <div class="lt-library-manager__brand">
            <span class="lt-library-manager__mark" aria-hidden="true"><i class="fa-solid fa-puzzle-piece"></i></span>
            <div>
                <p class="lt-library-manager__eyebrow">Integration management</p>
                <h2>Leantime Library</h2>
                <p>Review installed plugins and integrations from one place. Plugin code and its permissions remain managed by Leantime.</p>
            </div>
        </div>
        <div class="lt-library-manager__refresh">
            <button type="button" class="btn btn-default" data-plugin-metadata-sync
                data-endpoint="{{ rtrim(BASE_URL, '/') }}/LeanLib/plugins/check-for-updates"
                data-csrf="{{ csrf_token() }}">
                <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                <span>Check for updates</span>
            </button>
            <span role="status" aria-live="polite" data-plugin-metadata-status hidden></span>
        </div>
    </header>

    <div class="lt-library-manager__workspace">
        <nav class="lt-library-manager__list" aria-label="Plugins and integrations">
            <div class="lt-library-manager__list-heading">
                <h3>Plugins</h3>
                <span>{{ count($managerEntries) }}</span>
            </div>
            @forelse ($managerEntries as $entry)
                @php
                    $item = $entry['plugin'];
                    $isSelected = $selectedPlugin !== null && $item->foldername === $selectedPlugin->foldername;
                    $pluginUrl = rtrim(BASE_URL, '/').'/setting/editCompanySettings?'.http_build_query(['libraryPlugin' => $item->foldername]).'#integrations';
                @endphp
                <a class="lt-library-manager__plugin{{ $isSelected ? ' is-selected' : '' }}"
                    href="{{ $pluginUrl }}"{{ $isSelected ? ' aria-current=page' : '' }}>
                    <span class="lt-library-manager__plugin-icon" aria-hidden="true"><i class="fa-solid fa-puzzle-piece"></i></span>
                    <span class="lt-library-manager__plugin-copy">
                        <strong>{{ $item->name ?: $item->foldername }}</strong>
                        <small>{{ $entry['state'] === 'installed' ? (!empty($item->enabled) ? 'Enabled' : 'Disabled') : 'Ready to activate' }}</small>
                    </span>
                    <i class="fa-solid fa-chevron-right lt-library-manager__chevron" aria-hidden="true"></i>
                </a>
            @empty
                <p class="lt-library-manager__empty">No plugins were found in Leantime's plugin directory.</p>
            @endforelse
        </nav>

        <article class="lt-library-manager__detail" aria-live="polite">
            @if ($selectedEntry === null)
                <div class="lt-library-manager__empty-detail">
                    <span class="lt-library-manager__detail-icon" aria-hidden="true"><i class="fa-solid fa-plug"></i></span>
                    <h3>No plugins found</h3>
                    <p>Plugins installed in Leantime will appear here.</p>
                </div>
            @else
                @php
                    $plugin = $selectedEntry['plugin'];
                    $description = is_string($plugin->description ?? null) ? trim($plugin->description) : '';
                    $homepage = is_string($plugin->homepage ?? null) && filter_var($plugin->homepage, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($plugin->homepage, PHP_URL_SCHEME)), ['http', 'https'], true) ? $plugin->homepage : null;
                @endphp
                <div class="lt-library-manager__detail-top">
                    <span class="lt-library-manager__detail-icon" aria-hidden="true"><i class="fa-solid fa-puzzle-piece"></i></span>
                    <div>
                        <p class="lt-library-manager__eyebrow">{{ $selectedEntry['state'] === 'installed' ? (!empty($plugin->enabled) ? 'Enabled plugin' : 'Installed plugin') : 'Discovered plugin' }}</p>
                        <h3>{{ $plugin->name ?: $plugin->foldername }}</h3>
                    </div>
                    <span class="lt-library-manager__state{{ $selectedEntry['state'] === 'installed' && !empty($plugin->enabled) ? ' is-enabled' : '' }}">
                        {{ $selectedEntry['state'] === 'available' ? 'Available' : (!empty($plugin->enabled) ? 'Enabled' : 'Disabled') }}
                    </span>
                </div>

                @if ($description !== '') <p class="lt-library-manager__description">{{ $description }}</p> @endif

                <dl class="lt-library-manager__metadata">
                    @if (!empty($plugin->version))
                        <div><dt>Version</dt><dd>{{ $plugin->version }}</dd></div>
                    @endif
                    <div><dt>Plugin folder</dt><dd><code>{{ $plugin->foldername }}</code></dd></div>
                    @foreach ($authors as $author)
                        @php
                            $author = is_object($author) ? (array) $author : $author;
                            $authorName = is_array($author) && is_string($author['name'] ?? null) ? trim($author['name']) : '';
                        @endphp
                        @if ($authorName !== '')
                            <div><dt>{{ $loop->first ? 'Author' : 'Also' }}</dt><dd>{{ $authorName }}@if (is_string($author['email'] ?? null) && filter_var($author['email'], FILTER_VALIDATE_EMAIL)) <a href="mailto:{{ $author['email'] }}">{{ $author['email'] }}</a>@endif</dd></div>
                        @endif
                    @endforeach
                    @if ($homepage) <div><dt>Website</dt><dd><a href="{{ $homepage }}" target="_blank" rel="noopener noreferrer">{{ $homepage }}</a></dd></div> @endif
                </dl>

                <div class="lt-library-manager__settings">
                    <h4>Settings</h4>
                    @if ($pluginSettingsUrl)
                        <p>This plugin provides its own settings page.</p>
                        <a class="btn btn-default" href="{{ $pluginSettingsUrl }}"><i class="fa-solid fa-gear" aria-hidden="true"></i> Open settings</a>
                    @elseif ($selectedEntry['state'] === 'installed')
                        <p>This plugin does not expose a settings page.</p>
                    @else
                        <p>Activation controls will be available here after plugin metadata preflight is in place.</p>
                    @endif
                </div>
            @endif
        </article>
    </div>
</section>
