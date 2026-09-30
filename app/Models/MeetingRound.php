<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Carbon\CarbonImmutable|null $join_starts_at
 * @property \Carbon\CarbonImmutable|null $review_starts_at
 * @property \Carbon\CarbonImmutable|null $voting_starts_at
 * @property \Carbon\CarbonImmutable|null $final_starts_at
 * @property \Carbon\CarbonImmutable|null $cancelled_at
 */

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

    /** @return HasMany<RoundMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(RoundMember::class);
    }
    
}
