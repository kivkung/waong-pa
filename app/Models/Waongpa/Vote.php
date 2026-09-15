<?php

namespace App\Models\Waongpa;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }

    public function participant()
    {
        return $this->belongsTo(RoundParticipant::class, 'participant_id');
    }

    public function slot()
    {
        return $this->belongsTo(TimeSlot::class, 'time_slot_id');
    }
}
