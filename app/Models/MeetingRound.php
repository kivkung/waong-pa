<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRound extends Model
{
    public function casts(): array
    {
        return [
            'search_start_date' => 'immutable_date',
            'search_end_date' => 'immutable_date',
            'join_starts_at' => 'immutable_datetime',
            'review_starts_at' => 'immutable_datetime',
            'voting_starts_at' => 'immutable_datetime',
            'final_starts_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Room, $this> */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }
    
}
