<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserBusySlotController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $weekStart = CarbonImmutable::parse($validated['week'] ?? now()->toDateString())
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->startOfDay();
        $weekEnd = $weekStart->addWeek();

        // ปฏิทินต้องได้กิจกรรมครบทั้งสัปดาห์ ไม่ขึ้นกับหน้าของรายการด้านล่าง
        $calendarActivities = $request->user()->busySlots()
            ->where('start_at', '<', $weekEnd)
            ->where('end_at', '>', $weekStart)
            ->orderBy('start_at')
            ->get();

        $activities = $request->user()->busySlots()
            ->orderBy('start_at')
            ->paginate(20)
            ->withQueryString();

        return view('activities.index', compact('activities', 'calendarActivities', 'weekStart', 'weekEnd'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->busySlots()->create($this->validated($request));

        return redirect()->route('activities.index')->with('success', 'เพิ่มกิจกรรมแล้ว');
    }

    public function edit(Request $request, int $activity): View
    {
        $activity = $request->user()->busySlots()->findOrFail($activity);

        return view('activities.edit', compact('activity'));
    }

    public function update(Request $request, int $activity): RedirectResponse
    {
        $activity = $request->user()->busySlots()->findOrFail($activity);
        $activity->update($this->validated($request));

        return redirect()->route('activities.index')->with('success', 'บันทึกกิจกรรมแล้ว สำเนาที่ดึงเข้ารอบแล้วจะไม่เปลี่ยน');
    }

    public function destroy(Request $request, int $activity): RedirectResponse
    {
        $request->user()->busySlots()->findOrFail($activity)->delete();

        return redirect()->route('activities.index')->with('success', 'ลบกิจกรรมแล้ว');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],
        ]);
    }
}
