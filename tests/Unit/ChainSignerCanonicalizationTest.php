<?php

declare(strict_types=1);

/*
 * Proof test for B-2 from synthesis_2026-05-11.md.
 *
 * Expected to FAIL until canonicalize() normalizes cast-aware attributes.
 *
 * Bug mechanism (see ChainSigner::canonicalize at packages/core-php/src/Audit/ChainSigner.php):
 *
 *   - At sign-time, $row->getAttributes() returns the freshly-encrypted
 *     ciphertext for `changes` (re-serialized via the cast's set() call).
 *     Because Crypt::encryptString() is non-deterministic (random IV), a
 *     subsequent call would produce a different ciphertext for the same
 *     plaintext. The signature is computed against ciphertext-A.
 *
 *   - At verify-time, the row is reloaded from the DB. $row->getAttributes()
 *     returns ciphertext-B — the one that was actually stored. The verifier
 *     computes its expected signature against ciphertext-B, which never
 *     matches the original signature.
 *
 *   - `meta` is handled asymmetrically (lines 157-170): the verify path
 *     decode→ksort→re-encode-to-string, but the sign path only ksorts in
 *     place without re-encoding. With ['z'=>1,'a'=>2] passed as a JSON
 *     string, verify returns `{"a":2,"z":1}`; with the same logical value
 *     as a raw PHP array, sign returns the array (json_encoded later as
 *     `{"a":2,"z":1}`). These happen to match for shallow inputs but the
 *     two code paths are not symmetric — this test pins the asymmetry.
 *
 * The test is environment-light: it bypasses Eloquent casts entirely by
 * stuffing $attributes directly, so it does not require a DB, the cast
 * stack, or the Bloxy service provider. It exercises canonicalize()
 * against the two attribute shapes that legitimately exist at sign-time
 * vs verify-time for the same logical row.
 */

use Bloxy\Core\Audit\AuditLog;
use Bloxy\Core\Audit\ChainSigner;
use Illuminate\Support\Facades\Crypt;

/**
 * Build an AuditLog model and write the raw attributes array directly,
 * skipping setAttribute() / casts. This lets us simulate the precise
 * shape that getAttributes() returns at sign-time vs verify-time.
 *
 * @param  array<string, mixed>  $attrs
 */
function makeAuditLogWithRawAttributes(array $attrs): AuditLog
{
    $row = new AuditLog();
    // Bypass Eloquent's setAttribute() / cast pipeline. The protected
    // $attributes property is what getAttributes() ultimately exposes
    // (after cast cache merge — but there's no cache here).
    $reflection = new ReflectionClass($row);
    $prop = $reflection->getProperty('attributes');
    $prop->setAccessible(true);
    $prop->setValue($row, $attrs);
    return $row;
}

it('canonicalizes the same logical row differently at sign-time vs verify-time', function () {
    $signer = new ChainSigner();

    // Sign-time shape: `changes` is the raw PHP array as Eloquent would
    // hold it pre-cast-serialization, and `meta` is the raw PHP array.
    $signTimeRow = makeAuditLogWithRawAttributes([
        'happened_at' => '2026-05-11 12:00:00',
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'created',
        'subject_type' => 'App\\Models\\Foo',
        'subject_id' => '42',
        'changes' => ['before' => null, 'after' => ['x' => 1]],
        'meta' => ['z' => 1, 'a' => 2],
    ]);

    // Verify-time shape: `changes` is the encrypted ciphertext string the
    // DB returned, and `meta` is the stored JSON string. (The cast's
    // get() runs on attribute read, not on getAttributes() — so the raw
    // stored representation is what canonicalize sees.)
    //
    // Use a real Crypt::encryptString of the SAME plaintext the sign-time
    // row holds so the canonicalize() fix has something it can actually
    // decrypt. The random IV in Crypt makes the ciphertext bytes differ
    // from any previous encrypt — that's exactly why the bug exists.
    $changesPlaintext = json_encode(['before' => null, 'after' => ['x' => 1]]);
    $verifyTimeRow = makeAuditLogWithRawAttributes([
        'happened_at' => '2026-05-11 12:00:00',
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'created',
        'subject_type' => 'App\\Models\\Foo',
        'subject_id' => '42',
        'changes' => Crypt::encryptString($changesPlaintext),
        'meta' => json_encode(['z' => 1, 'a' => 2]),
    ]);

    $signTimeCanonical = $signer->canonicalize($signTimeRow);
    $verifyTimeCanonical = $signer->canonicalize($verifyTimeRow);

    // If canonicalize() were cast-aware, both representations of the
    // same logical row would yield identical canonical bytes. Today
    // they don't — that's bug B-2.
    expect($signTimeCanonical)->toBe($verifyTimeCanonical);
});
