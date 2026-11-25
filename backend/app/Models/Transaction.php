<?php

namespace App\Models;

use App\Services\SavingLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['occurred_at' => 'datetime', 'amount' => 'integer'];

    protected static function booted(): void
    {
        static::saved(fn (self $tx) => SavingLink::sync($tx));
        static::deleted(fn (self $tx) => SavingLink::sync($tx));
    }
}
