<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class success_b2c_transactions extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'transaction_id',
        'originator_conversation_id',
        'result_code',
        'result_desc',
        // 'amount',
        // 'receiver_name',
        // 'transaction_date',
    ];
}
