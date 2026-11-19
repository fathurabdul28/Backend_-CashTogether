<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $guarded = [];
    protected $casts = ['invite_expires_at' => 'datetime'];

    public function members() { return $this->hasMany(HouseholdMember::class); }
}
