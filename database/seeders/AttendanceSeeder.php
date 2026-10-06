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
            if ($user->email === 'user1@example.com') {
                $this->createUser1Attendance($user);

                continue;
            }

            $this->createNormalAttendance($user);
        }
    }

    /**
     * user1 のレポート確認用勤怠データを作成する。
     */
    private function createUser1Attendance(User $user): void
    {
        for ($monthsAgo = 5; $monthsAgo >= 1; $monthsAgo--) {
            $month = Carbon::today()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
            $dates = $this->getWeekdays($month, 15);

            foreach ($dates as $date) {
                $this->createAttendance(
                    $user,
                    $date,
                    '09:00:00',
                    '18:00:00'
                );
            }
        }

        $currentMonth = Carbon::today()->startOfMonth();
        $dates = $this->getWeekdays($currentMonth, 17);

        foreach ($dates as $index => $date) {
            if ($index < 10) {
                $this->createAttendance(
                    $user,
                    $date,
                    '09:00:00',
                    '18:00:00'
                );

                continue;
            }

            if ($index < 13) {
                $this->createAttendance(
                    $user,
                    $date,
                    '09:00:00',
                    '20:00:00'
                );

                continue;
            }

            if ($index < 15) {
                $this->createAttendance(
                    $user,
                    $date,
                    '09:30:00',
                    '18:00:00'
                );

                continue;
            }

            if ($index === 15) {
                $this->createAttendance(
                    $user,
                    $date,
                    '09:00:00',
                    '17:00:00'
                );

                continue;
            }

            $this->createAttendance(
                $user,
                $date,
                '08:00:00',
                '21:00:00'
            );
        }
    }

    /**
     * user2・user3 の通常勤務データを作成する。
     */
    private function createNormalAttendance(User $user): void
    {
        for ($daysAgo = 1; $daysAgo <= 10; $daysAgo++) {
            $date = Carbon::today()->subDays($daysAgo);

            if ($date->isWeekend()) {
                continue;
            }

            $this->createAttendance(
                $user,
                $date,
                '09:00:00',
                '18:00:00'
            );
        }
    }

    /**
     * 指定月から平日を指定件数取得する。
     */
    private function getWeekdays(Carbon $month, int $count): array
    {
        $dates = [];
        $date = $month->copy();

        while (count($dates) < $count) {
            if (! $date->isWeekend()) {
                $dates[] = $date->copy();
            }

            $date->addDay();
        }

        return $dates;
    }

    /**
     * 勤怠と固定休憩を作成する。
     */
    private function createAttendance(
        User $user,
        Carbon $date,
        string $clockIn,
        string $clockOut
    ): void {
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'comment' => null,
        ]);

        BreakTime::create([
            'attendance_record_id' => $attendanceRecord->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }
}
