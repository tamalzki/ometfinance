<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    use Auditable;

    protected $fillable = ['name', 'slug', 'color', 'sort_order'];

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class)->orderBy('name');
    }

    public function totalBalance(): float
    {
        return $this->bankAccounts->sum(fn ($a) => $a->currentBalance());
    }
}
