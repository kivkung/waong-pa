<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusySlotRecurrenceRule extends Model
{
    protected $fillable = ['frequency', 'occurrence_count'];

    protected function casts(): array
    {
        return ['occurrence_count' => 'integer'];
    }

    /** @return HasOne<UserBusySlot, $this> */
    public function busySlot(): HasOne
    {
        return $this->hasOne(UserBusySlot::class, 'recurrence_rule_id');
    }
}
