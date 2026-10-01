<?php

namespace Leantime\Plugins\LeanLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Throwable;

/** Discovers and validates surfaces that can be edited from Library settings. */
class GuiSurfaceRegistry
{
    public const API_VERSION = 1;
    public const FILTER = 'plugins.leantimelib.gui.surfaces';

    /**
     * @return array<int,array{id:string,label:string,icon:string,description:string,order:int,renderEditor:callable,overrideCapabilities:array,provider:string}>
     */
    public function getSurfaces(): array
    {
        $builtIn = [[
            'id' => 'todo-modal',
            'label' => 'To-do modal',
            'icon' => 'fa-solid fa-list-check',
            'description' => 'Arrange the native To-do modal and registered plugin contributions.',
            'order' => 0,
            'overrideCapabilities' => ['order', 'visibility', 'content'],
            'provider' => 'Leantime Library',
            'renderEditor' => static function (array $context = []): string {
                if (isset($context['projectId'])) {
                    return app(TodoLayoutEditor::class)->renderProjectControls(
                        (int) $context['projectId'],
                        (bool) ($context['canEdit'] ?? false)
                    );
                }
                $tabs = app(TodoTabRegistry::class)->getTabs(null, true);
                $sections = app(TodoSectionRegistry::class)->getSections(null, [], true);
                return app(TodoLayoutEditor::class)->renderGlobalControls($tabs, $sections);
            },
        ], [
            'id' => 'project-integrations',
            'label' => 'Project integrations',
            'icon' => 'fa-solid fa-plug',
            'description' => 'Set the order of provider panels in Project Settings → Integrations.',
            'order' => 10,
            'overrideCapabilities' => ['order'],
            'provider' => 'Leantime Library',
            'renderEditor' => static function (array $context = []): string {
                $registry = app(ProjectIntegrationRegistry::class);
                if (isset($context['projectId'])) {
                    return $registry->renderProjectOrderControls(
                        (int) $context['projectId'],
                        (bool) ($context['canEdit'] ?? false)
                    );
                }
                return $registry->renderGlobalOrderEditor();
            },
        ]];
        $contributions = EventDispatcher::dispatch_filter(self::FILTER, [], [], 'leantime');
        if (! is_array($contributions)) {
            Log::error('Leantime Library received an invalid GUI surface contribution list.');
            $contributions = [];
        }

        $seen = ['todo-modal' => true, 'project-integrations' => true];
        $surfaces = $builtIn;
        foreach ($contributions as $index => $surface) {
            if (! is_array($surface)) {
                Log::error('Leantime Library skipped a malformed GUI surface contribution.', ['index' => $index]);
                continue;
            }

            $id = $surface['id'] ?? null;
            $label = $surface['label'] ?? null;
            $renderEditor = $surface['renderEditor'] ?? null;
            $apiVersion = $surface['apiVersion'] ?? self::API_VERSION;
            $icon = $surface['icon'] ?? '';
            $description = $surface['description'] ?? '';
            $capabilities = $surface['overrideCapabilities'] ?? [];
            if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/', $id)
                || isset($seen[$id]) || ! is_string($label) || trim($label) === ''
                || ! is_string($icon) || ! preg_match('/^[a-zA-Z0-9 _-]{0,120}$/', $icon)
                || ! is_string($description) || ! is_array($capabilities) || ! is_callable($renderEditor)
                || $apiVersion !== self::API_VERSION) {
                Log::error('Leantime Library skipped an invalid GUI surface contribution.', [
                    'surface_id' => is_string($id) ? substr($id, 0, 80) : null,
                    'index' => $index,
                ]);
                continue;
            }

            $validCapabilities = array_values(array_filter($capabilities, static fn ($capability): bool =>
                is_string($capability) && in_array($capability, ['order', 'visibility', 'content'], true)
            ));
            $seen[$id] = true;
            $surfaces[] = [
                'id' => $id,
                'apiVersion' => self::API_VERSION,
                'label' => trim($label),
                'icon' => trim($icon),
                'description' => trim($description),
                'order' => is_int($surface['order'] ?? null) ? $surface['order'] : 100,
                'renderEditor' => $renderEditor,
                'overrideCapabilities' => $validCapabilities,
                'provider' => is_string($surface['provider'] ?? null) ? trim($surface['provider']) : '',
            ];
        }

        usort($surfaces, static fn (array $left, array $right): int =>
            ($left['order'] <=> $right['order']) ?: strcmp($left['id'], $right['id'])
        );

        return $surfaces;
    }

    /** Render one editor while isolating failures in provider code. */
    public function renderEditor(array $surface, array $context = []): string
    {
        try {
            $html = ($surface['renderEditor'])($context);
            return is_string($html) ? $html : '';
        } catch (Throwable $exception) {
            Log::error('Leantime Library GUI surface editor failed.', [
                'surface' => $surface['id'] ?? null,
                'provider' => $surface['provider'] ?? null,
                'exception_class' => $exception::class,
                'exception_code' => (int) $exception->getCode(),
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
            ]);
            return '<div class="alert alert-danger" role="alert">This customization editor could not be loaded.</div>';
        }
    }
}
