<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts all numeric financial columns (amounts + opening balances) and
 * the LedgerEntry description.
 *
 * Side-effect: DB-level aggregations (SUM, AVG, ORDER BY, WHERE <>) on these
 * columns no longer work. Every site in the codebase has been refactored to
 * load Eloquent collections and aggregate in PHP through the `encrypted`
 * cast. See controllers/models for "// Encrypted column" comments.
 *
 * Idempotent — re-running encrypts only still-plaintext values.
 */
return new class extends Migration
{
    /**
     * Table => list of columns to convert.
     */
    private array $targets = [
        'bank_accounts'       => ['opening_balance'],
        'ledger_entries'      => ['amount_in', 'amount_out', 'description'],
        'project_collections' => ['amount'],
        'project_expenses'    => ['amount'],
        'transfers'           => ['amount'],
    ];

    /**
     * Numeric columns we'll restore to DECIMAL(15,2) on rollback.
     */
    private array $numericColumns = [
        'bank_accounts.opening_balance',
        'ledger_entries.amount_in',
        'ledger_entries.amount_out',
        'project_collections.amount',
        'project_expenses.amount',
        'transfers.amount',
    ];

    public function up(): void
    {
        // 1. Widen columns to TEXT to fit ciphertext.
        $alters = [
            'ALTER TABLE bank_accounts MODIFY opening_balance TEXT NULL',
            'ALTER TABLE ledger_entries MODIFY amount_in TEXT NULL',
            'ALTER TABLE ledger_entries MODIFY amount_out TEXT NULL',
            'ALTER TABLE ledger_entries MODIFY description TEXT NOT NULL',
            'ALTER TABLE project_collections MODIFY amount TEXT NOT NULL',
            'ALTER TABLE project_expenses MODIFY amount TEXT NOT NULL',
            'ALTER TABLE transfers MODIFY amount TEXT NOT NULL',
        ];
        foreach ($alters as $sql) {
            DB::statement($sql);
        }

        // 2. Encrypt existing plaintext values in place.
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
                    if ($this->isEncrypted($value)) {
                        continue;
                    }
                    // For numeric columns we store the *string* representation
                    // of the float to keep things deterministic on round-trip.
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
        // 1. Decrypt back to plaintext.
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
                        // Skip values we can no longer decrypt.
                    }
                }
                if (! empty($updates)) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        }

        // 2. Restore original column types.
        $alters = [
            'ALTER TABLE bank_accounts MODIFY opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0',
            'ALTER TABLE ledger_entries MODIFY amount_in DECIMAL(15,2) NULL',
            'ALTER TABLE ledger_entries MODIFY amount_out DECIMAL(15,2) NULL',
            'ALTER TABLE ledger_entries MODIFY description VARCHAR(255) NOT NULL',
            'ALTER TABLE project_collections MODIFY amount DECIMAL(15,2) NOT NULL',
            'ALTER TABLE project_expenses MODIFY amount DECIMAL(15,2) NOT NULL',
            'ALTER TABLE transfers MODIFY amount DECIMAL(15,2) NOT NULL',
        ];
        foreach ($alters as $sql) {
            DB::statement($sql);
        }
    }

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
