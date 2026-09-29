<?php

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->student = User::factory()->create(['name' => 'Visible Student']);
    $this->room = new Room;
    $this->room->owner_id = $this->owner->id;
    $this->room->name = 'Member management';
    $this->room->visibility = 'public';
    $this->room->join_code = Str::upper(Str::random(12));
    $this->room->save();

    foreach ([$this->owner, $this->student] as $user) {
        RoomMember::create([
            'room_id' => $this->room->id, 'user_id' => $user->id,
            'role' => 'student', 'weight' => 1, 'status' => 'active', 'joined_at' => now(),
        ]);
    }
    $this->member = $this->room->members()->where('user_id', $this->student->id)->firstOrFail();
});

test('only active members see the member list and only owners see edit links', function () {
    $edit = route('rooms.members.edit', [$this->room, $this->member]);
    $this->get(route('rooms.show', $this->room))->assertOk()->assertDontSee('Visible Student');
    $this->actingAs(User::factory()->create())->get(route('rooms.show', $this->room))
        ->assertOk()->assertDontSee('Visible Student');
    $this->actingAs($this->student)->get(route('rooms.show', $this->room))
        ->assertOk()->assertSee('Visible Student')->assertDontSee($edit);
    $this->actingAs($this->owner)->get(route('rooms.show', $this->room))
        ->assertOk()->assertSee('Visible Student')->assertSee($edit);
});

test('inactive members are hidden and cannot be edited', function (string $status) {
    $this->member->update(['status' => $status]);
    $this->actingAs($this->owner)->get(route('rooms.show', $this->room))
        ->assertOk()->assertDontSee('Visible Student');
    $this->get(route('rooms.members.edit', [$this->room, $this->member]))->assertNotFound();
    $this->patch(route('rooms.members.update', [$this->room, $this->member]), [
        'role' => 'professor', 'weight' => 2,
    ])->assertNotFound();
    $this->actingAs($this->student)->get(route('rooms.show', $this->room))
        ->assertOk()->assertDontSee('สมาชิกในห้อง');
    expect($this->member->fresh()->role)->toBe('student');
})->with(['left', 'removed']);

test('owner can edit a member but submitted identity and status fields are ignored', function () {
    $this->actingAs($this->owner)
        ->get(route('rooms.members.edit', [$this->room, $this->member]))
        ->assertOk()->assertSee('Visible Student')->assertSee('name="_token"', false)
        ->assertSee('value="PATCH"', false);
    $this->patch(route('rooms.members.update', [$this->room, $this->member]), [
        'role' => 'professor', 'weight' => '2.50',
        'room_id' => 999, 'user_id' => $this->owner->id, 'status' => 'removed', 'owner_id' => $this->student->id,
    ])->assertRedirect(route('rooms.show', $this->room))->assertSessionHas('success');
    $this->assertDatabaseHas('room_members', [
        'id' => $this->member->id, 'room_id' => $this->room->id, 'user_id' => $this->student->id,
        'role' => 'professor', 'weight' => 2.5, 'status' => 'active',
    ]);
    expect($this->room->fresh()->owner_id)->toBe($this->owner->id);
});

test('owner can change their own role without losing ownership', function () {
    $ownerMember = $this->room->members()->where('user_id', $this->owner->id)->firstOrFail();
    $this->actingAs($this->owner)->patch(route('rooms.members.update', [$this->room, $ownerMember]), [
        'role' => 'professor', 'weight' => 100,
    ])->assertRedirect(route('rooms.show', $this->room));
    expect($ownerMember->fresh()->role)->toBe('professor');
    expect($this->room->fresh()->owner_id)->toBe($this->owner->id);
    $this->get(route('rooms.show', $this->room))->assertSee($this->room->join_code);
});

test('guests must log in before editing members', function () {
    $this->get(route('rooms.members.edit', [$this->room, $this->member]))->assertRedirect(route('login'));
    $this->patch(route('rooms.members.update', [$this->room, $this->member]), [
        'role' => 'professor', 'weight' => 2,
    ])->assertRedirect(route('login'));
    expect($this->member->fresh()->role)->toBe('student');
});

test('members and outsiders cannot edit roles even by direct requests', function (string $visibility, int $status) {
    $this->room->visibility = $visibility;
    $this->room->save();
    foreach ([$this->student, User::factory()->create()] as $user) {
        $this->actingAs($user)->get(route('rooms.members.edit', [$this->room, $this->member]))->assertStatus($status);
        $this->patch(route('rooms.members.update', [$this->room, $this->member]), [
            'role' => 'professor', 'weight' => 2,
        ])->assertStatus($status);
    }
    expect($this->member->fresh()->role)->toBe('student');
})->with([['public', 403], ['private', 404]]);

test('a member id from a different room cannot be used', function () {
    $otherRoom = $this->room->replicate();
    $otherRoom->join_code = Str::upper(Str::random(12));
    $otherRoom->save();
    $otherMember = $this->member->replicate();
    $otherMember->room_id = $otherRoom->id;
    $otherMember->save();

    $this->actingAs($this->owner)->get(route('rooms.members.edit', [$this->room, $otherMember]))->assertNotFound();
    $this->patch(route('rooms.members.update', [$this->room, $otherMember]), [
        'role' => 'professor', 'weight' => 2,
    ])->assertNotFound();
    expect($otherMember->fresh()->role)->toBe('student');
});

test('invalid role or weight returns errors and preserves stored values', function (string $field, mixed $value) {
    $edit = route('rooms.members.edit', [$this->room, $this->member]);
    $input = ['role' => 'professor', 'weight' => '2.50'];
    $input[$field] = $value;
    $this->actingAs($this->owner)->from($edit)
        ->patch(route('rooms.members.update', [$this->room, $this->member]), $input)
        ->assertRedirect($edit);
    // Follow the redirect before inspecting errors: JSON sessions are deserialized on each request.
    $this->get($edit)->assertOk()->assertSee('is-invalid')->assertSee('id="'.$field.'-error"', false);
    expect($this->member->fresh()->role)->toBe('student');
    expect((float) $this->member->fresh()->weight)->toBe(1.0);
})->with([
    ['role', 'owner'], ['role', ''], ['weight', ''], ['weight', 'abc'],
    ['weight', 0], ['weight', -1], ['weight', 100.01], ['weight', '1.234'],
]);

test('minimum weight is accepted and escaped names render safely on member pages', function () {
    $this->student->update(['name' => '<script>alert(1)</script>']);
    $this->actingAs($this->owner)->patch(route('rooms.members.update', [$this->room, $this->member]), [
        'role' => 'student', 'weight' => '0.01',
    ])->assertSessionHasNoErrors();
    expect((float) $this->member->fresh()->weight)->toBe(0.01);
    foreach ([route('rooms.show', $this->room), route('rooms.members.edit', [$this->room, $this->member])] as $url) {
        $this->get($url)->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
});

test('member lists paginate with Bootstrap links', function () {
    foreach (range(1, 14) as $number) {
        RoomMember::create([
            'room_id' => $this->room->id,
            'user_id' => User::factory()->create(['name' => 'Additional member '.$number])->id,
            'role' => 'student', 'weight' => 1, 'status' => 'active', 'joined_at' => now(),
        ]);
    }
    $this->actingAs($this->owner)->get(route('rooms.show', $this->room))
        ->assertOk()->assertSee('Additional member 13')->assertDontSee('Additional member 14')
        ->assertSee('class="page-link"', false);
    $this->get(route('rooms.show', $this->room).'?page=2')
        ->assertOk()->assertSee('Additional member 14')->assertDontSee('Additional member 13');
});
