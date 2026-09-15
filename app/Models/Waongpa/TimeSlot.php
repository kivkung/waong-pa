<?php

namespace App\Models\Waongpa;

use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'weighted_score' => 'decimal:2', 'calculated_at' => 'immutable_datetime'];
    }

    public function round()
    {
        return $this->belongsTo(MeetingRound::class, 'round_id');
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
