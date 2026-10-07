<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoundMemberBusyPeriod extends Model
{
    public $timestamps = false;

    protected $fillable = ['start_at', 'end_at'];

    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime'];
    }
}
