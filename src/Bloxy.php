<?php

declare(strict_types=1);

namespace Bloxy\Core;

/**
 * Static registry for app-level wiring that bloxy-core needs to know about
 * but cannot hard-code (e.g., the app's User model class).
 *
 * Apps call this once from their own ServiceProvider:
 *
 *     Bloxy::useUserModel(\App\Models\User::class);
 */
class Bloxy
{
    private static ?string $userModel = null;

    public static function useUserModel(string $class): void
    {
        self::$userModel = $class;
    }

    public static function userModel(): ?string
    {
        return self::$userModel;
    }

    public static function reset(): void
    {
        self::$userModel = null;
    }
}
