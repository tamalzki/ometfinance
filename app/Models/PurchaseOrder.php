<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'po_number',
        'supplier_name',
        'amount',
        'order_date',
        'status',
    ];
}
