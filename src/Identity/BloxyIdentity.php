<?php

declare(strict_types=1);

namespace Bloxy\Core\Identity;

/**
 * Marker contract for the app's user/identity model.
 *
 * The `Authorizable` trait satisfies this contract automatically. Apps can
 * also implement it directly on a non-Eloquent identity (service account,
 * agent identity, etc.) — the resolver only calls methods declared here.
 */
interface BloxyIdentity
{
    public function getKey();

    public function getMorphClass(): string;

    public function hasRole(string $name): bool;

    public function can($abilities, $arguments = []);
}
