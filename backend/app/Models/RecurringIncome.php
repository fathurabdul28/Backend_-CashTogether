<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringIncome extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'amount' => 'integer', 'day' => 'integer'];
}
