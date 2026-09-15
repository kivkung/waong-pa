<?php

namespace App\Models\Waongpa;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PersonalEvent extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
