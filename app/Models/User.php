<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'source',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isCfo(): bool        { return $this->role === 'cfo'; }
    public function isAccounting(): bool { return $this->role === 'accounting'; }

    /** Accounting Staff are locked to the office (Main/BGC) they were invited for; everyone else picks freely. */
    public function lockedSource(): ?string
    {
        return $this->isAccounting() ? ($this->source ?: 'mindanao') : null;
    }
}
