<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'description',
        'category',
        'amount',
        'transaction_date',
        'status',
        'reference_no',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
