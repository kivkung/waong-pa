<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** @return BelongsTo<BusySlotRecurrenceRule, $this> */
    public function recurrenceRule(): BelongsTo
    {
        return $this->belongsTo(BusySlotRecurrenceRule::class);
    }

    /** @param Builder<UserBusySlot> $query
     * @return Builder<UserBusySlot>
     */
    public function scopeOverlappingRange(Builder $query, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): Builder
    {
        // กิจกรรมซ้ำอาจเริ่มก่อนช่วงที่ดู จึงต้องนำต้นฉบับมาขยายกฎด้วย
        return $query->where('start_at', '<', $rangeEnd)
            ->where(function (Builder $query) use ($rangeStart) {
                $query->where('end_at', '>', $rangeStart)
                    ->orWhereNotNull('recurrence_rule_id');
            });
    }

    /** @return list<array{start_at: CarbonImmutable, end_at: CarbonImmutable}> */
    public function occurrencesBetween(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        if ($rangeStart >= $rangeEnd) {
            return [];
        }
        if ($this->start_at >= $rangeEnd) {
            return [];
        }

        $rule = $this->recurrenceRule;
        if ($rule === null) {
            if ($this->end_at > $rangeStart) {
                return [['start_at' => $this->start_at, 'end_at' => $this->end_at]];
            }

            return [];
        }

        $durationSeconds = (int) $this->start_at->diffInSeconds($this->end_at);
        // เผื่อกิจกรรมที่เริ่มก่อนช่วงค้นหา แต่ยังไม่จบ
        $earliestStart = $rangeStart->subSeconds($durationSeconds);
        $firstIndex = $this->firstOccurrenceIndex($earliestStart, $rule->frequency);
        $periods = [];

        for ($index = $firstIndex; $index < $rule->occurrence_count; $index++) {
            if ($rule->frequency === 'weekly') {
                $start = $this->start_at->addWeeks($index);
            } else {
                // คำนวณจากต้นฉบับเสมอ: 31 ม.ค. → 28 ก.พ. → 31 มี.ค.
                $start = $this->start_at->addMonthsNoOverflow($index);
            }
            if ($start >= $rangeEnd) {
                break;
            }

            $end = $start->addSeconds($durationSeconds);
            if ($end > $rangeStart) {
                $periods[] = ['start_at' => $start, 'end_at' => $end];
            }
        }

        return $periods;
    }

    private function firstOccurrenceIndex(CarbonImmutable $earliestStart, string $frequency): int
    {
        if ($earliestStart <= $this->start_at) {
            return 0;
        }

        // ข้ามครั้งเก่า ๆ ที่อยู่นอกช่วงค้นหา เพื่อไม่วนหลายล้านครั้ง
        if ($frequency === 'weekly') {
            $daysPassed = $this->start_at->diffInDays($earliestStart);
            $index = (int) ($daysPassed / 7);
        } else {
            $yearsPassed = $earliestStart->year - $this->start_at->year;
            $monthsPassed = $earliestStart->month - $this->start_at->month;
            $index = ($yearsPassed * 12) + $monthsPassed;
        }

        // เริ่มก่อนหนึ่งครั้ง เผื่อวันสิ้นเดือนหรือกิจกรรมที่คร่อมวัน
        $index = $index - 1;
        if ($index < 0) {
            return 0;
        }

        return $index;
    }

    /** @return list<array{start_at: CarbonImmutable, end_at: CarbonImmutable}> */
    public static function windows(MeetingRound $round): array
    {
        $windows = [];
        $day = $round->search_start_date;
        while ($day <= $round->search_end_date) {
            $windows[] = [
                'start_at' => $day->setTimeFromTimeString($round->daily_start_time),
                'end_at' => $day->setTimeFromTimeString($round->daily_end_time),
            ];
            $day = $day->addDay();
        }

        return $windows;
    }

    /** @param Builder<UserBusySlot> $query
     * @return Builder<UserBusySlot>
     */
    public function scopeOverlappingRound(Builder $query, MeetingRound $round): Builder
    {
        return $query->overlappingRange(
            $round->search_start_date->setTimeFromTimeString($round->daily_start_time),
            $round->search_end_date->setTimeFromTimeString($round->daily_end_time)
        );
    }

    /** Both preview and snapshot use this same overlap query and clipping.
     * @return list<array{start_at: CarbonImmutable, end_at: CarbonImmutable}>
     */
    public static function periodsFor(MeetingRound $round, int $userId): array
    {
        $periods = [];
        $windows = self::windows($round);
        if ($windows === []) {
            return [];
        }
        $rangeStart = $windows[0]['start_at'];
        $rangeEnd = $windows[count($windows) - 1]['end_at'];
        $activities = self::query()->where('user_id', $userId)
            ->overlappingRound($round)->with('recurrenceRule')->get();
        foreach ($activities as $activity) {
            foreach ($activity->occurrencesBetween($rangeStart, $rangeEnd) as $occurrence) {
                foreach ($windows as $window) {
                    // ตัดช่วงไม่ว่างให้เหลือเฉพาะส่วนที่อยู่ในกรอบค้นหา
                    $start = $occurrence['start_at'];
                    if ($start < $window['start_at']) {
                        $start = $window['start_at'];
                    }
                    $end = $occurrence['end_at'];
                    if ($end > $window['end_at']) {
                        $end = $window['end_at'];
                    }
                    if ($start < $end) {
                        $periods[] = ['start_at' => $start, 'end_at' => $end];
                    }
                }
            }
        }
        usort($periods, function (array $first, array $second): int {
            if ($first['start_at'] < $second['start_at']) {
                return -1;
            }
            if ($first['start_at'] > $second['start_at']) {
                return 1;
            }

            return 0;
        });

        return $periods;
    }
}
