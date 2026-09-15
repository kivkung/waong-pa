<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Waongpa\PersonalEvent;
use App\Services\Waongpa\Settings;
use App\Services\Waongpa\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WaongpaDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeder is local/testing only.');
        }
        if (User::whereIn('email', ['owner@waongpa.test', 'student@waongpa.test'])->exists()) {
            $this->command?->warn('Demo accounts already exist; skipped without overwriting passwords or data.');

            return;
        }
        $owner = User::create(['name' => 'อาจารย์ตัวอย่าง', 'email' => 'owner@waongpa.test', 'password' => Hash::make('WaongpaDemo123!'), 'email_verified_at' => now()]);
        $student = User::create(['name' => 'นักศึกษาตัวอย่าง', 'email' => 'student@waongpa.test', 'password' => Hash::make('WaongpaDemo123!'), 'email_verified_at' => now()]);
        // Fillable in the official starter deliberately excludes verified_at; assign it explicitly.
        foreach ([$owner, $student] as $user) {
            $user->email_verified_at = now();
            $user->save();
        }
        $settings = Settings::defaults();
        PersonalEvent::create(['user_id' => $student->id, 'title' => 'พักเที่ยง', 'starts_at' => $settings['search_start_date'].' 12:00:00', 'ends_at' => $settings['search_start_date'].' 13:00:00']);
        $workflow = app(Workflow::class);
        $room = $workflow->createRoom($owner, ['name' => 'ห้องนัดหมายตัวอย่าง', 'description' => 'ทดลองยืนยันตาราง หาเวลาว่าง โหวต และนัดซ้ำ', 'visibility' => 'public', 'owner_role' => 'professor'], $settings);
        $workflow->joinRoom($student, $room->join_code);
        $round = $room->rounds()->first();
        foreach ([$owner, $student] as $user) {
            $workflow->confirmSchedule($user, $round->id, 1);
        }
        $this->command?->info('Demo room ready. Sign in as owner and skip to Review to calculate.');
    }
}
