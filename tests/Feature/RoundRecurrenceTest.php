<?php

use App\Actions\ProcessRecurringRounds;
use App\Models\MeetingRound;
use App\Models\Room;
use App\Models\RoundRecurrenceSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 08:00'));
    config(['rounds.snapshot_delay_minutes' => 0]);
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner)->post(route('rooms.store'), ['name' => 'Recurring room', 'visibility' => 'private']);
    $this->room = Room::query()->firstOrFail();
    $this->input = [
        'search_start_date' => '2026-10-14', 'search_end_date' => '2026-10-14',
        'daily_start_time' => '09:00', 'daily_end_time' => '12:00', 'duration_minutes' => 60,
        'professor_rule' => 'all',
        'join_starts_at' => '2026-10-13T09:00', 'review_starts_at' => '2026-10-13T10:00',
        'voting_starts_at' => '2026-10-13T11:00', 'final_starts_at' => '2026-10-13T12:00',
        'repeat_frequency' => 'weekly', 'repeat_count' => 3,
    ];
});

test('single rounds remain unchanged and creation exposes repeat fields', function () {
    $this->get(route('rooms.rounds.create', $this->room))->assertOk()->assertSee('สร้างรอบใหม่อัตโนมัติ')->assertSee('repeat_count');
    $this->post(route('rooms.rounds.store', $this->room), [...$this->input, 'repeat_frequency' => 'none'])->assertSessionHasNoErrors();
    expect(MeetingRound::query()->firstOrFail()->recurrence_schedule_id)->toBeNull();
    $this->assertDatabaseCount('round_recurrence_schedules', 0);
});

test('weekly scheduler creates separate empty rounds only when due and never duplicates on retry', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input)->assertSessionHasNoErrors();
    $first = $this->room->rounds()->firstOrFail();
    $schedule = $first->recurrenceSchedule;
    expect($schedule->next_run_at->format('Y-m-d H:i'))->toBe('2026-10-20 09:00');
    $member = $this->room->members()->firstOrFail();
    $first->members()->create(['room_member_id' => $member->id, 'role' => 'student', 'weight' => 1, 'joined_at' => now()]);
    $this->travelTo($first->final_starts_at);
    $this->artisan('rounds:process')->assertExitCode(0);
    expect($first->fresh()->status)->toBe('completed');
    expect($first->candidates()->count())->toBeGreaterThan(0);
    $this->assertDatabaseCount('meeting_rounds', 1);

    $this->travelTo(CarbonImmutable::parse('2026-10-20 08:59'));
    app(ProcessRecurringRounds::class)->handle($schedule->id);
    $this->assertDatabaseCount('meeting_rounds', 1);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00'));
    $this->artisan('rounds:process')->assertExitCode(0);
    $this->artisan('rounds:process')->assertExitCode(0);
    $this->assertDatabaseCount('meeting_rounds', 2);
    $second = $this->room->rounds()->orderByDesc('round_no')->firstOrFail();
    expect($second->round_no)->toBe(2);
    expect($second->recurrence_index)->toBe(1);
    expect($second->phase)->toBe('join');
    expect($second->snapshot_taken_at)->toBeNull();
    expect($second->candidates_generated_at)->toBeNull();
    expect($second->members()->count())->toBe(0);
    expect($second->candidates()->count())->toBe(0);
    expect($second->search_start_date->format('Y-m-d'))->toBe('2026-10-21');
    expect($second->voting_starts_at->format('Y-m-d H:i'))->toBe('2026-10-20 11:00');
    $this->get(route('rooms.rounds.show', [$this->room, $second]))->assertOk()->assertSee('แผนสร้างรอบอัตโนมัติ')->assertSee('หยุดสร้างรอบถัดไป');

    $this->travelTo(CarbonImmutable::parse('2026-10-27 09:00'));
    $this->artisan('rounds:process')->assertExitCode(0);
    $this->assertDatabaseCount('meeting_rounds', 3);
    expect($schedule->fresh()->is_active)->toBeFalse();
    expect($schedule->fresh()->next_run_at)->toBeNull();
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00'));
    $this->artisan('rounds:process')->assertExitCode(0);
    $this->assertDatabaseCount('meeting_rounds', 3);
});

test('monthly plans clamp the original join date and shift all phases by the same days', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-30 08:00'));
    $this->post(route('rooms.rounds.store', $this->room), [
        ...$this->input, 'repeat_frequency' => 'monthly',
        'join_starts_at' => '2026-01-31T09:00', 'review_starts_at' => '2026-01-31T10:00',
        'voting_starts_at' => '2026-01-31T11:00', 'final_starts_at' => '2026-01-31T12:00',
        'search_start_date' => '2026-02-01', 'search_end_date' => '2026-02-03',
    ])->assertSessionHasNoErrors();
    $schedule = RoundRecurrenceSchedule::query()->firstOrFail();
    $second = $schedule->parametersFor(1);
    $third = $schedule->parametersFor(2);
    expect($second['join_starts_at'])->toBe('2026-02-28 09:00:00');
    expect($second['search_start_date'])->toBe('2026-03-01');
    expect($second['search_end_date'])->toBe('2026-03-03');
    expect($third['join_starts_at'])->toBe('2026-03-31 09:00:00');
    expect($third['search_start_date'])->toBe('2026-04-01');
    expect($third['final_starts_at'])->toBe('2026-03-31 12:00:00');
});

test('missed joins are skipped and the count limits total planned rounds', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input)->assertSessionHasNoErrors();
    $schedule = RoundRecurrenceSchedule::query()->firstOrFail();
    $this->travelTo(CarbonImmutable::parse('2026-10-27 09:05'));
    $this->artisan('rounds:process')->assertExitCode(0);
    expect($schedule->fresh()->skipped_count)->toBe(1);
    expect($schedule->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseCount('meeting_rounds', 2);
    expect($this->room->rounds()->orderByDesc('round_no')->firstOrFail()->recurrence_index)->toBe(2);
});

test('an active manual round blocks creation until the planned join window is missed', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input);
    $first = $this->room->rounds()->firstOrFail();
    $first->status = 'completed';
    $first->active_marker = null;
    $first->save();
    $schedule = $first->recurrenceSchedule;
    $manual = new MeetingRound;
    foreach ($schedule->parametersFor(1) as $field => $value) {
        $manual->setAttribute($field, $value);
    }
    $manual->room_id = $this->room->id;
    $manual->round_no = 2;
    $manual->save();
    $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00'));
    app(ProcessRecurringRounds::class)->handle($schedule->id);
    expect($schedule->fresh()->next_index)->toBe(1);
    $this->assertDatabaseCount('meeting_rounds', 2);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00'));
    app(ProcessRecurringRounds::class)->handle($schedule->id);
    expect($schedule->fresh()->next_index)->toBe(2);
    expect($schedule->fresh()->skipped_count)->toBe(1);
    $this->assertDatabaseCount('meeting_rounds', 2);
});

test('only the owner can stop future rounds and stopping preserves the current round', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input);
    $round = $this->room->rounds()->firstOrFail();
    $url = route('rooms.rounds.recurrence.stop', [$this->room, $round]);
    $this->actingAs(User::factory()->create())->patch($url)->assertNotFound();
    expect($round->recurrenceSchedule->fresh()->is_active)->toBeTrue();
    $this->actingAs($this->owner)->patch($url)->assertSessionHas('success');
    expect($round->fresh()->status)->toBe('active');
    expect($round->recurrenceSchedule->fresh()->is_active)->toBeFalse();
    $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00'));
    $this->artisan('rounds:process')->assertExitCode(0);
    $this->assertDatabaseCount('meeting_rounds', 1);
});

test('cancelling or resetting a recurring round stops its future schedule', function (string $operation) {
    $this->post(route('rooms.rounds.store', $this->room), $this->input);
    $round = $this->room->rounds()->firstOrFail();
    if ($operation === 'cancel') {
        $this->patch(route('rooms.rounds.cancel', [$this->room, $round]))->assertSessionHas('success');
    } else {
        $this->get(route('rooms.rounds.reset.edit', [$this->room, $round]))->assertSee('การรีเซ็ตนี้จะหยุดแผนสร้างรอบอัตโนมัติเดิมด้วย');
        $this->patch(route('rooms.rounds.reset.update', [$this->room, $round]), [...$this->input, 'confirm_reset' => 1])->assertSessionHasNoErrors();
    }
    expect($round->recurrenceSchedule->fresh()->is_active)->toBeFalse();
    expect($round->recurrenceSchedule->fresh()->next_run_at)->toBeNull();
})->with(['cancel', 'reset']);

test('invalid repeat settings and overlapping plans roll back the entire creation', function (array $changes, string $field) {
    $this->post(route('rooms.rounds.store', $this->room), array_replace($this->input, $changes))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('meeting_rounds', 0);
    $this->assertDatabaseCount('round_recurrence_schedules', 0);
})->with([
    [['repeat_frequency' => 'daily'], 'repeat_frequency'],
    [['repeat_count' => null], 'repeat_count'],
    [['repeat_count' => 1], 'repeat_count'],
    [['repeat_count' => 53], 'repeat_count'],
    [['repeat_count' => 2.5], 'repeat_count'],
    [['review_starts_at' => '2026-10-21T10:00', 'voting_starts_at' => '2026-10-21T11:00', 'final_starts_at' => '2026-10-21T12:00',
        'search_start_date' => '2026-10-22', 'search_end_date' => '2026-10-22'], 'repeat_frequency'],
]);

test('a second recurring plan is rejected while a previous plan still has future rounds', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input);
    $round = $this->room->rounds()->firstOrFail();
    $this->travelTo($round->final_starts_at);
    $this->artisan('rounds:process')->assertExitCode(0);
    $this->post(route('rooms.rounds.store', $this->room), [
        ...$this->input, 'join_starts_at' => '2026-10-13T13:00', 'review_starts_at' => '2026-10-13T14:00',
        'voting_starts_at' => '2026-10-13T15:00', 'final_starts_at' => '2026-10-13T16:00',
    ])->assertSessionHasErrors('repeat_frequency');
    $this->assertDatabaseCount('meeting_rounds', 1);
    $this->assertDatabaseCount('round_recurrence_schedules', 1);
});

test('deleted rooms do not receive automatic rounds', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input);
    $schedule = RoundRecurrenceSchedule::query()->firstOrFail();
    $this->room->delete();
    $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00'));
    $this->artisan('rounds:process')->assertExitCode(0);
    expect($schedule->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseCount('meeting_rounds', 1);
});

test('round recurrence migration rolls back and reapplies on SQLite', function () {
    $this->post(route('rooms.rounds.store', $this->room), [...$this->input, 'repeat_frequency' => 'none'])->assertSessionHasNoErrors();
    $round = $this->room->rounds()->firstOrFail();
    $member = $round->members()->create([
        'room_member_id' => $this->room->members()->firstOrFail()->id,
        'role' => 'student', 'weight' => 1, 'joined_at' => now(),
    ]);
    $member->busyPeriods()->create(['start_at' => '2026-10-14 09:00', 'end_at' => '2026-10-14 10:00']);
    $candidate = $round->candidates()->create([
        'start_at' => '2026-10-14 10:00', 'end_at' => '2026-10-14 11:00',
        'available_count' => 1, 'professor_count' => 0, 'available_member_ids' => [$member->id],
    ]);
    $candidate->votes()->create(['round_member_id' => $member->id]);
    $migration = require database_path('migrations/2026_10_13_000002_create_round_recurrence_schedules.php');
    $migration->down();
    expect(Schema::hasColumn('meeting_rounds', 'recurrence_schedule_id'))->toBeFalse();
    expect(Schema::hasTable('round_recurrence_schedules'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('meeting_rounds', 'recurrence_schedule_id'))->toBeTrue();
    expect($round->fresh())->not->toBeNull();
    expect($member->fresh())->not->toBeNull();
    expect($candidate->fresh())->not->toBeNull();
    $this->assertDatabaseCount('round_member_busy_periods', 1);
    $this->assertDatabaseCount('round_votes', 1);
    $invalidRound = $this->input;
    unset($invalidRound['repeat_frequency'], $invalidRound['repeat_count']);
    $invalidRound['room_id'] = $this->room->id;
    $invalidRound['round_no'] = 2;
    $invalidRound['status'] = 'completed';
    $invalidRound['active_marker'] = null;
    $invalidRound['professor_rule'] = 'invalid';
    expect(fn () => DB::table('meeting_rounds')->insert($invalidRound))->toThrow(QueryException::class);
});
