<?php

namespace Leantime\Plugins\LeanLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Plugins\Permissions\PluginsPermissions;
use Leantime\Domain\Plugins\Services\Plugins as PluginService;

/** Declarative editor model for the Leantime 3.10.0 company settings tabs. */
class CompanySettingsEditor
{
    private ?array $cachedWidgetDefinitions = null;
    private const TABS = [
        'details' => ['id' => 'details', 'label' => 'Details', 'icon' => 'fa fa-building', 'regions' => ['content', 'sidebar']],
        'apiKeys' => ['id' => 'apiKeys', 'label' => 'API Keys', 'icon' => 'fa-solid fa-key', 'regions' => ['content']],
        'integrations' => ['id' => 'integrations', 'label' => 'Integrations', 'icon' => 'fa fa-plug', 'regions' => ['content']],
    ];
    private function tabDefinition(string $id): ?array { return self::TABS[$id] ?? null; }

    private const WIDGETS = [
        'company.details.profile' => ['label' => 'Company profile', 'region' => 'content', 'tab' => 'details', 'kind' => 'native', 'placement' => 'any', 'parkable' => true],
        'company.details.defaults' => ['label' => 'Default settings', 'region' => 'content', 'tab' => 'details', 'kind' => 'native', 'placement' => 'any', 'parkable' => true],
        'company.details.notifications' => ['label' => 'Default notification types', 'region' => 'content', 'tab' => 'details', 'kind' => 'native', 'placement' => 'any', 'parkable' => true],
        'company.details.notificationRelevance' => ['label' => 'Default notification relevance', 'region' => 'content', 'tab' => 'details', 'kind' => 'native', 'placement' => 'any', 'parkable' => true],
        'company.details.save' => ['label' => 'Save company settings', 'region' => 'content', 'tab' => 'details', 'kind' => 'native', 'placement' => 'any', 'parkable' => true],
        'company.logo' => ['label' => 'Company logo', 'region' => 'sidebar', 'tab' => 'details', 'kind' => 'native', 'placement' => 'any', 'parkable' => true],
        'company.apiKeys' => ['label' => 'API keys', 'region' => 'content', 'tab' => 'apiKeys', 'kind' => 'native', 'placement' => 'any', 'parkable' => true],
        'company.integrations' => ['label' => 'Integrations page', 'region' => 'content', 'tab' => 'integrations', 'kind' => 'native', 'placement' => 'any', 'parkable' => true],
    ];

    public function widgetDefinitions(): array
    {
        if ($this->cachedWidgetDefinitions !== null) return $this->cachedWidgetDefinitions;
        $widgets = self::WIDGETS;
        $contributions = EventDispatcher::dispatch_filter('plugins.leantimelib.gui.companySettings.widgets', [], [], 'leantime');
        if (!is_array($contributions)) return $this->cachedWidgetDefinitions = $widgets;
        foreach ($contributions as $widget) {
            if (!is_array($widget) || !is_string($widget['id'] ?? null) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,119}$/', $widget['id'])
                || isset($widgets[$widget['id']]) || !is_string($widget['label'] ?? null) || trim($widget['label']) === ''
                || !is_string($widget['region'] ?? null) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/', $widget['region'])
                || (!is_callable($widget['render'] ?? null) && (!in_array($widget['template'] ?? null, ['content', 'notice', 'link'], true) || !is_callable($widget['data'] ?? null)))) {
                Log::error('Leantime Library skipped an invalid Company Settings widget contribution.');
                continue;
            }
            $defaultTab = is_string($widget['tab'] ?? null) && ($widget['tab'] === 'integrations' || $this->tabDefinition($widget['tab']) !== null) ? $widget['tab'] : 'integrations';
            if (!in_array($widget['region'], $this->regionsForTab($defaultTab), true)) {
                Log::error('Leantime Library skipped a Company Settings widget with an unsupported home region.', [
                    'widget_id' => $widget['id'],
                    'tab' => $defaultTab,
                    'region' => $widget['region'],
                ]);
                continue;
            }
            $placement = ($widget['placement'] ?? 'region') === 'any' ? 'any' : 'region';
            $widgets[$widget['id']] = ['label' => trim($widget['label']), 'region' => $widget['region'], 'tab' => $defaultTab, 'kind' => 'provider', 'placement' => $placement, 'parkable' => true, 'template' => $widget['template'] ?? null, 'render' => $widget['render'] ?? null, 'data' => $widget['data'] ?? null];
        }
        return $this->cachedWidgetDefinitions = $widgets;
    }

    /** Regions are declared from the native Leantime tab structure, not inferred globally. */
    public function regionsForTab(string $tabId): array
    {
        $tab = $this->tabDefinition($tabId);
        return $tab['regions'] ?? [];
    }

    public function render(): string
    {
        $layout = $this->layout();
        $html = '<section class="lt-library-company-editor" data-company-editor data-layout-endpoint="'.htmlspecialchars(rtrim(BASE_URL, '/').'/LeanLib/gui/company-settings', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" data-csrf="'.htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
        $html .= '<input type="hidden" name="_token" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
        $html .= '<p class="lt-library-workspace__hint">Select a tab to see its available destinations. Generic widgets can move between declared regions; constrained widgets stay in their required region. Parked widgets are out of the layout and retain their last valid destination.</p>';
        $widgetMetadata = array_map(static fn ($widget) => array_intersect_key($widget, array_flip(['label', 'region', 'kind', 'placement', 'tab', 'parkable'])), $layout['widgetDefinitions']);
        $html .= '<div class="lt-library-company-editor__tabs" data-company-editor-tabs data-layout="'.htmlspecialchars(json_encode(['tabs' => $layout['tabs'], 'activeTab' => $layout['activeTab'], 'regions' => $layout['regions'], 'parked' => $layout['parked'], 'parkedTargets' => $layout['parkedTargets'], 'widgetDefinitions' => $widgetMetadata], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
        foreach ($layout['tabs'] as $tabId) {
            $tab = $this->tabDefinition($tabId);
            if (!$tab) continue;
            $html .= '<button type="button" data-company-editor-tab="'.$this->e($tabId).'" class="'.($tabId === $layout['activeTab'] ? 'is-active' : '').'" aria-pressed="'.($tabId === $layout['activeTab'] ? 'true' : 'false').'">';
            $html .= '<i class="'.$this->e($tab['icon']).'" aria-hidden="true"></i> '.$this->e($tab['label']).'</button>';
        }
        $html .= '</div><div class="lt-library-company-editor__stage">';
        foreach ($layout['tabs'] as $tabId) {
            $tab = $this->tabDefinition($tabId);
            if (!$tab) continue;
            $html .= '<section data-company-editor-panel="'.$this->e($tabId).'"'.($tabId === $layout['activeTab'] ? '' : ' hidden').'><header><i class="'.$this->e($tab['icon']).'" aria-hidden="true"></i> '.$this->e($tab['label']).'</header>';
            foreach ($layout['regions'][$tabId] ?? [] as $region => $ids) {
                $html .= '<h4>'.$this->e(ucfirst($region)).'</h4><ol data-company-region="'.$this->e($tabId).':'.$this->e($region).'">';
                foreach ($ids as $id) $html .= $this->widget($id, $tabId, $region);
                $html .= '</ol>';
            }
            $html .= '</section>';
        }
        $html .= '</div><aside class="lt-library-company-editor__parked"><strong>Parked widgets</strong><small>Available across all tabs</small><ol data-company-region="parked">';
        foreach ($layout['parked'] as $id) {
            $target = $layout['parkedTargets'][$id];
            $html .= $this->widget($id, 'parked', $target['region'], $target['tab']);
        }
        $html .= '</ol></aside><button type="button" class="btn btn-primary" data-company-editor-save>Save company settings layout</button><span role="status" data-company-editor-status></span></section>';
        return $html;
    }

    public function renderTabHeader(array $params = []): void
    {
        $layout = $this->layout();
        $html = '';
        foreach ($layout['tabs'] as $id) {
            if ($id === 'details' || $id === 'apiKeys' || $id === 'integrations') continue;
            $tab = $this->tabDefinition($id);
            if (!$tab) continue;
            $html .= '<li data-leantimelib-company-tab="'.$this->e($id).'"><a href="#'.$this->e($id).'"><span class="'.$this->e($tab['icon']).'"></span> '.$this->e($tab['label']).'</a></li>';
        }
        $html .= '<li data-leantimelib-company-tab="integrations"><a href="#integrations"><span class="fa fa-plug"></span> Integrations</a></li>';
        $payload = json_encode(['tabs' => $layout['tabs'], 'widgets' => $layout['regions']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $html .= '<li hidden data-leantimelib-company-layout="'.htmlspecialchars($payload, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"></li>';
        echo $html;
    }

    public function renderTabContent(array $params = []): void
    {
        $layout = $this->layout();
        $integrationsDirectlyPlaced = in_array(
            'company.integrations',
            $layout['regions']['integrations']['content'] ?? [],
            true
        );
        $allWidgets = [];
        foreach ($layout['regions'] as $tabId => $tabRegions) {
            foreach ($tabRegions as $ids) foreach ($ids as $id) if (!isset($allWidgets[$id])) $allWidgets[$id] = $tabId;
        }
        $stashHtml = '';
        foreach ($allWidgets as $widgetId => $tabId) {
            if ($widgetId === 'company.integrations') {
                if (!$integrationsDirectlyPlaced) $stashHtml .= $this->renderIntegrationsWidget();
                continue;
            }
            $stashHtml .= $this->renderLiveWidgets([$widgetId]);
        }
        echo '<div hidden data-company-widget-stash>'.$stashHtml.'</div>';
        foreach ($layout['tabs'] as $id) {
            $isIntegrations = $id === 'integrations';
            if ($id === 'details' || $id === 'apiKeys') continue;
            $content = $isIntegrations && $integrationsDirectlyPlaced
                ? '<div data-leantimelib-company-region="content">'.$this->renderIntegrationsWidget().'</div>'
                : '';
            echo '<div id="'.$this->e($id).'" data-leantimelib-company-panel="'.$this->e($id).'"'.($isIntegrations ? ' data-leantimelib-integrations-panel' : '').'>'.$content.'</div>';
        }
    }

    private function renderLiveWidgets(array $ids): string
    {
        $html = '';
        foreach (array_values(array_unique($ids)) as $id) {
            $definition = $this->widgetDefinitions()[$id] ?? null;
            if (!is_array($definition) || ($definition['kind'] ?? '') !== 'provider') continue;
            try {
                if (is_callable($definition['render'] ?? null)) {
                    $content = ($definition['render'])(['widgetId' => $id]);
                } else {
                    $data = ($definition['data'])(['widgetId' => $id]);
                    $content = $this->renderGenericTemplate((string) $definition['template'], is_array($data) ? $data : []);
                }
                if (is_string($content)) $html .= '<div data-company-live-widget="'.$this->e($id).'">'.$content.'</div>';
            } catch (\Throwable $exception) {
                Log::error('Leantime Library Company Settings widget failed to render.', ['widget_id' => $id, 'exception_class' => $exception::class]);
            }
        }
        return $html;
    }

    private function renderIntegrationsWidget(): string
    {
        if (!function_exists('can') || !can(PluginsPermissions::MANAGE)) {
            return '<div data-company-live-widget="company.integrations"><p>You do not have permission to manage integrations.</p></div>';
        }

        try {
            $plugins = app(PluginService::class);
            $installed = $plugins->getAllPlugins();
            $installed = is_array($installed) ? $installed : [];
            $preflight = app(PluginPreflight::class);
            $content = view()->file(__DIR__.'/../Templates/integrations.blade.php', [
                'layout' => null,
                'installedPlugins' => $installed,
                'newPlugins' => $preflight->discover($plugins, $installed),
                'pluginPreflight' => $preflight->checksForDisabled($installed),
            ])->render();
            return '<div data-company-live-widget="company.integrations">'.$content.'</div>';
        } catch (\Throwable $exception) {
            Log::error('Leantime Library integrations widget failed to render.', [
                'exception_class' => $exception::class,
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
            ]);
            return '<div data-company-live-widget="company.integrations"><p>Integrations could not be loaded. Check the Leantime application log.</p></div>';
        }
    }

    private function renderGenericTemplate(string $template, array $data): string
    {
        $title = $this->e(is_string($data['title'] ?? null) ? $data['title'] : '');
        $body = $this->e(is_string($data['body'] ?? null) ? $data['body'] : '');
        if ($template === 'notice') return '<div class="alert alert-info"><strong>'.$title.'</strong><p>'.$body.'</p></div>';
        if ($template === 'link') {
            $url = is_string($data['url'] ?? null) ? trim($data['url']) : '';
            $safe = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
                ? $url
                : ((str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\')) ? $url : '');
            return '<div class="leantimelib-generic-widget"><strong>'.$title.'</strong>'.($safe !== '' ? ' <a href="'.$this->e($safe).'">'.$this->e(is_string($data['label'] ?? null) ? $data['label'] : 'Open').'</a>' : '').'<p>'.$body.'</p></div>';
        }
        return '<div class="leantimelib-generic-widget"><strong>'.$title.'</strong><p>'.$body.'</p></div>';
    }

    public function layout(): array
    {
        $raw = app(\Leantime\Domain\Setting\Services\Setting::class)->getSetting('leantimelib.gui.companySettings', '{}');
        $saved = is_string($raw) ? json_decode($raw, true) : null;
        $saved = is_array($saved) ? $saved : [];
        $savedTabs = is_array($saved['tabs'] ?? null) ? $saved['tabs'] : [];
        $tabs = array_values(array_filter(array_filter($savedTabs, 'is_string'), fn ($id) => $this->tabDefinition($id) !== null));
        foreach (array_keys(self::TABS) as $id) if (!in_array($id, $tabs, true)) $tabs[] = $id;
        $widgets = $this->widgetDefinitions();
        $savedParked = is_array($saved['parked'] ?? null) ? $saved['parked'] : [];
        $parked = array_values(array_intersect(array_filter($savedParked, 'is_string'), array_keys($widgets)));
        $parkedTargets = is_array($saved['parkedTargets'] ?? null) ? $saved['parkedTargets'] : [];
        $regions = [];
        $savedRegions = is_array($saved['regions'] ?? null) ? $saved['regions'] : [];
        $requestedTabs = [];
        foreach ($savedRegions as $savedTab => $tabRegions) {
            if (!is_array($tabRegions)) continue;
            foreach ($tabRegions as $ids) {
                if (!is_array($ids)) continue;
                foreach ($ids as $id) if (is_string($id) && !isset($requestedTabs[$id])) $requestedTabs[$id] = $savedTab;
            }
        }
        foreach ($tabs as $tabId) $regions[$tabId] = array_fill_keys($this->regionsForTab($tabId), []);
        $placed = [];
        foreach ($tabs as $tabId) {
            foreach (array_keys($regions[$tabId]) as $regionName) {
                $savedRegionWidgets = is_array($savedRegions[$tabId][$regionName] ?? null) ? $savedRegions[$tabId][$regionName] : [];
                foreach (array_values(array_unique(array_intersect(array_filter($savedRegionWidgets, 'is_string'), array_keys($widgets)))) as $id) {
                    if (isset($placed[$id]) || in_array($id, $parked, true)) continue;
                    if (($widgets[$id]['placement'] ?? 'region') !== 'any' && ($widgets[$id]['region'] ?? '') !== $regionName) continue;
                    $regions[$tabId][$regionName][] = $id;
                    $placed[$id] = true;
                }
            }
        }
        foreach ($widgets as $id => $widget) {
            if (isset($placed[$id]) || in_array($id, $parked, true)) continue;
            $tab = is_string($requestedTabs[$id] ?? null) && in_array($requestedTabs[$id], $tabs, true)
                ? $requestedTabs[$id]
                : (in_array($widget['tab'], $tabs, true) ? $widget['tab'] : ($tabs[0] ?? 'details'));
            if (($widget['placement'] ?? 'region') !== 'any' && !isset($regions[$tab][$widget['region']])) {
                $tab = in_array($widget['tab'], $tabs, true) ? $widget['tab'] : ($tabs[0] ?? 'details');
            }
            $region = isset($regions[$tab][$widget['region']]) ? $widget['region'] : 'content';
            $regions[$tab][$region][] = $id;
        }
        foreach ($parked as $id) {
            $target = is_array($parkedTargets[$id] ?? null) ? $parkedTargets[$id] : [];
            $tab = is_string($target['tab'] ?? null) && in_array($target['tab'], $tabs, true) ? $target['tab'] : ($widgets[$id]['tab'] ?? 'integrations');
            if (($widgets[$id]['placement'] ?? 'region') !== 'any' && !isset($regions[$tab][$widgets[$id]['region']])) {
                $tab = in_array($widgets[$id]['tab'], $tabs, true) ? $widgets[$id]['tab'] : ($tabs[0] ?? 'details');
            }
            $region = is_string($target['region'] ?? null) && isset($regions[$tab][$target['region']])
                && (($widgets[$id]['placement'] ?? 'region') === 'any' || $target['region'] === $widgets[$id]['region'])
                ? $target['region'] : (isset($regions[$tab][$widgets[$id]['region']]) ? $widgets[$id]['region'] : 'content');
            $parkedTargets[$id] = ['tab' => $tab, 'region' => $region];
        }
        return ['tabs' => $tabs, 'activeTab' => in_array($saved['activeTab'] ?? null, $tabs, true) ? $saved['activeTab'] : ($tabs[0] ?? 'details'), 'regions' => $regions, 'parked' => $parked, 'parkedTargets' => $parkedTargets, 'widgetDefinitions' => $widgets];
    }

    private function widget(string $id, string $tabId, ?string $returnRegion = null, ?string $returnTab = null): string
    {
        $definition = $this->widgetDefinitions()[$id] ?? null;
        if (!$definition) return '';
        $homeTab = $definition['tab'] ?? 'integrations';
        return '<li class="lt-library-company-widget" draggable="true" data-company-widget="'.$this->e($id).'" data-default-region="'.$this->e($definition['region']).'" data-default-tab="'.$this->e($homeTab).'" data-return-region="'.$this->e($returnRegion ?? $definition['region']).'" data-return-tab="'.$this->e($returnTab ?? ($tabId === 'parked' ? $homeTab : $tabId)).'" data-placement="'.$this->e($definition['placement']).'" data-widget-kind="'.$this->e($definition['kind']).'" data-widget-tab="'.$this->e($tabId).'"'.($tabId === 'parked' ? ' aria-label="Parked widget; available across tabs"' : '').'><span aria-hidden="true">⠿</span> '.$this->e($definition['label']).'<button type="button" data-company-park-widget>'.($tabId === 'parked' ? '+' : '×').'</button></li>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
