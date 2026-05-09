<?php

declare(strict_types=1);

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir() . '/bloxy-bump-test-' . uniqid();
    mkdir($this->tmpDir);
    mkdir($this->tmpDir . '/scripts');
    mkdir($this->tmpDir . '/packages/core-js', 0755, true);
    mkdir($this->tmpDir . '/packages/crypto-js', 0755, true);
    mkdir($this->tmpDir . '/packages/passkey-js', 0755, true);
    mkdir($this->tmpDir . '/packages/tester-bridge-js', 0755, true);

    // Seed package.json files with old versions.
    $seed = function (string $relPath, string $name, string $version) {
        $path = $this->tmpDir . '/' . $relPath;
        file_put_contents($path, json_encode([
            'name' => $name,
            'version' => $version,
            'description' => 'unrelated field that must not change',
        ], JSON_PRETTY_PRINT) . "\n");
    };
    $seed('packages/core-js/package.json', '@peanutgraphic/bloxy-ui', '0.0.0');
    $seed('packages/crypto-js/package.json', '@peanutgraphic/bloxy-crypto', '0.1.0');
    $seed('packages/passkey-js/package.json', '@peanutgraphic/bloxy-passkey', '0.0.0');
    $seed('packages/tester-bridge-js/package.json', '@peanutgraphic/bloxy-tester-bridge', '0.0.0');

    // Copy the real script into the tmp dir.
    $repoRoot = realpath(__DIR__ . '/../../../../');
    copy($repoRoot . '/scripts/bump-version.sh', $this->tmpDir . '/scripts/bump-version.sh');
    chmod($this->tmpDir . '/scripts/bump-version.sh', 0755);
});

afterEach(function () {
    $rmrf = function ($path) use (&$rmrf) {
        if (is_dir($path)) {
            foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
                $rmrf($path . '/' . $entry);
            }
            rmdir($path);
        } else {
            @unlink($path);
        }
    };
    $rmrf($this->tmpDir);
});

it('updates all 4 JS package.json version fields to the target version', function () {
    $output = [];
    $code = 0;
    exec("cd {$this->tmpDir} && bash scripts/bump-version.sh 0.1.0 2>&1", $output, $code);

    expect($code)->toBe(0);
    expect(json_decode(file_get_contents($this->tmpDir . '/packages/core-js/package.json'), true)['version'])->toBe('0.1.0');
    expect(json_decode(file_get_contents($this->tmpDir . '/packages/crypto-js/package.json'), true)['version'])->toBe('0.1.0');
    expect(json_decode(file_get_contents($this->tmpDir . '/packages/passkey-js/package.json'), true)['version'])->toBe('0.1.0');
    expect(json_decode(file_get_contents($this->tmpDir . '/packages/tester-bridge-js/package.json'), true)['version'])->toBe('0.1.0');
});

it('does not modify other fields in package.json', function () {
    exec("cd {$this->tmpDir} && bash scripts/bump-version.sh 0.1.0 2>&1");

    $core = json_decode(file_get_contents($this->tmpDir . '/packages/core-js/package.json'), true);
    expect($core['name'])->toBe('@peanutgraphic/bloxy-ui');
    expect($core['description'])->toBe('unrelated field that must not change');
});

it('exits non-zero on invalid semver input', function () {
    $output = [];
    $code = 0;
    exec("cd {$this->tmpDir} && bash scripts/bump-version.sh not-a-version 2>&1", $output, $code);
    expect($code)->not->toBe(0);
});

it('exits non-zero with no arguments', function () {
    $output = [];
    $code = 0;
    exec("cd {$this->tmpDir} && bash scripts/bump-version.sh 2>&1", $output, $code);
    expect($code)->not->toBe(0);
});

it('accepts pre-release semver like 0.1.0-rc.1', function () {
    $output = [];
    $code = 0;
    exec("cd {$this->tmpDir} && bash scripts/bump-version.sh 0.1.0-rc.1 2>&1", $output, $code);
    expect($code)->toBe(0);
    expect(json_decode(file_get_contents($this->tmpDir . '/packages/core-js/package.json'), true)['version'])->toBe('0.1.0-rc.1');
});
