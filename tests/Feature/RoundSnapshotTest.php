<?php

use App\Actions\ProcessMeetingRound;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\RoundMember;
use App\Models\RoundMemberBusyPeriod;
use App\Models\User;
use App\Models\UserBusySlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 10:00:00'));
    $this->owner = User::factory()->create(['name' => 'Z Owner']);
    $this->candidate = User::factory()->create(['name' => 'A Candidate']);
    $this->room = new Room;
    $this->room->owner_id = $this->owner->id;
    $this->room->name = 'Round selection';
    $this->room->visibility = 'private';
    $this->room->join_code = 'ROUNDSELECT1';
    $this->room->save();
    foreach ([$this->owner, $this->candidate] as $user) {
        RoomMember::create([
            'room_id' => $this->room->id, 'user_id' => $user->id,
            'status' => 'active', 'role' => 'professor', 'weight' => 3, 'joined_at' => now(),
        ]);
    }
    $id = DB::table('meeting_rounds')->insertGetId([
        'room_id' => $this->room->id, 'round_no' => 1,
        'search_start_date' => now()->addDay()->toDateString(),
        'search_end_date' => now()->addDays(2)->toDateString(),
        'join_starts_at' => now()->subHour(), 'review_starts_at' => now()->addHour(),
        'voting_starts_at' => now()->addHours(2), 'final_starts_at' => now()->addHours(3),
    ]);
    $this->round = $this->room->rounds()->findOrFail($id);
    $this->member = $this->room->members()->where('user_id', $this->candidate->id)->firstOrFail();
    $this->url = route('rooms.rounds.members.edit', [$this->room, $this->round]);
    $this->submit = route('rooms.rounds.members.update', [$this->room, $this->round]);
});

function snapshotParticipant($test): RoundMember
{
    return $test->round->members()->create([
        'room_member_id' => $test->member->id, 'role' => 'student', 'weight' => 1, 'joined_at' => now(),
    ]);
}

function resetInput(): array
{
    return [
        'search_start_date' => '2026-10-03', 'search_end_date' => '2026-10-04',
        'daily_start_time' => '09:00', 'daily_end_time' => '17:00', 'duration_minutes' => 60,
        'professor_rule' => 'all', 'join_starts_at' => '2026-10-02T09:00',
        'review_starts_at' => '2026-10-02T12:00', 'voting_starts_at' => '2026-10-02T13:00',
        'final_starts_at' => '2026-10-02T14:00', 'confirm_reset' => 1,
    ];
}

test('snapshot waits for configured delay then clips periods and is idempotent', function () {
    $participant = snapshotParticipant($this);
    $activity = $this->candidate->busySlots()->create([
        'name' => 'Private title', 'start_at' => '2026-10-03 07:00', 'end_at' => '2026-10-04 09:00',
    ]);
    $deleted = $this->candidate->busySlots()->create([
        'name' => 'Deleted', 'start_at' => '2026-10-03 12:00', 'end_at' => '2026-10-03 13:00',
    ]);
    $deleted->delete();
    config(['rounds.snapshot_delay_minutes' => 7]);
    $process = app(ProcessMeetingRound::class);
    $this->travelTo($this->round->review_starts_at);
    $process->handle($this->round->id);
    expect($this->round->fresh()->phase)->toBe('review');
    expect($this->round->fresh()->snapshot_taken_at)->toBeNull();
    $activity->end_at = '2026-10-04 10:00';
    $activity->save();
    $this->travel(6)->minutes();
    $process->handle($this->round->id);
    $this->assertDatabaseCount('round_member_busy_periods', 0);
    $this->travel(1)->minutes();
    $process->handle($this->round->id);
    $this->assertDatabaseCount('round_member_busy_periods', 2);
    $this->assertDatabaseHas('round_member_busy_periods', [
        'round_member_id' => $participant->id, 'start_at' => '2026-10-03 08:00:00', 'end_at' => '2026-10-03 18:00:00',
    ]);
    $taken = $this->round->fresh()->snapshot_taken_at;
    $activity->delete();
    $process->handle($this->round->id);
    expect($this->round->fresh()->snapshot_taken_at->eq($taken))->toBeTrue();
    $this->assertDatabaseCount('round_member_busy_periods', 2);
    $this->actingAs($this->owner)->get(route('rooms.rounds.show', [$this->room, $this->round]))
        ->assertOk()->assertDontSee('Private title');
});

test('overlap excludes touching endpoints and preview matches snapshot', function () {
    $participant = snapshotParticipant($this);
    foreach ([['07:00', '08:00'], ['18:00', '19:00'], ['07:30', '08:30']] as [$start, $end]) {
        $this->candidate->busySlots()->create(['name' => 'Secret', 'start_at' => '2026-10-03 '.$start, 'end_at' => '2026-10-03 '.$end]);
    }
    $periods = UserBusySlot::periodsFor($this->round, $this->candidate->id);
    expect($periods)->toHaveCount(1);
    expect($periods[0]['start_at']->format('H:i'))->toBe('08:00');
    $this->actingAs($this->candidate)->get(route('rooms.show', $this->room))->assertOk()->assertDontSee('Secret');
    $this->travelTo($this->round->snapshotDueAt());
    app(ProcessMeetingRound::class)->handle($this->round->id);
    expect($participant->busyPeriods()->first()->end_at->eq($periods[0]['end_at']))->toBeTrue();
});

test('empty rounds and members without activities still get a snapshot timestamp', function (bool $hasMember) {
    if ($hasMember) {
        snapshotParticipant($this);
    }
    $this->travelTo($this->round->snapshotDueAt());
    $this->artisan('rounds:process')->assertExitCode(0);
    expect($this->round->fresh()->snapshot_taken_at)->not->toBeNull();
    $this->assertDatabaseCount('round_member_busy_periods', 0);
})->with([true, false]);

test('snapshot failure rolls back periods and retries while phase stays review', function () {
    snapshotParticipant($this);
    $this->candidate->busySlots()->create(['name' => 'Busy', 'start_at' => '2026-10-03 09:00', 'end_at' => '2026-10-03 10:00']);
    $this->travelTo($this->round->snapshotDueAt());
    RoundMemberBusyPeriod::created(function () {
        throw new RuntimeException('simulate failure');
    });
    try {
        app(ProcessMeetingRound::class)->handle($this->round->id);
        $this->fail('Expected failure');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('simulate failure');
    } finally {
        RoundMemberBusyPeriod::flushEventListeners();
    }
    expect($this->round->fresh()->phase)->toBe('review');
    expect($this->round->fresh()->snapshot_taken_at)->toBeNull();
    $this->assertDatabaseCount('round_member_busy_periods', 0);
    app(ProcessMeetingRound::class)->handle($this->round->id);
    $this->assertDatabaseCount('round_member_busy_periods', 1);
});

test('reset validates input preserves participants and clears snapshots atomically', function () {
    $participant = snapshotParticipant($this);
    $participant->busyPeriods()->create(['start_at' => '2026-10-03 09:00', 'end_at' => '2026-10-03 10:00']);
    $this->round->phase = 'review';
    $this->round->snapshot_taken_at = now();
    $this->round->save();
    $url = route('rooms.rounds.reset.update', [$this->room, $this->round]);
    $this->actingAs($this->candidate)->patch($url, resetInput())->assertNotFound();
    $this->actingAs($this->owner)->get(route('rooms.rounds.reset.edit', [$this->room, $this->round]))->assertOk();
    $this->patch($url, [...resetInput(), 'duration_minutes' => 45])->assertSessionHasErrors();
    $this->assertDatabaseCount('round_member_busy_periods', 1);
    expect($this->round->fresh()->phase)->toBe('review');
    $this->patch($url, resetInput())->assertSessionHasNoErrors()->assertRedirect();
    expect($this->round->fresh()->phase)->toBe('join');
    expect($this->round->fresh()->snapshot_taken_at)->toBeNull();
    expect($this->round->fresh()->min_professors)->toBeNull();
    $this->assertDatabaseCount('round_members', 1);
    $this->assertDatabaseCount('round_member_busy_periods', 0);
});

test('leave and owner removal lock at review boundary even before scheduler', function (bool $owner) {
    snapshotParticipant($this);
    $this->travelTo($this->round->review_starts_at);
    $url = route('room.members.left', [$this->room, $this->member]);
    $this->actingAs($owner ? $this->owner : $this->candidate)->post($url)->assertSessionHas('warning');
    expect($this->member->fresh()->status)->toBe('active');
    $this->assertDatabaseCount('round_members', 1);
    $this->actingAs($this->owner)->patch(route('rooms.rounds.reset.update', [$this->room, $this->round]), resetInput())
        ->assertSessionHasNoErrors();
    $this->actingAs($owner ? $this->owner : $this->candidate)->post($url)->assertSessionHas('success');
    $this->assertDatabaseCount('round_members', 0);
    expect($this->member->fresh()->status)->toBe($owner ? 'removed' : 'left');
})->with([true, false]);

test('personal activities are owner only and soft deleted rooms disappear', function () {
    $activity = $this->candidate->busySlots()->create(['name' => 'Hidden name', 'start_at' => '2026-10-03 09:00', 'end_at' => '2026-10-03 10:00']);
    $this->actingAs($this->owner)->get(route('activities.edit', $activity))->assertNotFound();
    $this->patch(route('activities.update', $activity), [])->assertNotFound();
    $this->delete(route('activities.destroy', $activity))->assertNotFound();
    $this->actingAs($this->candidate)->get(route('activities.index'))->assertOk()->assertSee('Hidden name');
    $this->delete(route('activities.destroy', $activity))->assertRedirect();
    $this->assertSoftDeleted('user_busy_slots', ['id' => $activity->id]);
    $this->room->delete();
    $this->assertSoftDeleted('rooms', ['id' => $this->room->id]);
    $this->get(route('rooms.show', $this->room))->assertNotFound();
});

test('migration backfills latest confirmation only for rounds past Join before dropping it', function () {
    $participant = snapshotParticipant($this);
    $migration = require database_path('migrations/2026_10_02_000002_add_round_snapshots.php');
    $migration->down();
    DB::table('round_members')->where('id', $participant->id)->update(['confirmed_at' => '2026-10-02 09:30:00']);
    $this->round->phase = 'review';
    $this->round->save();
    $migration->up();
    expect($this->round->fresh()->snapshot_taken_at->format('Y-m-d H:i:s'))->toBe('2026-10-02 09:30:00');
    expect(Schema::hasColumn('round_members', 'confirmed_at'))->toBeFalse();
});
