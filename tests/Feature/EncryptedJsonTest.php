<?php

declare(strict_types=1);

use Bloxy\Core\Casts\EncryptedJson;
use Bloxy\Core\Casts\ServerEncryptedJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('test_encrypted_json_models', function (Blueprint $table) {
        $table->id();
        $table->text('payload')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_encrypted_json_models');
});

it('encrypts and decrypts a flat array', function () {
    $model = new TestEncryptedJsonModel();
    $model->payload = ['a' => 1, 'b' => 'two'];
    $model->save();

    $reloaded = TestEncryptedJsonModel::find($model->id);
    expect($reloaded->payload)->toBe(['a' => 1, 'b' => 'two']);
});

it('encrypts and decrypts a deeply nested array', function () {
    $model = new TestEncryptedJsonModel();
    $model->payload = [
        'user' => ['name' => 'nat', 'roles' => ['operator', 'agent']],
        'meta' => ['flags' => ['x' => true, 'y' => false]],
    ];
    $model->save();

    $reloaded = TestEncryptedJsonModel::find($model->id);
    expect($reloaded->payload)->toBe([
        'user' => ['name' => 'nat', 'roles' => ['operator', 'agent']],
        'meta' => ['flags' => ['x' => true, 'y' => false]],
    ]);
});

it('round-trips null', function () {
    $model = new TestEncryptedJsonModel();
    $model->payload = null;
    $model->save();

    $reloaded = TestEncryptedJsonModel::find($model->id);
    expect($reloaded->payload)->toBeNull();
});

it('round-trips an empty array', function () {
    $model = new TestEncryptedJsonModel();
    $model->payload = [];
    $model->save();

    $reloaded = TestEncryptedJsonModel::find($model->id);
    expect($reloaded->payload)->toBe([]);
});

it('writes ciphertext to the column (not plaintext JSON)', function () {
    $model = new TestEncryptedJsonModel();
    $model->payload = ['secret' => 'value'];
    $model->save();

    $rawColumn = \Illuminate\Support\Facades\DB::table('test_encrypted_json_models')
        ->where('id', $model->id)
        ->value('payload');

    expect($rawColumn)->not->toContain('secret');
    expect($rawColumn)->not->toContain('value');
});

class TestEncryptedJsonModel extends Model
{
    protected $table = 'test_encrypted_json_models';
    protected $guarded = [];
    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'payload' => EncryptedJson::class,
        ];
    }
}

// --- ServerEncryptedJson (canonical) ---

it('ServerEncryptedJson round-trips an associative array', function () {
    $original = ['name' => 'nat', 'score' => 42, 'active' => true];

    $model = new TestServerEncryptedJsonModel();
    $model->payload = $original;
    $model->save();

    $rawColumn = \Illuminate\Support\Facades\DB::table('test_encrypted_json_models')
        ->where('id', $model->id)
        ->value('payload');

    expect($rawColumn)->not->toContain('nat');
    expect($rawColumn)->not->toContain('score');

    $reloaded = TestServerEncryptedJsonModel::find($model->id);
    expect($reloaded->payload)->toBe($original);
});

it('EncryptedJson is an instanceof ServerEncryptedJson', function () {
    expect(new EncryptedJson())->toBeInstanceOf(ServerEncryptedJson::class);
});

class TestServerEncryptedJsonModel extends Model
{
    protected $table = 'test_encrypted_json_models';
    protected $guarded = [];
    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'payload' => ServerEncryptedJson::class,
        ];
    }
}
