<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusySlotRecurrenceRule extends Model
{
    // อนุญาตให้บันทึกสองช่องนี้ด้วย create([...])
    protected $fillable = ['frequency', 'occurrence_count'];

    protected function casts(): array
    {
        // อ่านจำนวนครั้งออกมาเป็นจำนวนเต็ม
        return ['occurrence_count' => 'integer'];
    }

    /** @return HasOne<UserBusySlot, $this> */
    public function busySlot(): HasOne
    {
        // กฎหนึ่งรายการใช้กับกิจกรรมหนึ่งรายการ
        return $this->hasOne(UserBusySlot::class, 'recurrence_rule_id');
    }
}
