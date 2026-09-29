<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function create(): View
    {
        return view('rooms.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['required', Rule::in(['public', 'private'])],
        ]);

        // Either both the room and its owner membership are saved, or neither is.
        $room = DB::transaction(function () use ($request, $validated) {
            do {
                $code = Str::upper(Str::random(12));
            } while (Room::where('join_code', $code)->exists());

            $room = new Room;
            $room->owner_id = $request->user()->id;
            $room->name = $validated['name'];
            $room->description = $validated['description'] ?? null;
            $room->visibility = $validated['visibility'];
            $room->join_code = $code;
            $room->save();

            $member = new RoomMember;
            $member->room_id = $room->id;
            $member->user_id = $request->user()->id;
            $member->role = 'student';
            $member->weight = 1;
            $member->status = 'active';
            $member->joined_at = now();
            $member->save();

            return $room;
        });

        return redirect()->route('rooms.show', $room)
            ->with('success', 'สร้างห้องเรียบร้อยแล้ว คุณเป็นเจ้าของและสมาชิกของห้องนี้');
    }

    public function joinForm(): View
    {
        return view('rooms.join');
    }

    public function join(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'join_code' => ['required', 'string', 'max:100'],
        ]);
        $code = Str::upper(trim($validated['join_code']));
        $room = Room::where('join_code', $code)->first();

        if (!$room) {
            return back()->withErrors(['join_code' => 'ไม่พบรหัสห้อง กรุณาตรวจสอบอีกครั้ง'])
                ->withInput();
        }

        $member = RoomMember::where('room_id', $room->id)
            ->where('user_id', $request->user()->id)->first();

        if ($member && $member->status === 'removed') {
            return back()->withErrors(['join_code' => 'คุณถูกนำออกจากห้องนี้ กรุณาติดต่อเจ้าของห้อง'])
                ->withInput();
        }

        if ($member && $member->status === 'active') {
            return redirect()->route('rooms.show', $room)
                ->with('success', 'คุณเป็นสมาชิกห้องนี้อยู่แล้ว');
        }

        if (!$member) {
            // The unique(room_id, user_id) constraint also prevents duplicate membership.
            $member = RoomMember::firstOrCreate(
                ['room_id' => $room->id, 'user_id' => $request->user()->id],
                ['role' => 'student', 'weight' => 1, 'status' => 'active', 'joined_at' => now()],
            );
        } else {
            // Reuse a left membership instead of inserting the same pair again.
            $member->role = 'student';
            $member->weight = 1;
            $member->status = 'active';
            $member->joined_at = now();
            $member->left_at = null;
            $member->save();
        }

        return redirect()->route('rooms.show', $room)
            ->with('success', 'เข้าร่วมห้องเรียบร้อยแล้ว');
    }

    public function show(Request $request, Room $room): View
    {
        $isMember = false;
        $isOwner = false;

        if ($request->user()) {
            $isMember = $room->members()->where('user_id', $request->user()->id)
                ->where('status', 'active')->exists();
            $isOwner = (int) $room->owner_id === (int) $request->user()->id;
        }

        // Knowing the URL is not permission to read a private room.
        abort_unless($room->visibility === 'public' || $isMember, 404);

        // Only active members can see the list. Load users together to avoid one query per name.
        $members = null;
        if ($isMember || $isOwner) {
            $members = $room->members()->where('status', 'active')
                ->with('user')->orderBy('id')->paginate(15);
        }

        $rounds = $room->rounds()
            ->orderByDesc('round_no')
            ->paginate(10, ['*'], 'rounds_page');

        return view('rooms.show', compact('room', 'isMember', 'isOwner', 'members', 'rounds'));
    }
}
