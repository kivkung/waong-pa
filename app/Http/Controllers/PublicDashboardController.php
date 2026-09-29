<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $rooms = Room::query()
            ->select(['id', 'name', 'description', 'visibility'])
            ->where('visibility', 'public')
            ->orderByDesc('id')
            ->paginate(9, ['*'], 'public_page');

        $myRooms = null;

        // Logging in does not grant access to other people's private rooms.
        if ($request->user()) {
            $myRooms = Room::query()
                ->select(['id', 'name', 'description', 'visibility'])
                ->whereHas('members', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id)
                        ->where('status', 'active');
                })
                ->orderByDesc('id')
                ->paginate(9, ['*'], 'my_page');
        }

        return view('index', compact('rooms', 'myRooms'));
    }
}
