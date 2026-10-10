<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoundVote extends Model
{
    protected $fillable = ['round_member_id', 'round_candidate_id'];
}
