<?php

namespace App\Models\Waongpa;

use Illuminate\Database\Eloquent\Model;

class RoundResult extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['finalized_at' => 'immutable_datetime'];
    }

    public function round()
    {
        return $this->belongsTo(MeetingRound::class, 'round_id');
    }

    public function slot()
    {
        return $this->belongsTo(TimeSlot::class, 'time_slot_id');
    }
}
