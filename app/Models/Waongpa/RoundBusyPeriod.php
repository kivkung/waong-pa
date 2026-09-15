<?php

namespace App\Models\Waongpa;

use Illuminate\Database\Eloquent\Model;

class RoundBusyPeriod extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    public function participant()
    {
        return $this->belongsTo(RoundParticipant::class, 'participant_id');
    }

    public function sourceEvent()
    {
        return $this->belongsTo(PersonalEvent::class, 'source_event_id');
    }
}
