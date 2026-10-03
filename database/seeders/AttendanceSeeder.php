<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereIn('email', [
            'user1@example.com',
            'user2@example.com',
            'user3@example.com',
        ])->get();

        foreach ($users as $user) {
            for ($daysAgo = 1; $daysAgo <= 10; $daysAgo++) {
                $date = Carbon::today()->subDays($daysAgo);

                if ($date->isWeekend()) {
                    continue;
                }

                $attendanceRecord = AttendanceRecord::create([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                    'comment' => null,
                ]);

                BreakTime::create([
                    'attendance_record_id' => $attendanceRecord->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);
            }
        }
    }
}