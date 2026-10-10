<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 */
class RoundMemberBusyPeriod extends Model
{
    public $timestamps = false;

    protected $fillable = ['start_at', 'end_at'];

    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime'];
    }
}
