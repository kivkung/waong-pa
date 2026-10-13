<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 * @property list<int> $available_member_ids
 */
class RoundCandidate extends Model
{
    public $timestamps = false;

    protected $fillable = ['start_at', 'end_at', 'available_count', 'professor_count', 'available_member_ids', 'is_winner'];

    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime', 'available_member_ids' => 'array', 'is_winner' => 'boolean'];
    }

    /** @return HasMany<RoundVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(RoundVote::class);
    }

    public function weightedScore(Collection $weights): float
    {
        return (float) collect($this->available_member_ids)
            ->sum(fn(int $id): float => (float) ($weights[$id] ?? 0));
    }
}
