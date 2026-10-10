<?php

use App\Actions\ProcessMeetingRound;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\RoundVote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('development shortcut opens voting with a snapshot then closes using recorded votes', function () {
    config(['rounds.snapshot_delay_minutes' => 7]);
    $this->artisan('rounds:advance', ['round' => $this->round->id, 'stage' => 'voting'])->assertExitCode(0);
    $round = $this->round->fresh();
    expect($round->phase)->toBe('voting');
    expect($round->snapshot_taken_at)->not->toBeNull();
    expect($round->candidates()->count())->toBe(5);
    expect($round->join_starts_at < $round->review_starts_at)->toBeTrue();
    expect($round->review_starts_at < $round->voting_starts_at)->toBeTrue();
    expect($round->final_starts_at > now())->toBeTrue();
    $candidate = $round->candidates()->where('available_count', 2)->orderBy('start_at')->firstOrFail();
    $this->actingAs($this->student)->post($this->vote, ['candidate_id' => $candidate->id])->assertSessionHasNoErrors();

    $this->artisan('rounds:advance', ['round' => $round->id, 'stage' => 'final'])->assertExitCode(0);
    expect($round->fresh()->status)->toBe('completed');
    expect($candidate->fresh()->is_winner)->toBeTrue();
    $this->post($this->vote, ['candidate_id' => $candidate->id])->assertSessionHasErrors('candidate_id');
    $this->artisan('rounds:advance', ['round' => $round->id, 'stage' => 'voting'])->assertExitCode(1);
});

test('development shortcut rejects invalid stages ids and closing before voting', function () {
    foreach ([['round' => 99999, 'stage' => 'voting'], ['round' => 'abc', 'stage' => 'voting'],
        ['round' => $this->round->id, 'stage' => 'join'], ['round' => $this->round->id, 'stage' => 'final']] as $arguments) {
        $this->artisan('rounds:advance', $arguments)->assertExitCode(1);
    }
    expect($this->round->fresh()->phase)->toBe('join');
    expect($this->round->fresh()->snapshot_taken_at)->toBeNull();
});

test('development shortcut cannot extend an open vote or run in production', function () {
    $arguments = ['round' => $this->round->id, 'stage' => 'voting'];
    $this->artisan('rounds:advance', $arguments)->assertExitCode(0);
    $deadline = $this->round->fresh()->final_starts_at;
    $this->artisan('rounds:advance', $arguments)->assertExitCode(1);
    expect($this->round->fresh()->final_starts_at->eq($deadline))->toBeTrue();

    app()->instance('env', 'production');
    try {
        $this->artisan('rounds:advance', ['round' => $this->round->id, 'stage' => 'final'])->assertExitCode(1);
        expect($this->round->fresh()->phase)->toBe('voting');
    } finally {
        app()->instance('env', 'testing');
    }
});

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00'));
    config(['rounds.snapshot_delay_minutes' => 0]);
    $this->owner = User::factory()->create();
    $this->student = User::factory()->create();
    $this->room = new Room;
    $this->room->owner_id = $this->owner->id;
    $this->room->name = 'ห้องทดสอบ';
    $this->room->join_code = 'VOTINGTEST12';
    $this->room->visibility = 'private';
    $this->room->save();
    $id = DB::table('meeting_rounds')->insertGetId([
        'room_id' => $this->room->id, 'round_no' => 1,
        'search_start_date' => '2026-10-11', 'search_end_date' => '2026-10-11',
        'daily_start_time' => '09:00', 'daily_end_time' => '12:00', 'duration_minutes' => 60,
        'professor_rule' => 'all', 'min_professors' => null,
        'join_starts_at' => '2026-10-10 09:00', 'review_starts_at' => '2026-10-10 11:00',
        'voting_starts_at' => '2026-10-10 12:00', 'final_starts_at' => '2026-10-10 13:00',
    ]);
    $this->round = $this->room->rounds()->findOrFail($id);
    foreach ([$this->owner, $this->student] as $index => $user) {
        $membership = RoomMember::create([
            'room_id' => $this->room->id, 'user_id' => $user->id, 'status' => 'active',
            'role' => $index ? 'student' : 'professor', 'weight' => 1, 'joined_at' => now(),
        ]);
        $participant = $this->round->members()->create([
            'room_member_id' => $membership->id, 'role' => $membership->role, 'weight' => 1, 'joined_at' => now(),
        ]);
        if ($index) {
            $this->participant = $participant;
        }
    }
    $this->student->busySlots()->create(['name' => 'Private lesson', 'start_at' => '2026-10-11 09:00', 'end_at' => '2026-10-11 10:00']);
    $this->show = route('rooms.rounds.show', [$this->room, $this->round]);
    $this->vote = route('rooms.rounds.votes.store', [$this->room, $this->round]);
});

test('review scores full duration overlaps and ranks using a frozen snapshot', function () {
    $this->travelTo($this->round->review_starts_at);
    $this->actingAs($this->student)->get($this->show)->assertOk()->assertSee('ตรวจตัวเลือก')->assertDontSee('Private lesson');
    $candidates = $this->round->candidates()->orderBy('start_at')->get();
    expect($candidates)->toHaveCount(5);
    expect($candidates->pluck('available_count')->all())->toBe([1, 1, 2, 2, 2]);
    expect($candidates[2]->available_member_ids)->toContain($this->participant->id);
    $this->student->busySlots()->delete();
    app(ProcessMeetingRound::class)->handle($this->round->id);
    expect($this->round->candidates()->count())->toBe(5);
    expect($this->round->candidates()->orderBy('start_at')->first()->available_count)->toBe(1);
    $this->get($this->show)->assertViewHas('candidates', fn ($options) => $options->first()->start_at->format('H:i') === '10:00');
});

test('professor constraints reject any overlap across the appointment', function (string $rule, int $minimum, int $expected) {
    $this->round->professor_rule = $rule;
    $this->round->min_professors = $minimum;
    $this->round->save();
    $this->owner->busySlots()->create(['name' => 'Busy professor', 'start_at' => '2026-10-11 10:00', 'end_at' => '2026-10-11 11:00']);
    $this->travelTo($this->round->review_starts_at);
    app(ProcessMeetingRound::class)->handle($this->round->id);
    expect($this->round->candidates()->count())->toBe($expected);
})->with([['all', 1, 2], ['at_least', 1, 2], ['at_least', 2, 0]]);

test('voting hides personal conflicts and stores only one changeable vote per participant', function () {
    $this->travelTo($this->round->voting_starts_at);
    $this->actingAs($this->student)->get($this->show)->assertOk()
        ->assertViewHas('candidates', fn ($options) => $options->count() === 3);
    $options = $this->round->candidates()->orderBy('start_at')->get();
    $this->post($this->vote, ['candidate_id' => $options[0]->id])->assertSessionHasErrors('candidate_id');
    $this->post($this->vote, ['candidate_id' => $options[2]->id])->assertSessionHasNoErrors()->assertRedirect($this->show);
    $this->post($this->vote, ['candidate_id' => $options[3]->id])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('round_votes', 1);
    $this->assertDatabaseHas('round_votes', ['round_member_id' => $this->participant->id, 'round_candidate_id' => $options[3]->id]);
});

test('votes require membership and round participation and reject foreign candidates', function () {
    $this->travelTo($this->round->voting_starts_at);
    $outsider = User::factory()->create();
    $this->actingAs($outsider)->post($this->vote, ['candidate_id' => 1])->assertNotFound();
    RoomMember::create(['room_id' => $this->room->id, 'user_id' => $outsider->id, 'status' => 'active', 'role' => 'student', 'weight' => 1, 'joined_at' => now()]);
    $this->post($this->vote, ['candidate_id' => 1])->assertNotFound();
    $this->actingAs($this->student)->post($this->vote, ['candidate_id' => 99999])->assertNotFound();
});

test('review and exact final deadline reject votes and final ties prefer readiness then earliest', function () {
    $this->travelTo($this->round->review_starts_at);
    $this->actingAs($this->student)->get($this->show);
    $options = $this->round->candidates()->orderBy('start_at')->get();
    $this->post($this->vote, ['candidate_id' => $options[2]->id])->assertSessionHasErrors('candidate_id');
    $this->travelTo($this->round->voting_starts_at);
    $this->actingAs($this->owner)->post($this->vote, ['candidate_id' => $options[0]->id])->assertSessionHasNoErrors();
    $this->actingAs($this->student)->post($this->vote, ['candidate_id' => $options[2]->id])->assertSessionHasNoErrors();
    $this->travelTo($this->round->final_starts_at);
    $this->post($this->vote, ['candidate_id' => $options[3]->id])->assertSessionHasErrors('candidate_id');
    expect($this->round->fresh()->status)->toBe('completed');
    expect($this->round->fresh()->active_marker)->toBeNull();
    expect($options[2]->fresh()->is_winner)->toBeTrue();
    app(ProcessMeetingRound::class)->handle($this->round->id);
    expect($this->round->candidates()->where('is_winner', true)->count())->toBe(1);
    $this->get($this->show)->assertOk()->assertSee('เวลาที่ได้รับเลือก');
    $this->get(route('rooms.rounds.calendar', [$this->room, $this->round]))->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->assertSee('BEGIN:VEVENT')->assertSee('DTSTART:');
});

test('equal vote and readiness scores select earliest appointment', function () {
    $this->travelTo($this->round->voting_starts_at);
    $this->actingAs($this->student)->get($this->show);
    $options = $this->round->candidates()->where('available_count', 2)->orderBy('start_at')->get();
    $this->post($this->vote, ['candidate_id' => $options[1]->id]);
    $this->actingAs($this->owner)->post($this->vote, ['candidate_id' => $options[0]->id]);
    $this->travelTo($this->round->final_starts_at);
    app(ProcessMeetingRound::class)->handle($this->round->id);
    expect($options[0]->fresh()->is_winner)->toBeTrue();
});

test('no votes or no eligible options finalize without inventing a winner', function (bool $empty) {
    if ($empty) {
        $this->round->professor_rule = 'at_least';
        $this->round->min_professors = 3;
        $this->round->save();
    }
    $this->travelTo($this->round->final_starts_at);
    $this->actingAs($this->student)->get($this->show)->assertOk();
    expect($this->round->fresh()->phase)->toBe('final');
    expect($this->round->candidates()->where('is_winner', true)->exists())->toBeFalse();
    $this->get(route('rooms.rounds.calendar', [$this->room, $this->round]))->assertNotFound();
})->with([true, false]);

test('reset deletes candidates and votes and cancelled rounds reject votes', function () {
    $this->travelTo($this->round->voting_starts_at);
    $this->actingAs($this->student)->get($this->show);
    $candidate = $this->round->candidates()->where('available_count', 2)->firstOrFail();
    $this->post($this->vote, ['candidate_id' => $candidate->id]);
    $this->actingAs($this->owner)->patch(route('rooms.rounds.reset.update', [$this->room, $this->round]), [
        'search_start_date' => '2026-10-11', 'search_end_date' => '2026-10-11',
        'daily_start_time' => '09:00', 'daily_end_time' => '12:00', 'duration_minutes' => 60,
        'professor_rule' => 'all', 'join_starts_at' => '2026-10-10T09:00',
        'review_starts_at' => '2026-10-10T14:00', 'voting_starts_at' => '2026-10-10T15:00',
        'final_starts_at' => '2026-10-10T16:00', 'confirm_reset' => 1,
    ])->assertSessionHasNoErrors();
    expect($this->round->fresh()->candidates_generated_at)->toBeNull();
    $this->assertDatabaseCount('round_candidates', 0);
    $this->assertDatabaseCount('round_votes', 0);
    $this->patch(route('rooms.rounds.cancel', [$this->room, $this->round]));
    $this->post($this->vote, ['candidate_id' => $candidate->id])->assertSessionHasErrors('candidate_id');
    expect(RoundVote::query()->count())->toBe(0);
});
