<?php

declare(strict_types=1);

use Bloxy\Core\Audit\AuditLog;
use Bloxy\Core\Audit\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('test_audited_models', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->integer('count')->default(0);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_audited_models');
});

it('writes a created entry on insert', function () {
    $m = TestAuditedModel::create(['title' => 'hello', 'count' => 1]);

    $entries = AuditLog::query()
        ->where('subject_type', TestAuditedModel::class)
        ->where('subject_id', (string) $m->id)
        ->get();

    expect($entries)->toHaveCount(1);
    expect($entries->first()->action)->toBe('created');
    expect($entries->first()->changes)->toMatchArray([
        'before' => null,
    ]);
    expect($entries->first()->changes['after'])->toMatchArray([
        'title' => 'hello',
        'count' => 1,
    ]);
});

it('writes an updated entry with before/after diff on update', function () {
    $m = TestAuditedModel::create(['title' => 'old', 'count' => 0]);
    AuditLog::query()->delete();   // ignore the create entry for this test

    $m->update(['title' => 'new']);

    $entries = AuditLog::query()->get();
    expect($entries)->toHaveCount(1);
    expect($entries->first()->action)->toBe('updated');
    expect($entries->first()->changes)->toBe([
        'before' => ['title' => 'old'],
        'after' => ['title' => 'new'],
    ]);
});

it('writes a deleted entry on delete', function () {
    $m = TestAuditedModel::create(['title' => 'doomed']);
    AuditLog::query()->delete();   // ignore the create entry

    $deletedAttributes = $m->getAttributes();
    $m->delete();

    $entries = AuditLog::query()->get();
    expect($entries)->toHaveCount(1);
    expect($entries->first()->action)->toBe('deleted');
    expect($entries->first()->changes['before'])->toBe($deletedAttributes);
    expect($entries->first()->changes['after'])->toBeNull();
});

it('records the actor when a user is authenticated', function () {
    $user = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users_stub';
        public $incrementing = false;
        protected $keyType = 'string';
        public function getKey(): string { return '99'; }
        public function getMorphClass(): string { return 'user'; }
    };
    auth()->setUser($user);

    TestAuditedModel::create(['title' => 'auth-test']);

    $entry = AuditLog::query()->first();
    expect($entry->actor_type)->toBe('user');
    expect($entry->actor_id)->toBe('99');
});

it('records null actor when nobody is authenticated', function () {
    auth()->logout();

    TestAuditedModel::create(['title' => 'system-event']);

    $entry = AuditLog::query()->first();
    expect($entry->actor_type)->toBeNull();
    expect($entry->actor_id)->toBeNull();
});

it('records the request_id when set on the current request attributes', function () {
    request()->attributes->set('audit.request_id', '550e8400-e29b-41d4-a716-446655440000');

    TestAuditedModel::create(['title' => 'with-request-id']);

    $entry = AuditLog::query()->first();
    expect($entry->request_id)->toBe('550e8400-e29b-41d4-a716-446655440000');

    request()->attributes->remove('audit.request_id');
});

it('records a read-access entry on explicit recordRead()', function () {
    $m = TestAuditedModel::create(['title' => 'private']);
    AuditLog::query()->delete();   // ignore the create entry

    $m->recordRead(['reason' => 'user-clicked-detail']);

    $entries = AuditLog::query()->get();
    expect($entries)->toHaveCount(1);
    expect($entries->first()->action)->toBe('read');
    expect($entries->first()->changes)->toBeNull();
    expect($entries->first()->meta)->toBe(['reason' => 'user-clicked-detail']);
    expect($entries->first()->subject_type)->toBe(TestAuditedModel::class);
    expect($entries->first()->subject_id)->toBe((string) $m->id);
});

it('recordRead() returns the AuditLog instance', function () {
    $m = TestAuditedModel::create(['title' => 'private']);
    AuditLog::query()->delete();

    $log = $m->recordRead();

    expect($log)->toBeInstanceOf(AuditLog::class);
    expect($log->action)->toBe('read');
});

class TestAuditedModel extends Model
{
    use HasAudit;

    protected $table = 'test_audited_models';
    protected $guarded = [];
    public $timestamps = true;
}
