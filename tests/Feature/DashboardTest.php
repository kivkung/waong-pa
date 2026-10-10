<?php

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Support\Str;

test('dashboard shows room actions for guests and members', function () {
    $this->get(route('dashboard'))->assertOk()
        ->assertSee('นัดหมายให้ลงตัว เริ่มจากห้องของคุณ')
        ->assertSee(route('login'));

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertSee(route('rooms.create'))
        ->assertSee(route('rooms.join'));
});

function makeRoomForTest(User $owner, string $visibility = 'public'): Room
{
    $room = new Room;
    $room->owner_id = $owner->id;
    $room->name = 'Room-'.Str::random(8);
    $room->description = 'Test description';
    $room->visibility = $visibility;
    $room->join_code = Str::upper(Str::random(12));
    $room->save();

    RoomMember::create([
        'room_id' => $room->id, 'user_id' => $owner->id,
        'role' => 'student', 'weight' => 1, 'status' => 'active', 'joined_at' => now(),
    ]);

    return $room;
}

test('guests see public rooms but not private rooms or invite codes', function () {
    $owner = User::factory()->create();
    $public = makeRoomForTest($owner);
    $private = makeRoomForTest($owner, 'private');

    $this->get(route('dashboard'))->assertOk()
        ->assertSee($public->name)->assertDontSee($private->name)
        ->assertDontSee($public->join_code)->assertDontSee($private->join_code);
    $this->get(route('rooms.show', $public))->assertOk()->assertDontSee($public->join_code);
    $this->get(route('rooms.show', $private))->assertNotFound();
});

test('logged in users see only their own private memberships', function () {
    $user = User::factory()->create();
    $own = makeRoomForTest($user, 'private');
    $other = makeRoomForTest(User::factory()->create(), 'private');

    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertSee($own->name)->assertDontSee($other->name);
    $this->get(route('rooms.show', $own))->assertOk()->assertSee($own->join_code);
    $this->get(route('rooms.show', $other))->assertNotFound();
});

test('guests cannot access create or join forms or submit them', function () {
    $this->get(route('rooms.create'))->assertRedirect(route('login'));
    $this->get(route('rooms.join'))->assertRedirect(route('login'));
    $this->post(route('rooms.store'), [])->assertRedirect(route('login'));
    $this->post(route('rooms.join.store'), [])->assertRedirect(route('login'));
    $this->assertDatabaseCount('rooms', 0);
});

test('creating a room makes the authenticated user its owner and active member', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->get(route('rooms.create'))->assertOk();
    $response = $this->post(route('rooms.store'), [
        'name' => 'My private room', 'visibility' => 'private',
        'owner_id' => $other->id, 'join_code' => 'UNTRUSTED123',
    ]);
    $room = Room::firstOrFail();
    $response->assertRedirect(route('rooms.show', $room));
    expect($room->owner_id)->toBe($user->id);
    expect(strlen($room->join_code))->toBe(12);
    expect($room->join_code)->not->toBe('UNTRUSTED123');
    $this->assertDatabaseHas('room_members', [
        'room_id' => $room->id, 'user_id' => $user->id,
        'role' => 'student', 'status' => 'active',
    ]);
    $this->get(route('rooms.show', $room))->assertOk()->assertSee($room->join_code);
});

test('invalid room input does not create any rows', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('rooms.store'), ['name' => '', 'visibility' => 'secret'])
        ->assertSessionHasErrors(['name', 'visibility']);
    $this->assertDatabaseCount('rooms', 0);
    $this->assertDatabaseCount('room_members', 0);
});

test('room creation rolls back when owner membership fails', function () {
    $this->actingAs(User::factory()->create());
    RoomMember::creating(function () {
        throw new RuntimeException('Simulated membership failure');
    });
    try {
        $this->withoutExceptionHandling()->post(route('rooms.store'), [
            'name' => 'Must roll back', 'visibility' => 'public',
        ]);
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated membership failure');
    } finally {
        RoomMember::flushEventListeners();
    }
    $this->assertDatabaseCount('rooms', 0);
    $this->assertDatabaseCount('room_members', 0);
});

test('a code joins a private room and repeat submissions do not duplicate membership', function () {
    $room = makeRoomForTest(User::factory()->create(), 'private');
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('rooms.join'))->assertOk();

    foreach (range(1, 2) as $attempt) {
        $this->post(route('rooms.join.store'), [
            'join_code' => ' '.strtolower($room->join_code).' ',
        ])->assertRedirect(route('rooms.show', $room));
    }

    expect(RoomMember::where('room_id', $room->id)->where('user_id', $user->id)->count())->toBe(1);
    $this->get(route('dashboard'))->assertSee($room->name);
    $this->get(route('rooms.show', $room))->assertOk()->assertDontSee($room->join_code);
});

test('invalid join codes do not add a member', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('rooms.join'))
        ->post(route('rooms.join.store'), ['join_code' => 'WRONGCODE123'])
        ->assertRedirect(route('rooms.join'))->assertSessionHasErrors('join_code');
    $this->assertDatabaseCount('room_members', 0);
});

test('left members can rejoin but removed members cannot use the code to bypass removal', function () {
    $room = makeRoomForTest(User::factory()->create(), 'private');
    $user = User::factory()->create();
    $member = RoomMember::create([
        'room_id' => $room->id, 'user_id' => $user->id, 'role' => 'student',
        'weight' => 1, 'status' => 'left', 'joined_at' => now(), 'left_at' => now(),
    ]);
    $this->actingAs($user)->get(route('rooms.show', $room))->assertNotFound();
    $this->post(route('rooms.join.store'), ['join_code' => $room->join_code])
        ->assertRedirect(route('rooms.show', $room));
    expect($member->fresh()->status)->toBe('active');
    expect($member->fresh()->left_at)->toBeNull();

    $member->update(['status' => 'removed']);
    $this->post(route('rooms.join.store'), ['join_code' => $room->join_code])
        ->assertSessionHasErrors('join_code');
    $this->get(route('rooms.show', $room))->assertNotFound();
});

test('room names are escaped when rendered', function () {
    $room = makeRoomForTest(User::factory()->create());
    $room->name = '<script>alert(1)</script>';
    $room->save();
    $this->get(route('dashboard'))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
