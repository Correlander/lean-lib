<?php

namespace Leantime\Plugins\LeanLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Throwable;

/** Provider-owned connection panels for the signed-in user's own account. */
class AccountIntegrationRegistry
{
    public const FILTER = 'leantime.plugins.leantimelib.user.integrations.accounts';
    private const TAB_ID = 'leantimelibConnectedAccounts';

    /** @return array<int, array{id:string,label:string,description:string,order:int,view:?string,data:mixed,render:mixed}> */
    public function getEntries(): array
    {
        $userId = (int) session('userdata.id', 0);
        if ($userId < 1) return [];

        $contributions = EventDispatcher::dispatch_filter(
            self::FILTER,
            [],
            ['userId' => $userId, 'scope' => 'self'],
            'leantime'
        );
        if (!is_array($contributions)) {
            Log::error('Leantime Library received an invalid account integration contribution list.');
            return [];
        }

        $entries = [];
        $seen = [];
        foreach ($contributions as $index => $entry) {
            if (!is_array($entry)) {
                Log::error('Leantime Library skipped a malformed account integration contribution.', ['index' => $index]);
                continue;
            }
            $id = $entry['id'] ?? null;
            $label = $entry['label'] ?? null;
            $description = $entry['description'] ?? '';
            $view = $entry['view'] ?? null;
            $data = $entry['data'] ?? [];
            $render = $entry['render'] ?? null;
            $hasView = is_string($view) && trim($view) !== '' && (is_array($data) || is_callable($data));
            if (!is_string($id) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,119}$/', $id)
                || $id === self::TAB_ID || isset($seen[$id])
                || !is_string($label) || trim($label) === ''
                || !is_string($description)
                || (!is_callable($render) && !$hasView)) {
                Log::error('Leantime Library skipped an invalid account integration contribution.', [
                    'entry_id' => is_string($id) ? substr($id, 0, 120) : null,
                    'index' => $index,
                ]);
                continue;
            }

            $seen[$id] = true;
            $entries[] = [
                'id' => $id,
                'label' => trim($label),
                'description' => trim($description),
                'order' => is_int($entry['order'] ?? null) ? $entry['order'] : 100,
                'view' => $hasView ? trim($view) : null,
                'data' => $data,
                'render' => $render,
            ];
        }

        usort($entries, static fn (array $left, array $right): int => ($left['order'] <=> $right['order']) ?: strcmp($left['id'], $right['id']));
        return $entries;
    }

    public function renderTabHeader(): void
    {
        if ($this->getEntries() === []) return;
        echo '<li data-leantimelib-account-integrations-tab><a href="#'.self::TAB_ID.'">Connected accounts</a></li>';
    }

    public function renderTabContent(): void
    {
        $userId = (int) session('userdata.id', 0);
        if ($userId < 1) return;
        $entries = $this->getEntries();
        if ($entries === []) return;

        echo '<div id="'.self::TAB_ID.'" data-leantimelib-account-integrations>';
        foreach ($entries as $entry) {
            try {
                $context = ['userId' => $userId, 'scope' => 'self'];
                if (is_callable($entry['render'])) {
                    $content = ($entry['render'])($context);
                } else {
                    $data = is_callable($entry['data']) ? ($entry['data'])($context) : $entry['data'];
                    if (!is_array($data)) throw new \UnexpectedValueException('Account integration view data must be an array.');
                    $content = view($entry['view'], $data)->render();
                }
                if (!is_string($content)) throw new \UnexpectedValueException('Account integration renderers must return a string.');
                $id = htmlspecialchars($entry['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $label = htmlspecialchars($entry['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $description = $entry['description'] !== ''
                    ? '<p class="leantimelib-account-integration__description">'.htmlspecialchars($entry['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>'
                    : '';
                echo '<section class="leantimelib-account-integration" data-account-integration="'.$id.'"><h3>'.$label.'</h3>'.$description.'<div class="leantimelib-account-integration__content">'.$content.'</div></section>';
            } catch (Throwable $exception) {
                Log::error('Leantime Library account integration panel failed to render.', [
                    'entry_id' => $entry['id'],
                    'exception_class' => $exception::class,
                    'exception_file' => basename($exception->getFile()),
                    'exception_line' => $exception->getLine(),
                ]);
                $id = htmlspecialchars($entry['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $label = htmlspecialchars($entry['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo '<section class="leantimelib-account-integration" data-account-integration="'.$id.'"><h3>'.$label.'</h3><p>This integration account panel could not be loaded.</p></section>';
            }
        }
        echo '</div>';
    }
}
