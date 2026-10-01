<?php

namespace Leantime\Plugins\LeanLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Throwable;

/** Optional enabled-plugin detail fragments for the Library manager. */
class PluginManagerRegistry
{
    public const API_VERSION = 1;
    public const FILTER = 'leantime.plugins.leantimelib.pluginManager.settings';

    public function renderSettings(string $pluginId): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $pluginId)) return null;

        $contributions = EventDispatcher::dispatch_filter(
            self::FILTER,
            [],
            ['pluginId' => $pluginId, 'scope' => 'plugin-manager']
        );
        if (!is_array($contributions)) {
            Log::error('Leantime Library received an invalid plugin manager contribution list.');
            return null;
        }

        $selected = null;
        foreach ($contributions as $index => $contribution) {
            if (!is_array($contribution)) {
                Log::error('Leantime Library skipped a malformed plugin manager contribution.', ['index' => $index]);
                continue;
            }
            $candidateId = $contribution['pluginId'] ?? null;
            $render = $contribution['render'] ?? null;
            if (($contribution['apiVersion'] ?? null) !== self::API_VERSION
                || !is_string($candidateId) || !preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $candidateId)
                || !is_callable($render)) {
                Log::error('Leantime Library skipped an invalid plugin manager contribution.', [
                    'plugin_id' => is_string($candidateId) ? substr($candidateId, 0, 100) : null,
                    'index' => $index,
                ]);
                continue;
            }
            if ($candidateId !== $pluginId) continue;
            if ($selected !== null) {
                Log::error('Leantime Library skipped a duplicate plugin manager contribution.', ['plugin_id' => $pluginId]);
                continue;
            }
            $selected = $render;
        }

        if ($selected === null) return null;

        try {
            $content = $selected(['pluginId' => $pluginId, 'scope' => 'plugin-manager']);
            if (!is_string($content)) throw new \UnexpectedValueException('Plugin manager renderers must return a string.');
            return trim($content) !== '' ? $content : null;
        } catch (Throwable $exception) {
            Log::error('Leantime Library plugin manager content failed to render.', [
                'plugin_id' => $pluginId,
                'exception_class' => $exception::class,
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
            ]);
            return null;
        }
    }
}
