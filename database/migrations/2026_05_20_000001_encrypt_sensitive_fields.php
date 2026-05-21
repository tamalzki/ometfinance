<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypt sensitive PII columns at rest.
 *
 * Steps:
 *  1. Widen columns to TEXT so they can hold the longer base64 ciphertext.
 *  2. Read each existing row via raw query, encrypt any plaintext values,
 *     and write them back via raw query — bypassing the new `encrypted` casts.
 *
 * Idempotent: each value is attempted as decryption first; if that succeeds
 * the value is already encrypted and is skipped. Safe to run multiple times.
 */
return new class extends Migration
{
    /**
     * Columns we encrypt, keyed by table name.
     */
    private array $targets = [
        'bank_accounts'        => ['account_number'],
        'project_collections'  => ['reference', 'notes'],
        'project_expenses'     => ['vendor_ref', 'notes'],
        'transfers'            => ['memo', 'reason'],
        'ledger_entries'       => ['notes'],
    ];

    public function up(): void
    {
        // 1. Widen any string-typed columns so they can hold ciphertext.
        //    Notes columns are already TEXT in the original migrations; the
        //    short varchars are: account_number, reference, vendor_ref, memo.
        //    Using raw ALTER to avoid the doctrine/dbal dependency.
        $alters = [
            'ALTER TABLE bank_accounts MODIFY account_number TEXT NULL',
            'ALTER TABLE project_collections MODIFY reference TEXT NULL',
            'ALTER TABLE project_expenses MODIFY vendor_ref TEXT NULL',
            'ALTER TABLE transfers MODIFY memo TEXT NULL',
        ];
        foreach ($alters as $sql) {
            DB::statement($sql);
        }

        // 2. Encrypt existing plaintext values in-place.
        foreach ($this->targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->select(array_merge(['id'], $columns))->get();
            foreach ($rows as $row) {
                $updates = [];
                foreach ($columns as $col) {
                    $value = $row->{$col} ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    // Already encrypted? Skip.
                    if ($this->isEncrypted($value)) {
                        continue;
                    }
                    $updates[$col] = Crypt::encryptString((string) $value);
                }
                if (! empty($updates)) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        }
    }

    public function down(): void
    {
        // 1. Decrypt any encrypted values back to plaintext.
        foreach ($this->targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->select(array_merge(['id'], $columns))->get();
            foreach ($rows as $row) {
                $updates = [];
                foreach ($columns as $col) {
                    $value = $row->{$col} ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if (! $this->isEncrypted($value)) {
                        continue;
                    }
                    try {
                        $updates[$col] = Crypt::decryptString($value);
                    } catch (\Throwable $e) {
                        // Skip values we cannot decrypt with the current key.
                    }
                }
                if (! empty($updates)) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        }

        // 2. Narrow columns back to their original widths.
        $alters = [
            'ALTER TABLE bank_accounts MODIFY account_number VARCHAR(255) NULL',
            'ALTER TABLE project_collections MODIFY reference VARCHAR(100) NULL',
            'ALTER TABLE project_expenses MODIFY vendor_ref VARCHAR(100) NULL',
            'ALTER TABLE transfers MODIFY memo VARCHAR(255) NULL',
        ];
        foreach ($alters as $sql) {
            DB::statement($sql);
        }
    }

    /**
     * Detects whether a string is already a Laravel-encrypted payload by
     * trying to decrypt it. Cheap probe — encryption failures throw.
     */
    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
