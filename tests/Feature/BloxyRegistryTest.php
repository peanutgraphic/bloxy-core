<?php

declare(strict_types=1);

use Bloxy\Core\Bloxy;

afterEach(function () {
    Bloxy::reset();
});

it('useUserModel emits a deprecation notice but does not error', function () {
    $errors = [];
    set_error_handler(function ($severity, $message) use (&$errors) {
        $errors[] = ['severity' => $severity, 'message' => $message];
        return true;
    }, E_USER_DEPRECATED);

    Bloxy::useUserModel(\stdClass::class);

    restore_error_handler();

    expect($errors)->toHaveCount(1);
    expect($errors[0]['message'])->toContain('deprecated');
});

it('userModel returns the auth.providers.users.model value', function () {
    config()->set('auth.providers.users.model', 'App\\Models\\User');

    @$result = Bloxy::userModel();
    expect($result)->toBe('App\\Models\\User');
});
