<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpParser\Node\Expr\FuncCall;

class RoomMemberController extends Controller
{
    public function edit(Request $request, Room $room, int $member): View
    {
        // Do not reveal private rooms to someone who is not their owner.
        abort_unless((int) $room->owner_id === (int) $request->user()->id, $room->visibility === 'private' ? 404 : 403);

        // Looking through this room prevents editing a member from another room.
        $member = $room->members()->where('status', 'active')->with('user')->findOrFail($member);

        return view('rooms.members.edit', compact('room', 'member'));
    }

    public function update(Request $request, Room $room, int $member): RedirectResponse
    {
        abort_unless((int) $room->owner_id === (int) $request->user()->id, $room->visibility === 'private' ? 404 : 403);
        $member = $room->members()->where('status', 'active')->findOrFail($member);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['student', 'professor'])],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
        ], [
            'role.required' => 'กรุณาเลือกบทบาท',
            'role.in' => 'บทบาทต้องเป็น student หรือ professor',
            'weight.required' => 'กรุณากรอกน้ำหนัก',
            'weight.numeric' => 'น้ำหนักต้องเป็นตัวเลข',
            'weight.min' => 'น้ำหนักต้องไม่น้อยกว่า 0.01',
            'weight.max' => 'น้ำหนักต้องไม่เกิน 100',
            'weight.decimal' => 'น้ำหนักมีทศนิยมได้ไม่เกิน 2 ตำแหน่ง',
        ]);

        // Explicit fields keep ownership, membership, and status out of this form.
        $member->role = $validated['role'];
        $member->weight = $validated['weight'];
        $member->save();

        return redirect()->route('rooms.show', $room)
            ->with('success', 'บันทึกบทบาทและน้ำหนักสมาชิกแล้ว');
    }
    
    public function left_members(Request $request, Room $room, int $member): RedirectResponse
    {
         $member = $room->members()
            ->where('status', 'active')
            ->with('user')
            ->findOrFail($member);

        $isSelf = (int) $member->user_id === (int) $request->user()->id;
        $isOwner = (int) $room->owner_id === (int) $request->user()->id;
        
        abort_unless(
            $isSelf || $isOwner,
            $room->visibility === 'private' ? 404 : 403
        );
        abort_if((int) $member->user_id === (int) $room->owner_id, 403);

        $member->status = $isSelf ? 'left' : 'removed';
        $member->left_at = now();
        $member->save();

        return $isSelf 
            ? redirect()->route('home')->with('success', 'คุณออกจากสมาชิกห้อง ' . $room->name . ' แล้ว') 
            : redirect()->route('rooms.show', $room)->with('success', 'ลบผู้ใช้ ' .$member->user->name. ' ออกจากสมาชิกห้องแล้ว');
    }
}
