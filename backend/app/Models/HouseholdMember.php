<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseholdMember extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = ['joined_at' => 'datetime'];

    public function household() { return $this->belongsTo(Household::class); }
    public function user() { return $this->belongsTo(User::class); }
}
