<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_correct_report_data(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 10, 5, 12, 0, 0)
        );

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->createAttendance(
            $user,
            '2026-09-01',
            '09:00:00',
            '18:00:00'
        );

        $this->createAttendance(
            $user,
            '2026-10-01',
            '09:30:00',
            '18:00:00'
        );

        $this->createAttendance(
            $user,
            '2026-10-02',
            '09:00:00',
            '17:00:00'
        );

        $this->createAttendance(
            $user,
            '2026-10-03',
            '08:00:00',
            '21:00:00'
        );

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk();

        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_work_minutes'] === 2070
                && $summary['total_overtime_minutes'] === 240
                && $summary['avg_work_minutes'] === 518;
        });

        $response->assertViewHas('monthlyTrend', function ($monthlyTrend) {
            $september = collect($monthlyTrend)->firstWhere(
                'month',
                '2026/09'
            );

            $october = collect($monthlyTrend)->firstWhere(
                'month',
                '2026/10'
            );

            return count($monthlyTrend) === 6
                && $september !== null
                && $september['work_minutes'] === 480
                && $september['overtime_minutes'] === 0
                && $october !== null
                && $october['work_minutes'] === 1590
                && $october['overtime_minutes'] === 240;
        });

        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    public function test_user_without_attendance_can_view_zero_report(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 10, 5, 12, 0, 0)
        );

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk();

        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);

        $response->assertViewHas('monthlyTrend', function ($monthlyTrend) {
            return count($monthlyTrend) === 6
                && collect($monthlyTrend)->every(
                    fn ($month) => $month['work_minutes'] === 0
                        && $month['overtime_minutes'] === 0
                );
        });

        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }

    private function createAttendance(
        User $user,
        string $date,
        string $clockIn,
        string $clockOut
    ): AttendanceRecord {
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'comment' => 'レポートテスト',
        ]);

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        return $attendance;
    }
}
