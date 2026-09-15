<?php

namespace App\Models\Waongpa;

use Illuminate\Database\Eloquent\Model;

class MeetingRound extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['round_no' => 'integer', 'version' => 'integer', 'duration_minutes' => 'integer', 'search_start_date' => 'immutable_date', 'search_end_date' => 'immutable_date', 'join_starts_at' => 'immutable_datetime', 'review_starts_at' => 'immutable_datetime', 'voting_starts_at' => 'immutable_datetime', 'final_starts_at' => 'immutable_datetime'];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function participants()
    {
        return $this->hasMany(RoundParticipant::class, 'round_id');
    }

    public function slots()
    {
        return $this->hasMany(TimeSlot::class, 'round_id');
    }

    public function result()
    {
        return $this->hasOne(RoundResult::class, 'round_id');
    }
}
