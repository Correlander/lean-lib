<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Leantime\Domain\Help\Services\Helper;

/**
 * Prevents Leantime 3.10.0's Help service from generating a starter project,
 * milestone, tasks, and goals when the Library option is enabled.
 *
 * The core service has no plugin filter around these calls, so the Library binds
 * this narrow subclass for the current request when the feature is enabled.
 */
class NoDefaultProjectHelper extends Helper
{
    public function ensureDefaultProject(int $userId, string $role = 'editor'): void {}

    public function createDefaultProject(int $userId, string $role = 'editor'): int
    {
        return 0;
    }
}
