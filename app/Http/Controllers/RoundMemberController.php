<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoundMemberController extends Controller
{
    public function store(Request $request, Room $room, int $round): RedirectResponse
    {
        // ใช้บัญชีที่ล็อกอินค้นหาสมาชิก active ของห้องนี้
        $roomMember = $room->members()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless(
            $roomMember,
            $room->visibility === 'private' ? 404 : 403
        );

        // ป้องกันการส่ง ID รอบของห้องอื่น
        $round = $room->rounds()->findOrFail($round);

        $now = now();

        if (
            $round->status !== 'active'
            || $round->phase !== 'join'
            || $now->lt($round->join_starts_at)
            || $now->gte($round->review_starts_at)
        ) {
            return redirect()->route('rooms.rounds.show', [
                'room' => $room,
                'round' => $round,
            ])->with('warning', 'รอบนี้ยังไม่เปิดรับหรือปิดรับสมาชิกแล้ว');
        }

        $roundMember = $round->members()->firstOrCreate(
            ['room_member_id' => $roomMember->id],
            [
                'role' => $roomMember->role,
                'weight' => $roomMember->weight,
                'joined_at' => $now,
            ]
        );

        return redirect()->route('rooms.rounds.show', [
            'room' => $room,
            'round' => $round,
        ])->with(
            'success',
            $roundMember->wasRecentlyCreated
                ? 'เข้าร่วมรอบเรียบร้อยแล้ว'
                : 'คุณเข้าร่วมรอบนี้แล้ว'
        );
    }

    public function show()
    {

    }
}