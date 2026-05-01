<?php

declare(strict_types=1);

use Bloxy\Core\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('test_encrypted_string_models', function (Blueprint $table) {
        $table->id();
        $table->text('secret_value')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_encrypted_string_models');
});

it('encrypts on save and decrypts on retrieve', function () {
    $model = new TestEncryptedStringModel();
    $model->secret_value = 'hello-world';
    $model->save();

    $rawColumn = \Illuminate\Support\Facades\DB::table('test_encrypted_string_models')
        ->where('id', $model->id)
        ->value('secret_value');

    expect($rawColumn)->not->toBe('hello-world');
    expect($rawColumn)->toBeString();
    expect(strlen($rawColumn))->toBeGreaterThan(20);

    $reloaded = TestEncryptedStringModel::find($model->id);
    expect($reloaded->secret_value)->toBe('hello-world');
});

it('round-trips null', function () {
    $model = new TestEncryptedStringModel();
    $model->secret_value = null;
    $model->save();

    $reloaded = TestEncryptedStringModel::find($model->id);
    expect($reloaded->secret_value)->toBeNull();
});

it('handles empty string', function () {
    $model = new TestEncryptedStringModel();
    $model->secret_value = '';
    $model->save();

    $reloaded = TestEncryptedStringModel::find($model->id);
    expect($reloaded->secret_value)->toBe('');
});

class TestEncryptedStringModel extends Model
{
    protected $table = 'test_encrypted_string_models';
    protected $guarded = [];
    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'secret_value' => EncryptedString::class,
        ];
    }
}
