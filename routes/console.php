<?php

use App\Actions\ProcessMeetingRound;
use App\Models\MeetingRound;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rounds:process', function () {
    $failed = false;
    foreach (MeetingRound::query()->where('status', 'active')->whereHas('room')->cursor() as $round) {
        try {
            app(ProcessMeetingRound::class)->handle($round->id);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Round '.$round->id.': '.$exception->getMessage());
            $failed = true;
        }
    }

    return $failed ? 1 : 0;
})->purpose('Process round snapshots, availability ranking, voting and final results');

Schedule::command('rounds:process')->everyMinute()->withoutOverlapping();

// คำสั่งลัดสำหรับทดสอบ โดยแก้เวลาของรอบแล้วให้ระบบประมวลผลตามปกติ
Artisan::command('rounds:advance {round : ID ของรอบ} {stage : voting หรือ final}', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('คำสั่งนี้ใช้ได้เฉพาะเครื่องพัฒนา (APP_ENV=local) หรือการทดสอบ');

        return 1;
    }

    $stage = $this->argument('stage');
    if ($stage !== 'voting' && $stage !== 'final') {
        $this->error('ระบุขั้นตอนเป็น voting หรือ final');

        return 1;
    }

    $roundId = $this->argument('round');
    if (is_int($roundId)) {
        $roundId = (string) $roundId;
    }
    if (! is_string($roundId) || ! ctype_digit($roundId) || (int) $roundId < 1) {
        $this->error('ID ของรอบต้องเป็นจำนวนเต็มบวก');

        return 1;
    }

    $advanced = DB::transaction(function () use ($roundId, $stage) {
        $round = MeetingRound::query()->lockForUpdate()->find($roundId);
        if ($round === null || $round->status !== 'active' || ! $round->room()->exists()) {
            $this->error('ไม่พบรอบที่กำลังดำเนินการ');

            return false;
        }

        if ($stage === 'voting') {
            if ($round->phase === 'voting') {
                $this->error('รอบนี้เปิดโหวตแล้ว ใช้ final เพื่อปิดโหวต');

                return false;
            }

            // ให้ผ่านเวลาปิดรับและเวลารอเก็บสำเนาแล้ว
            $round->review_starts_at = now()->subMinutes((int) config('rounds.snapshot_delay_minutes') + 1);
            if ($round->join_starts_at > $round->review_starts_at) {
                $round->join_starts_at = $round->review_starts_at->subMinute();
            }
            $round->voting_starts_at = now();
            $round->final_starts_at = now()->addHour();
        } else {
            if ($round->phase !== 'voting') {
                $this->error('ต้องเปิดโหวตด้วย voting ก่อน แล้วจึงใช้ final');

                return false;
            }
            $round->final_starts_at = now();
        }

        $round->save();

        return true;
    });

    if (! $advanced) {
        return 1;
    }

    app(ProcessMeetingRound::class)->handle((int) $roundId);
    $this->info('อัปเดตรอบ '.$roundId.' แล้ว กรุณารีเฟรชหน้ารายละเอียดรอบ');

    return 0;
})->purpose('ข้ามเวลาเพื่อทดสอบเปิดโหวตหรือสรุปผลในเครื่องพัฒนา');
