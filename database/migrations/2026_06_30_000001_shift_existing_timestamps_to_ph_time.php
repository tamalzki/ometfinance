<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time data correction: the app ran with app.timezone=UTC until now, so
 * every existing created_at/updated_at/approved_at/etc. was written in true
 * UTC. Now that app.timezone=Asia/Manila, shift those historical values
 * +8 hours so old records display the same correct PH wall-clock time that
 * new records will get going forward.
 *
 * Deliberately excludes expiry/security-sensitive columns (admin_invites,
 * password_resets, personal_access_tokens, failed_jobs) — shifting those
 * risks expiry-window edge cases for near-zero display value.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string}> */
    private array $columns = [
        ['audit_logs', 'created_at'],

        ['bank_accounts', 'created_at'],
        ['bank_accounts', 'updated_at'],

        ['entities', 'created_at'],
        ['entities', 'updated_at'],

        ['invoices', 'created_at'],
        ['invoices', 'updated_at'],

        ['ledger_entries', 'created_at'],
        ['ledger_entries', 'updated_at'],

        ['payees', 'created_at'],
        ['payees', 'updated_at'],

        ['project_allocation_lines', 'created_at'],
        ['project_allocation_lines', 'updated_at'],

        ['project_categories', 'created_at'],
        ['project_categories', 'updated_at'],

        ['project_collections', 'created_at'],
        ['project_collections', 'updated_at'],

        ['project_expenses', 'created_at'],
        ['project_expenses', 'updated_at'],

        ['projects', 'created_at'],
        ['projects', 'updated_at'],

        ['purchase_orders', 'created_at'],
        ['purchase_orders', 'updated_at'],

        ['transactions', 'created_at'],
        ['transactions', 'updated_at'],

        ['transfers', 'created_at'],
        ['transfers', 'updated_at'],

        ['users', 'created_at'],
        ['users', 'updated_at'],
        ['users', 'email_verified_at'],

        ['voucher_attachments', 'created_at'],
        ['voucher_attachments', 'updated_at'],

        ['voucher_entries', 'created_at'],
        ['voucher_entries', 'updated_at'],

        ['voucher_payments', 'created_at'],
        ['voucher_payments', 'updated_at'],

        ['voucher_requests', 'created_at'],
        ['voucher_requests', 'updated_at'],
        ['voucher_requests', 'reviewed_at'],

        ['vouchers', 'created_at'],
        ['vouchers', 'updated_at'],
        ['vouchers', 'approved_at'],
        ['vouchers', 'deleted_at'],
    ];

    public function up(): void
    {
        foreach ($this->columns as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            DB::statement("UPDATE `{$table}` SET `{$column}` = DATE_ADD(`{$column}`, INTERVAL 8 HOUR) WHERE `{$column}` IS NOT NULL");
        }
    }

    public function down(): void
    {
        foreach ($this->columns as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            DB::statement("UPDATE `{$table}` SET `{$column}` = DATE_SUB(`{$column}`, INTERVAL 8 HOUR) WHERE `{$column}` IS NOT NULL");
        }
    }
};
