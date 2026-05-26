<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'raw_message_id',
        'telegram_user_id',
        'type',
        'amount',
        'category',
        'note',
        'transaction_date'
    ];
}
