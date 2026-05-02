<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('exits 0 with OK when every state-changing route has bloxy.audit', function () {
    Route::middleware(['bloxy.audit'])->post('/covered', fn () => 'ok');
    Route::middleware(['bloxy.audit'])->put('/also-covered', fn () => 'ok');

    $this->artisan('bloxy:audit-coverage')
        ->expectsOutputToContain('OK')
        ->assertExitCode(0);
});

it('exits non-zero and lists routes missing the audit middleware', function () {
    Route::middleware(['bloxy.audit'])->post('/covered', fn () => 'ok');
    Route::post('/uncovered', fn () => 'ok');
    Route::patch('/also-uncovered', fn () => 'ok');

    $this->artisan('bloxy:audit-coverage')
        ->expectsOutputToContain('Found 2 uncovered')
        ->expectsOutputToContain('POST uncovered')
        ->expectsOutputToContain('PATCH also-uncovered')
        ->assertExitCode(1);
});

it('ignores GET/HEAD/OPTIONS routes', function () {
    Route::get('/page', fn () => 'ok');
    Route::middleware(['bloxy.audit'])->post('/covered', fn () => 'ok');

    $this->artisan('bloxy:audit-coverage')->assertExitCode(0);
});

it('honors bloxy.audit.coverage_excludes patterns', function () {
    Route::middleware(['bloxy.audit'])->post('/covered', fn () => 'ok');
    Route::post('/excluded/leave-me-alone', fn () => 'ok');
    config()->set('bloxy.audit.coverage_excludes', ['#^storage/#', '#^excluded/#']);

    $this->artisan('bloxy:audit-coverage')
        ->expectsOutputToContain('OK')
        ->assertExitCode(0);
});

it('exposes assertRouteHasAudit and assertNoUncoveredStateChangingRoutes via trait', function () {
    Route::middleware(['bloxy.audit'])->post('/named-and-covered', fn () => 'ok')->name('covered.route');

    $reflectionClass = new ReflectionClass(\Bloxy\Core\Testing\AssertsAuditCoverage::class);
    expect($reflectionClass->isTrait())->toBeTrue();
    expect($reflectionClass->hasMethod('assertRouteHasAudit'))->toBeTrue();
    expect($reflectionClass->hasMethod('assertNoUncoveredStateChangingRoutes'))->toBeTrue();
});
