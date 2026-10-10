<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $join_starts_at
 * @property CarbonImmutable|null $review_starts_at
 * @property CarbonImmutable|null $voting_starts_at
 * @property CarbonImmutable|null $final_starts_at
 * @property CarbonImmutable|null $cancelled_at
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
            'snapshot_taken_at' => 'immutable_datetime',
            'candidates_generated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return HasMany<RoundMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(RoundMember::class);
    }

    public function snapshotDueAt(): CarbonImmutable
    {
        return $this->review_starts_at->addMinutes(config('rounds.snapshot_delay_minutes'));
    }

    /** @return HasMany<RoundCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(RoundCandidate::class);
    }

    public function membershipLocked(): bool
    {
        return $this->status === 'active'
            && ($this->phase !== 'join' || now()->gte($this->review_starts_at));
    }
}
