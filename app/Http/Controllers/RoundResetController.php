<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Rules\RoundParameters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoundResetController extends Controller
{
    public function edit(Request $request, Room $room, int $round): View
    {
        abort_unless((int) $room->owner_id === (int) $request->user()->id, $room->visibility === 'private' ? 404 : 403);
        $round = $room->rounds()->where('status', 'active')->findOrFail($round);

        return view('rooms.rounds.reset', compact('room', 'round'));
    }

    public function update(Request $request, Room $room, int $round): RedirectResponse
    {
        abort_unless((int) $room->owner_id === (int) $request->user()->id, $room->visibility === 'private' ? 404 : 403);

        return DB::transaction(function () use ($request, $room, $round) {
            $round = $room->rounds()->lockForUpdate()->where('status', 'active')->findOrFail($round);
            $request->validate(['confirm_reset' => ['accepted']]);
            $validated = RoundParameters::validate($request);
            foreach ($validated as $field => $value) {
                $round->setAttribute($field, $value);
            }
            $round->min_professors = $validated['professor_rule'] === 'all' ? null : $validated['min_professors'];
            $round->phase = 'join';
            $round->snapshot_taken_at = null;
            $round->candidates_generated_at = null;
            $round->candidates()->delete();
            foreach ($round->members()->get() as $member) {
                $member->busyPeriods()->delete();
            }
            $round->save();

            return redirect()->route('rooms.rounds.show', [$room, $round])
                ->with('success', 'รีเซ็ตรอบกลับ Join แล้ว สำเนาตารางของทุกคนถูกล้าง ระบบจะดึงใหม่ตามเวลาที่กำหนด');
        });
    }
}
