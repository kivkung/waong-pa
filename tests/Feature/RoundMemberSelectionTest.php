<?php

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
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

test('picker lists existing participants first and only candidates from this room', function () {
    $ownerMember = $this->room->members()->where('user_id', $this->owner->id)->firstOrFail();
    $this->round->members()->create(['room_member_id' => $ownerMember->id, 'role' => 'student', 'weight' => 1, 'joined_at' => now()]);
    User::factory()->create(['name' => 'Outsider hidden']);
    $this->actingAs($this->owner)->get($this->url)->assertOk()
        ->assertSeeInOrder(['Z Owner', 'A Candidate'])->assertSee('checked', false)
        ->assertDontSee('disabled', false)->assertDontSee('Outsider hidden');
    $this->get(route('rooms.rounds.show', [$this->room, $this->round]))->assertOk()->assertSee($this->url);
});

test('bulk adding snapshots members once without changing room membership or confirmation', function () {
    $this->actingAs($this->owner)->patch($this->submit, ['member_ids' => [$this->member->id]])
        ->assertRedirect(route('rooms.rounds.show', [$this->room, $this->round]));
    $entry = $this->round->members()->firstOrFail();
    $this->member->weight = 9;
    $this->member->save();
    $this->patch($this->submit, ['member_ids' => [$this->member->id]])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('round_members', 1);
    $this->assertDatabaseCount('room_members', 2);
    expect((float) $entry->fresh()->weight)->toBe(3.0);
    expect($entry->fresh()->joined_at)->not->toBeNull();
});

test('guests and non owners cannot use the picker', function () {
    $this->get($this->url)->assertRedirect(route('login'));
    $this->patch($this->submit)->assertRedirect(route('login'));
    foreach (['private' => 404, 'public' => 403] as $visibility => $status) {
        $this->room->visibility = $visibility;
        $this->room->save();
        $this->actingAs($this->candidate)->get($this->url)->assertStatus($status);
        $this->patch($this->submit, ['member_ids' => [$this->member->id]])->assertStatus($status);
    }
    $this->assertDatabaseCount('round_members', 0);
});

test('owner can deselect participants and they can join again themselves', function () {
    $this->actingAs($this->owner)->patch($this->submit, ['member_ids' => [$this->member->id]])
        ->assertSessionHasNoErrors();
    $this->patch($this->submit, [])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('round_members', 0);
    $this->assertDatabaseHas('room_members', ['id' => $this->member->id, 'status' => 'active']);
    $this->actingAs($this->candidate)
        ->post(route('rooms.rounds.members.store', [$this->room, $this->round]))
        ->assertSessionHasNoErrors()->assertSessionHas('success');
    $this->assertDatabaseHas('round_members', [
        'meeting_round_id' => $this->round->id, 'room_member_id' => $this->member->id,
    ]);
});

test('editing retains checked snapshots and removes only unchecked participants of this round', function () {
    $ownerMember = $this->room->members()->where('user_id', $this->owner->id)->firstOrFail();
    $this->actingAs($this->owner)->patch($this->submit, ['member_ids' => [$ownerMember->id, $this->member->id]])
        ->assertSessionHasNoErrors();
    $this->patch($this->submit, ['member_ids' => [$ownerMember->id]])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('round_members', 1);
    $this->assertDatabaseHas('round_members', ['room_member_id' => $ownerMember->id]);
    $this->assertDatabaseCount('room_members', 2);
});

test('existing participants who left the room may be retained or removed', function () {
    $this->actingAs($this->owner)->patch($this->submit, ['member_ids' => [$this->member->id]])
        ->assertSessionHasNoErrors();
    $this->member->status = 'left';
    $this->member->save();
    $this->get($this->url)->assertOk()->assertSee('A Candidate');
    $this->patch($this->submit, ['member_ids' => [$this->member->id]])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('round_members', 1);
    $this->patch($this->submit, [])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('round_members', 0);
});

test('invalid edits preserve existing participants and unchecked choices after validation', function () {
    $this->actingAs($this->owner)->patch($this->submit, ['member_ids' => [$this->member->id]])
        ->assertSessionHasNoErrors();
    $this->from($this->url)->patch($this->submit, ['member_ids' => [999999], 'selection_submitted' => 1])
        ->assertSessionHasErrors();
    $this->assertDatabaseCount('round_members', 1);
    $this->get($this->url)->assertOk()->assertDontSee('checked', false);
});

test('closed rounds reject page and submission', function (string $state) {
    match ($state) {
        'early' => $this->round->join_starts_at = now()->addHour(),
        'late' => $this->round->review_starts_at = now(),
        'cancelled' => $this->round->status = 'cancelled',
        'review' => $this->round->phase = 'review',
    };
    $this->round->save();
    $this->actingAs($this->owner)->get($this->url)->assertRedirect()->assertSessionHas('warning');
    $this->patch($this->submit, ['member_ids' => [$this->member->id]])->assertRedirect()->assertSessionHas('warning');
    $this->assertDatabaseCount('round_members', 0);
})->with(['early', 'late', 'cancelled', 'review']);

test('inactive duplicate and foreign memberships are rejected without partial additions', function (string $case) {
    $otherRoom = $this->room->replicate();
    $otherRoom->join_code = 'OTHERSELECT1';
    $otherRoom->save();
    $other = RoomMember::create([
        'room_id' => $otherRoom->id, 'user_id' => $this->candidate->id,
        'status' => 'active', 'role' => 'student', 'weight' => 1, 'joined_at' => now(),
    ]);
    if (in_array($case, ['left', 'removed'])) {
        $other->room_id = $this->room->id;
        $other->user_id = User::factory()->create()->id;
        $other->status = $case;
        $other->save();
    }
    $ids = [$this->member->id, $case === 'duplicate' ? $this->member->id : $other->id];
    $this->actingAs($this->owner)->patch($this->submit, ['member_ids' => $ids])->assertSessionHasErrors();
    $this->assertDatabaseCount('round_members', 0);
    $this->get(route('rooms.rounds.members.edit', [$otherRoom, $this->round]))->assertNotFound();
    $this->patch(route('rooms.rounds.members.update', [$otherRoom, $this->round]))->assertNotFound();
})->with(['foreign', 'left', 'removed', 'duplicate']);
