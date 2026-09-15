<?php

namespace App\Models\Waongpa;

use Illuminate\Database\Eloquent\Model;

class RoundParticipant extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'schedule_copied_at' => 'immutable_datetime', 'schedule_confirmed_at' => 'immutable_datetime'];
    }

    public function round()
    {
        return $this->belongsTo(MeetingRound::class, 'round_id');
    }

    public function member()
    {
        return $this->belongsTo(RoomMember::class, 'room_member_id');
    }

    public function busyPeriods()
    {
        return $this->hasMany(RoundBusyPeriod::class, 'participant_id');
    }

    public function vote()
    {
        return $this->hasOne(Vote::class, 'participant_id');
    }
}
