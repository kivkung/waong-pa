<?php

namespace App\Http\Controllers;

use App\Models\MeetingRound;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
            $roomMember !== null,
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

    public function edit(Request $request, Room $room, int $round): View|RedirectResponse
    {
        abort_unless((int) $room->owner_id === (int) $request->user()->id, $room->visibility === 'private' ? 404 : 403);
        $round = $room->rounds()->findOrFail($round);
        if (! $this->isOpen($round)) {
            return redirect()->route('rooms.rounds.show', [$room, $round])
                ->with('warning', 'แก้ไขสมาชิกได้เฉพาะช่วงเปิดรับของเฟส Join');
        }

        $joinedIds = $round->members()->pluck('room_member_id')->all();
        $members = $room->members()->with('user')
            ->where(function ($query) use ($joinedIds) {
                $query->where('status', 'active')->orWhereIn('id', $joinedIds);
            })->get()->sortBy('user.name')->sortBy(fn ($member): int => in_array($member->id, $joinedIds) ? 0 : 1);

        return view('rooms.rounds.members.edit', compact('room', 'round', 'members', 'joinedIds'));
    }

    public function update(Request $request, Room $room, int $round): RedirectResponse
    {
        abort_unless((int) $room->owner_id === (int) $request->user()->id, $room->visibility === 'private' ? 404 : 403);
        $round = $room->rounds()->findOrFail($round);

        return DB::transaction(function () use ($request, $room, $round): RedirectResponse {
            $round = $room->rounds()->lockForUpdate()->findOrFail($round->id);
            if (! $this->isOpen($round)) {
                return redirect()->route('rooms.rounds.show', [$room, $round])
                    ->with('warning', 'แก้ไขสมาชิกได้เฉพาะช่วงเปิดรับของเฟส Join');
            }

            $joinedIds = $round->members()->pluck('room_member_id')->all();
            $validated = $request->validate([
                'member_ids' => ['sometimes', 'array'],
                'member_ids.*' => ['required', 'integer', 'distinct', Rule::exists('room_members', 'id')
                    ->where('room_id', $room->id)->where(function ($query) use ($joinedIds) {
                        $query->where('status', 'active')->orWhereIn('id', $joinedIds);
                    })],
            ]);
            $members = $room->members()
                ->whereIn('id', $validated['member_ids'] ?? [])->lockForUpdate()->get();
            $removed = $round->members()->whereNotIn('room_member_id', $members->modelKeys())->delete();
            $added = 0;
            foreach ($members as $member) {
                $entry = $round->members()->firstOrCreate(
                    ['room_member_id' => $member->id],
                    ['role' => $member->role, 'weight' => $member->weight, 'joined_at' => now()],
                );
                $added += $entry->wasRecentlyCreated ? 1 : 0;
            }

            return redirect()->route('rooms.rounds.show', [$room, $round])
                ->with('success', "บันทึกสมาชิกในรอบแล้ว เพิ่ม {$added} คน นำออก {$removed} คน");
        });
    }

    private function isOpen(MeetingRound $round): bool
    {
        $now = now();

        return $round->status === 'active' && $round->phase === 'join'
            && $now->gte($round->join_starts_at) && $now->lt($round->review_starts_at);
    }
}
