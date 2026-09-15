<?php

namespace App\Http\Controllers\Waongpa;

use App\Models\Waongpa\MeetingRound;
use App\Services\Waongpa\Access;
use Illuminate\Http\Request;

class DownloadMeeting
{
    public function __invoke(Request $request, int $roundId, Access $access)
    {
        $round = MeetingRound::with('room', 'result.slot')->findOrFail($roundId);
        abort_unless($access->isMember($request->user(), $round->room), 403);
        abort_unless($round->phase === 'final' && $round->result, 404);
        $slot = $round->result->slot;
        $escape = fn ($s) => str_replace(['\\', "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $s);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Waongpa//Meeting//TH', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
            'UID:waongpa-round-'.$round->id.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            'DTSTAMP:'.$round->result->finalized_at->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$slot->starts_at->utc()->format('Ymd\THis\Z'), 'DTEND:'.$slot->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$escape($round->room->name.' รอบที่ '.$round->round_no), 'END:VEVENT', 'END:VCALENDAR'];
        // Fold at 75 octets without splitting a UTF-8 character (RFC 5545).
        $folded = [];
        foreach ($lines as $line) {
            $limit = 75;
            while (strlen($line) > $limit) {
                $part = mb_strcut($line, 0, $limit, 'UTF-8');
                $folded[] = $part;
                $line = ' '.substr($line, strlen($part));
            }
            $folded[] = $line;
        }

        return response(implode("\r\n", $folded)."\r\n", 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="waongpa-round-'.$round->id.'.ics"']);
    }
}
