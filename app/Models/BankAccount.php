<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use Auditable;

    protected $fillable = [
        'entity_id',
        'name',
        'bank_name',
        'account_number',
        'opening_balance',
        'is_active',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'account_number'  => 'encrypted',
        // Numeric columns stored as encrypted strings; cast to float at use-site
        // for arithmetic. `opening_balance` decimal/numeric semantics are
        // preserved by string-to-float coercion when summed/added.
        'opening_balance' => 'encrypted',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->orderBy('date')->orderBy('id');
    }

    public function currentBalance(): float
    {
        // Encrypted columns — aggregation handled in PHP. Lazy-loads the
        // ledgerEntries collection if it isn't already eager-loaded; once
        // loaded, the cast decrypts amount_in/amount_out per row.
        $entries = $this->ledgerEntries;

        $running = (float) $this->opening_balance;

        foreach ($entries as $e) {
            $running += (float) $e->amount_in - (float) $e->amount_out;
        }

        return $running;
    }

    public function maskedAccountNumber(): string
    {
        if (! $this->account_number) {
            return '—';
        }
        $num = (string) $this->account_number;
        if (strlen($num) <= 4) {
            return $num;
        }
        return '•••• ' . substr($num, -4);
    }
}
