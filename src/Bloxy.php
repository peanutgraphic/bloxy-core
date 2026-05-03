<?php

declare(strict_types=1);

namespace Bloxy\Core;

/**
 * @deprecated since 0.x — bloxy-passkey now reads
 *             config('auth.providers.users.model') directly via
 *             Bloxy\Passkey\Support\ResolveUserClass. The static User-model
 *             registry below is dead code retained as no-op shims for one
 *             minor version, then removed in 0.x+1.0.
 */
class Bloxy
{
    private static ?string $userModel = null;

    /**
     * @deprecated since 0.x — bloxy-passkey now reads
     *             config('auth.providers.users.model'). This method is a
     *             no-op shim retained for one minor version, then removed.
     *             If you were calling this, simply delete the call.
     */
    public static function useUserModel(string $class): void
    {
        @trigger_error(
            'Bloxy::useUserModel() is deprecated. bloxy-passkey reads '
            . 'config(\'auth.providers.users.model\') automatically. '
            . 'Delete the call to silence this notice.',
            E_USER_DEPRECATED,
        );
        self::$userModel = $class;
    }

    /**
     * @deprecated since 0.x — read config('auth.providers.users.model') directly.
     */
    public static function userModel(): ?string
    {
        @trigger_error(
            'Bloxy::userModel() is deprecated. Read '
            . 'config(\'auth.providers.users.model\') directly.',
            E_USER_DEPRECATED,
        );
        return self::$userModel ?? config('auth.providers.users.model');
    }

    /**
     * @deprecated since 0.x — internal-state reset for the deprecated
     *             registry. Retained so consumer test suites that called
     *             this in tearDown don't break. No-op once the registry
     *             goes away in 0.x+1.0.
     */
    public static function reset(): void
    {
        self::$userModel = null;
    }
}
