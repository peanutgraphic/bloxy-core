<?php

declare(strict_types=1);

use Bloxy\Core\Bloxy;

afterEach(function () {
    Bloxy::reset();
});

it('returns null when no user model has been registered', function () {
    expect(Bloxy::userModel())->toBeNull();
});

it('stores and returns the registered user model class', function () {
    Bloxy::useUserModel('App\\Models\\User');
    expect(Bloxy::userModel())->toBe('App\\Models\\User');
});

it('reset() clears the registered model', function () {
    Bloxy::useUserModel('App\\Models\\User');
    Bloxy::reset();
    expect(Bloxy::userModel())->toBeNull();
});
