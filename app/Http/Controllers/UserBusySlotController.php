<?php

namespace App\Http\Controllers;

use App\Models\BusySlotRecurrenceRule;
use App\Models\UserBusySlot;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $calendarActivities = collect();
        $calendarSeries = $request->user()->busySlots()->with('recurrenceRule')
            ->overlappingRange($weekStart, $weekEnd)->get();
        foreach ($calendarSeries as $activity) {
            foreach ($activity->occurrencesBetween($weekStart, $weekEnd) as $occurrence) {
                // สำเนาสำหรับแสดงผลเท่านั้น ไม่บันทึก occurrence เป็นแถวใหม่
                $calendarActivity = clone $activity;
                $calendarActivity->start_at = $occurrence['start_at'];
                $calendarActivity->end_at = $occurrence['end_at'];
                $calendarActivities->push($calendarActivity);
            }
        }
        $calendarActivities = $calendarActivities->sortBy('start_at')->values();

        $recurringActivities = $request->user()->busySlots()->with('recurrenceRule')
            ->whereNotNull('recurrence_rule_id')->orderBy('start_at')
            ->paginate(20, ['*'], 'recurring_page')->withQueryString();

        $activities = $request->user()->busySlots()->with('recurrenceRule')
            ->whereNull('recurrence_rule_id')
            ->orderBy('start_at')
            ->paginate(20, ['*'], 'activities_page')
            ->withQueryString();

        return view('activities.index', compact('activities', 'recurringActivities', 'calendarActivities', 'weekStart', 'weekEnd'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        DB::transaction(function () use ($request, $validated) {
            $activity = new UserBusySlot;
            $activity->user_id = $request->user()->id;
            $this->saveActivity($activity, $validated);
        });

        return redirect()->route('activities.index')->with('success', 'เพิ่มกิจกรรมแล้ว');
    }

    public function edit(Request $request, int $activity): View
    {
        $activity = $request->user()->busySlots()->with('recurrenceRule')->findOrFail($activity);

        return view('activities.edit', compact('activity'));
    }

    public function update(Request $request, int $activity): RedirectResponse
    {
        // ตรวจเจ้าของก่อน validate เพื่อไม่เปิดเผยข้อมูลของคนอื่น
        $request->user()->busySlots()->findOrFail($activity);
        $validated = $this->validated($request);
        DB::transaction(function () use ($request, $activity, $validated) {
            $activity = $request->user()->busySlots()->lockForUpdate()->findOrFail($activity);
            $this->saveActivity($activity, $validated);
        });

        return redirect()->route('activities.index')->with('success', 'บันทึกกิจกรรมแล้ว สำเนาที่ดึงเข้ารอบแล้วจะไม่เปลี่ยน');
    }

    public function destroy(Request $request, int $activity): RedirectResponse
    {
        DB::transaction(function () use ($request, $activity) {
            $activity = $request->user()->busySlots()->lockForUpdate()->findOrFail($activity);
            $rule = $activity->recurrenceRule;
            $activity->recurrenceRule()->dissociate();
            $activity->save();
            $activity->delete();
            if ($rule !== null) {
                $rule->delete();
            }
        });

        return redirect()->route('activities.index')->with('success', 'ลบกิจกรรมแล้ว');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],
            'frequency' => ['nullable', Rule::in(['none', 'weekly', 'monthly'])],
            // ขอบเขตชนิด integer ของฐานข้อมูล ไม่ใช่เพดานจำนวนครั้งทางธุรกิจ
            'occurrence_count' => ['exclude_unless:frequency,weekly,monthly', 'required', 'integer', 'min:2', 'max:2147483647'],
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function saveActivity(UserBusySlot $activity, array $validated): void
    {
        $activity->name = $validated['name'];
        $activity->start_at = $validated['start_at'];
        $activity->end_at = $validated['end_at'];
        $frequency = $validated['frequency'] ?? 'none';
        $rule = $activity->recurrenceRule;

        if ($frequency === 'weekly' || $frequency === 'monthly') {
            $count = (int) $validated['occurrence_count'];
            if ($count < 2) {
                throw ValidationException::withMessages(['occurrence_count' => 'จำนวนครั้งต้องไม่น้อยกว่า 2']);
            }
            if ($rule === null) {
                $rule = new BusySlotRecurrenceRule;
            }
            $rule->frequency = $frequency;
            $rule->occurrence_count = $count;
            $rule->save();
            $activity->recurrenceRule()->associate($rule);
            $activity->save();
        } else {
            $activity->recurrenceRule()->dissociate();
            $activity->save();
            if ($rule !== null) {
                $rule->delete();
            }
        }
    }
}
