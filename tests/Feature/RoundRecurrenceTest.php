<?php

use App\Actions\ProcessMeetingRound;
use App\Models\BusySlotRecurrenceRule;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 08:00'));
    config(['rounds.snapshot_delay_minutes' => 0]);
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner)->post(route('rooms.store'), ['name' => 'Recurring room', 'visibility' => 'private']);
    $this->room = Room::query()->firstOrFail();
    $this->input = [
        'activity_name' => 'Weekly meeting',
        'search_start_date' => '2026-10-14', 'search_end_date' => '2026-10-14',
        'daily_start_time' => '09:00', 'daily_end_time' => '12:00', 'duration_minutes' => 60,
        'professor_rule' => 'all',
        'join_starts_at' => '2026-10-13T09:00', 'review_starts_at' => '2026-10-13T10:00',
        'voting_starts_at' => '2026-10-13T11:00', 'final_starts_at' => '2026-10-13T12:00',
        'repeat_frequency' => 'weekly', 'repeat_count' => 10,
    ];
});

test('one vote produces ten weekly appointments in each participant calendar without new voting rounds', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input)->assertSessionHasNoErrors();
    $round = $this->room->rounds()->firstOrFail();
    $student = User::factory()->create();
    $membership = $this->room->members()->create([
        'user_id' => $student->id, 'role' => 'student', 'weight' => 1, 'status' => 'active', 'joined_at' => now(),
    ]);
    foreach ([$this->room->members()->where('user_id', $this->owner->id)->firstOrFail(), $membership] as $member) {
        $round->members()->create(['room_member_id' => $member->id, 'role' => $member->role, 'weight' => 1, 'joined_at' => now()]);
    }
    $this->get(route('rooms.rounds.show', [$this->room, $round]))->assertOk()->assertSee('นัดหมายเกิดซ้ำ');
    $this->travelTo($round->voting_starts_at);
    app(ProcessMeetingRound::class)->handle($round->id);
    $winner = $round->candidates()->orderBy('start_at')->firstOrFail();
    $this->post(route('rooms.rounds.votes.store', [$this->room, $round]), ['candidate_id' => $winner->id])->assertSessionHasNoErrors();
    $this->travelTo($round->final_starts_at);
    $this->artisan('rounds:process')->assertExitCode(0);
    $this->get(route('rooms.rounds.show', [$this->room, $round]))->assertOk()
        ->assertSee('ดูวันนัดหมายทั้งหมด')->assertSee($winner->start_at->addWeeks(9)->format('d/m/Y H:i'));
    foreach ([$this->owner, $student] as $user) {
        $activity = $user->busySlots()->with('recurrenceRule')->sole();
        expect($activity->name)->toBe('Weekly meeting');
        expect($activity->recurrenceRule->frequency)->toBe('weekly');
        expect($activity->recurrenceRule->occurrence_count)->toBe(10);
        $occurrences = $activity->occurrencesBetween($winner->start_at, $winner->start_at->addWeeks(11));
        expect($occurrences)->toHaveCount(10);
        expect($occurrences[0]['start_at']->equalTo($winner->start_at))->toBeTrue();
        expect($occurrences[9]['start_at']->equalTo($winner->start_at->addWeeks(9)))->toBeTrue();
        expect($occurrences[9]['end_at']->equalTo($winner->end_at->addWeeks(9)))->toBeTrue();
        $this->actingAs($user)->get(route('activities.index', ['week' => $winner->start_at->addWeeks(9)->toDateString()]))
            ->assertOk()->assertViewHas('calendarActivities', fn ($activities) => $activities->contains(fn ($item) => $item->id === $activity->id && $item->start_at->equalTo($winner->start_at->addWeeks(9))));
        $this->get(route('activities.index', ['week' => $winner->start_at->addWeeks(10)->toDateString()]))
            ->assertOk()->assertViewHas('calendarActivities', fn ($activities) => $activities->isEmpty());
    }
    $this->travelTo($winner->start_at->addWeeks(11));
    $this->artisan('rounds:process')->assertExitCode(0);
    $this->assertDatabaseCount('meeting_rounds', 1);
    $this->assertDatabaseCount('round_votes', 1);
    $this->assertDatabaseCount('user_busy_slots', 2);
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 2);
    expect(Schema::hasTable('round_recurrence_schedules'))->toBeFalse();
});

test('monthly appointments clamp each occurrence from the original winning date and preserve duration', function () {
    $this->input['repeat_frequency'] = 'monthly';
    $this->input['repeat_count'] = 3;
    $this->input['search_start_date'] = '2026-10-31';
    $this->input['search_end_date'] = '2026-10-31';
    $this->post(route('rooms.rounds.store', $this->room), $this->input)->assertSessionHasNoErrors();
    $round = $this->room->rounds()->firstOrFail();
    $member = $this->room->members()->firstOrFail();
    $round->members()->create(['room_member_id' => $member->id, 'role' => 'student', 'weight' => 1, 'joined_at' => now()]);
    $this->travelTo($round->final_starts_at);
    app(ProcessMeetingRound::class)->handle($round->id);
    $winner = $round->candidates()->where('is_winner', true)->firstOrFail();
    $periods = $round->appointmentOccurrences($winner);
    expect(array_map(fn ($period) => $period['start_at']->format('Y-m-d'), $periods))->toBe(['2026-10-31', '2026-11-30', '2026-12-31']);
    $activity = $this->owner->busySlots()->sole();
    expect($activity->occurrencesBetween($winner->start_at, $winner->start_at->addMonths(4)))->toEqual($periods);
});

test('single appointments ignore repeat count and create one calendar activity', function () {
    $this->post(route('rooms.rounds.store', $this->room), [...$this->input, 'repeat_frequency' => 'none'])->assertSessionHasNoErrors();
    $round = $this->room->rounds()->firstOrFail();
    expect($round->repeat_count)->toBe(1);
    $member = $this->room->members()->firstOrFail();
    $round->members()->create(['room_member_id' => $member->id, 'role' => 'student', 'weight' => 1, 'joined_at' => now()]);
    $this->travelTo($round->final_starts_at);
    app(ProcessMeetingRound::class)->handle($round->id);
    expect($this->owner->busySlots()->sole()->recurrence_rule_id)->toBeNull();
});

test('no eligible winner produces no calendar activities', function () {
    $this->post(route('rooms.rounds.store', $this->room), [...$this->input, 'professor_rule' => 'at_least', 'min_professors' => 3])->assertSessionHasNoErrors();
    $round = $this->room->rounds()->firstOrFail();
    $this->travelTo($round->final_starts_at);
    app(ProcessMeetingRound::class)->handle($round->id);
    expect($round->fresh()->status)->toBe('completed');
    $this->assertDatabaseCount('user_busy_slots', 0);
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 0);
});

test('reset edits appointment repetition without creating a new voting round', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input)->assertSessionHasNoErrors();
    $round = $this->room->rounds()->firstOrFail();
    $this->get(route('rooms.rounds.reset.edit', [$this->room, $round]))->assertOk()->assertSee('repeat_frequency');
    $this->patch(route('rooms.rounds.reset.update', [$this->room, $round]), [
        ...$this->input, 'repeat_frequency' => 'monthly', 'repeat_count' => 4, 'confirm_reset' => 1,
    ])->assertSessionHasNoErrors();
    expect($round->fresh()->repeat_frequency)->toBe('monthly');
    expect($round->fresh()->repeat_count)->toBe(4);
    $this->assertDatabaseCount('meeting_rounds', 1);
});

test('invalid appointment recurrence is rejected before saving', function (array $changes, string $field) {
    $this->post(route('rooms.rounds.store', $this->room), [...$this->input, ...$changes])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('meeting_rounds', 0);
})->with([
    [['repeat_frequency' => 'daily'], 'repeat_frequency'],
    [['repeat_count' => null], 'repeat_count'],
    [['repeat_count' => 1], 'repeat_count'],
    [['repeat_count' => 53], 'repeat_count'],
    [['repeat_count' => 2.5], 'repeat_count'],
]);

test('migration replaces old voting schedules and preserves existing rounds members candidates votes and private activities', function () {
    $this->post(route('rooms.rounds.store', $this->room), $this->input)->assertSessionHasNoErrors();
    $round = $this->room->rounds()->firstOrFail();
    $member = $round->members()->create(['room_member_id' => $this->room->members()->firstOrFail()->id, 'role' => 'student', 'weight' => 1, 'joined_at' => now()]);
    $member->busyPeriods()->create(['start_at' => '2026-10-14 10:00', 'end_at' => '2026-10-14 11:00']);
    $candidate = $round->candidates()->create(['start_at' => '2026-10-14 09:00', 'end_at' => '2026-10-14 10:00', 'available_count' => 1, 'professor_count' => 0, 'available_member_ids' => [$member->id]]);
    $candidate->votes()->create(['round_member_id' => $member->id]);
    $private = $this->owner->busySlots()->create(['name' => 'Private lesson', 'start_at' => '2026-10-14 10:00', 'end_at' => '2026-10-14 11:00']);
    $private->recurrenceRule()->associate(BusySlotRecurrenceRule::create(['frequency' => 'weekly', 'occurrence_count' => 5]));
    $private->save();
    $migration = require database_path('migrations/2026_10_13_000003_replace_round_schedules_with_appointment_recurrence.php');
    $migration->down();
    $scheduleId = DB::table('round_recurrence_schedules')->insertGetId(['room_id' => $this->room->id, 'frequency' => 'monthly', 'round_count' => 4, 'template' => '{}']);
    DB::table('meeting_rounds')->where('id', $round->id)->update(['recurrence_schedule_id' => $scheduleId, 'recurrence_index' => 0]);
    $migration->up();
    expect($round->fresh()->repeat_frequency)->toBe('monthly');
    expect($round->fresh()->repeat_count)->toBe(4);
    expect(Schema::hasTable('round_recurrence_schedules'))->toBeFalse();
    expect(Schema::hasColumn('meeting_rounds', 'recurrence_index'))->toBeFalse();
    expect($member->fresh())->not->toBeNull();
    expect($candidate->fresh())->not->toBeNull();
    $this->assertDatabaseCount('round_votes', 1);
    $this->assertDatabaseCount('round_member_busy_periods', 1);
    expect($private->fresh()->recurrenceRule->occurrence_count)->toBe(5);
});
