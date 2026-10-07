<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<RoomMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(RoomMember::class);
    }

    /** @return HasMany<MeetingRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(MeetingRound::class);
    }
}
