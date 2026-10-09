<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 */
class UserBusySlot extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'start_at', 'end_at'];

    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime'];
    }

    /** @return list<array{start_at: CarbonImmutable, end_at: CarbonImmutable}> */
    public static function windows(MeetingRound $round): array
    {
        $windows = [];
        for ($day = $round->search_start_date; $day->lte($round->search_end_date); $day = $day->addDay()) {
            $windows[] = [
                'start_at' => $day->setTimeFromTimeString($round->daily_start_time),
                'end_at' => $day->setTimeFromTimeString($round->daily_end_time),
            ];
        }

        return $windows;
    }

    /** @param Builder<UserBusySlot> $query
     * @return Builder<UserBusySlot>
     */
    public function scopeOverlappingRound(Builder $query, MeetingRound $round): Builder
    {
        return $query->where(function (Builder $query) use ($round) {
            foreach (self::windows($round) as $window) {
                $query->orWhere(function (Builder $query) use ($window) {
                    $query->where('start_at', '<', $window['end_at'])
                        ->where('end_at', '>', $window['start_at']);
                });
            }
        });
    }

    /** Both preview and snapshot use this same overlap query and clipping.
     * @return list<array{start_at: CarbonImmutable, end_at: CarbonImmutable}>
     */
    public static function periodsFor(MeetingRound $round, int $userId): array
    {
        $periods = [];
        foreach (self::query()->where('user_id', $userId)->overlappingRound($round)->orderBy('start_at')->get() as $activity) {
            foreach (self::windows($round) as $window) {
                $start = $activity->start_at->max($window['start_at']);
                $end = $activity->end_at->min($window['end_at']);
                if ($start->lt($end)) {
                    $periods[] = ['start_at' => $start, 'end_at' => $end];
                }
            }
        }

        return $periods;
    }
}
