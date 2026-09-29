<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Throwable;

/**
 * Aggregates plugin-provided tabs for the Leantime 3.10.0 To-do detail modal.
 *
 * Contributors register a filter on FILTER and append contribution metadata. The
 * renderer is attached to Leantime's native ticketTabs/ticketTabsContent events.
 */
class TodoTabRegistry
{
    public const FILTER = 'leantime.plugins.leantimelib.todo.detail.tabs';

    private const ORDER_SETTING = 'leantimelib.todo.detail.tabOrder';

    public function __construct(private SettingService $settings) {}

    public function renderTabHeaders(string $event, array $payload): void
    {
        $params = $payload[0] ?? [];
        if (! is_array($params)) {
            return;
        }

        $ticket = $params['ticket'] ?? null;

        foreach ($this->getTabs($ticket) as $tab) {
            $id = htmlspecialchars($tab['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars($tab['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $icon = htmlspecialchars($tab['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            echo '<li data-leantimelib-tab="'.$id.'"><a href="#'.$id.'"><span class="'.$icon.'"></span> '.$label.'</a></li>';
        }
    }

    public function renderTabPanels(string $event, array $payload): void
    {
        $params = $payload[0] ?? [];
        if (! is_array($params)) {
            return;
        }

        $ticket = $params['ticket'] ?? null;

        foreach ($this->getTabs($ticket) as $tab) {
            $id = htmlspecialchars($tab['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            echo '<div id="'.$id.'" data-leantimelib-panel="'.$id.'">';
            try {
                $content = ($tab['render'])($ticket, $params);
                if (is_string($content)) {
                    echo $content;
                }
            } catch (Throwable $exception) {
                Log::error('Leantime Library tab renderer failed.', [
                    'tab' => $tab['id'],
                    'exception' => $exception,
                ]);
                echo '<p>Unable to load this plugin tab.</p>';
            }
            echo '</div>';
        }
    }

    /** @return array<int, array{id:string,label:string,icon:string,order:int,render:callable}> */
    public function getTabs(mixed $ticket = null): array
    {
        $tabs = EventDispatcher::dispatch_filter('plugins.leantimelib.todo.detail.tabs', [], ['ticket' => $ticket], 'leantime');
        if (! is_array($tabs)) {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($tabs as $tab) {
            if (! is_array($tab)) {
                continue;
            }

            $id = $tab['id'] ?? null;
            $label = $tab['label'] ?? null;
            $icon = $tab['icon'] ?? '';
            $render = $tab['render'] ?? null;
            if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,119}$/', $id)
                || in_array($id, ['ticketdetails', 'files', 'timesheet'], true)
                || isset($seen[$id]) || ! is_string($label) || trim($label) === ''
                || ! is_string($icon) || ! preg_match('/^[a-zA-Z0-9 _-]*$/', $icon)
                || ! is_callable($render)) {
                continue;
            }

            $seen[$id] = true;
            $normalized[] = [
                'id' => $id,
                'label' => trim($label),
                'icon' => trim($icon),
                'order' => is_int($tab['order'] ?? null) ? $tab['order'] : 100,
                'render' => $render,
            ];
        }

        $savedOrder = $this->readSavedOrder();
        $savedRanks = array_flip($savedOrder);
        usort($normalized, static function (array $left, array $right) use ($savedRanks): int {
            $leftHasSavedRank = isset($savedRanks[$left['id']]);
            $rightHasSavedRank = isset($savedRanks[$right['id']]);

            if ($leftHasSavedRank && $rightHasSavedRank) {
                return $savedRanks[$left['id']] <=> $savedRanks[$right['id']];
            }
            if ($leftHasSavedRank !== $rightHasSavedRank) {
                return $leftHasSavedRank ? -1 : 1;
            }

            return ($left['order'] <=> $right['order']) ?: strcmp($left['id'], $right['id']);
        });

        return $normalized;
    }

    public function saveOrder(array $requestedOrder, mixed $ticket = null): bool
    {
        $availableIds = array_column($this->getTabs($ticket), 'id');
        $order = [];
        foreach ($requestedOrder as $id) {
            if (is_string($id) && in_array($id, $availableIds, true) && ! in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        foreach ($availableIds as $id) {
            if (! in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        return $this->settings->saveSetting(self::ORDER_SETTING, json_encode($order, JSON_THROW_ON_ERROR));
    }

    private function readSavedOrder(): array
    {
        $value = $this->settings->getSetting(self::ORDER_SETTING, '[]');
        if (! is_string($value)) {
            return [];
        }

        $order = json_decode($value, true);

        return is_array($order) ? array_values(array_filter($order, 'is_string')) : [];
    }
}
