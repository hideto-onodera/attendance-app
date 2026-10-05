<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'admin_status' => true,
        ]);
    }

    private function createAttendance(
        User $user,
        string $date = '2026-10-01',
        string $clockIn = '09:00:00',
        string $clockOut = '18:00:00'
    ): AttendanceRecord {
        return AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'comment' => 'テスト勤怠',
        ]);
    }

    public function test_admin_attendance_list_displays_general_users(): void
    {
        $admin = $this->createAdmin();

        $user1 = User::factory()->create([
            'name' => '一般ユーザー1',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => '一般ユーザー2',
            'admin_status' => false,
        ]);

        $this->createAttendance($user1);
        $this->createAttendance($user2);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-10-01');

        $response->assertOk();
        $response->assertSee('一般ユーザー1');
        $response->assertSee('一般ユーザー2');
    }

    public function test_admin_attendance_list_displays_work_and_break_times(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '勤怠確認ユーザー',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-10-01');

        $response->assertOk();
        $response->assertSee('勤怠確認ユーザー');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('01:00');
        $response->assertSee('08:00');
    }

    public function test_admin_attendance_list_displays_current_date_by_default(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list');

        $response->assertOk();
        $response->assertSee('2026年10月05日の勤怠');
        $response->assertSee('2026/10/05');
    }

    public function test_admin_can_view_previous_day(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '前日確認ユーザー',
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-10-04',
            '09:01:00',
            '18:01:00'
        );

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-10-04');

        $response->assertOk();
        $response->assertSee('2026/10/04');
        $response->assertSee('前日確認ユーザー');
        $response->assertSee('09:01');
        $response->assertSee('18:01');
    }

    public function test_admin_can_view_next_day(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '翌日確認ユーザー',
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-10-06',
            '09:02:00',
            '18:02:00'
        );

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-10-06');

        $response->assertOk();
        $response->assertSee('2026/10/06');
        $response->assertSee('翌日確認ユーザー');
        $response->assertSee('09:02');
        $response->assertSee('18:02');
    }

    public function test_admin_can_view_attendance_detail(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '詳細確認ユーザー',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/' . $attendance->id);

        $response->assertOk();
        $response->assertSee('詳細確認ユーザー');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
        $response->assertSee('テスト勤怠');
    }

    public function test_admin_can_update_attendance(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['admin_status' => false]);
        $attendance = $this->createAttendance($user);

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
                'new_clock_in' => '08:30',
                'new_clock_out' => '18:30',
                'new_break_in' => ['12:15'],
                'new_break_out' => ['13:15'],
                'comment' => '管理者による修正',
            ]);

        $response->assertRedirect(
            '/admin/attendance/' . $attendance->id
        );

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '18:30:00',
            'comment' => '管理者による修正',
        ]);

        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:15:00',
            'break_out' => '13:15:00',
        ]);
    }

    public function test_admin_update_rejects_invalid_clock_times(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['admin_status' => false]);
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
                'new_clock_in' => '18:00',
                'new_clock_out' => '09:00',
                'new_break_in' => [],
                'new_break_out' => [],
                'comment' => '不正な修正',
            ]);

        $response->assertSessionHasErrors([
            'new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_admin_update_rejects_invalid_break_start(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['admin_status' => false]);
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['08:00'],
                'new_break_out' => ['10:00'],
                'comment' => '休憩開始確認',
            ]);

        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    public function test_admin_update_rejects_invalid_break_end(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['admin_status' => false]);
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['17:00'],
                'new_break_out' => ['19:00'],
                'comment' => '休憩終了確認',
            ]);

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_admin_update_requires_comment(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['admin_status' => false]);
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => [],
                'new_break_out' => [],
                'comment' => '',
            ]);

        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }

    public function test_admin_staff_list_displays_general_users_only(): void
    {
        $admin = $this->createAdmin();

        User::factory()->create([
            'name' => 'スタッフA',
            'email' => 'staff-a@example.com',
            'admin_status' => false,
        ]);

        User::factory()->create([
            'name' => 'スタッフB',
            'email' => 'staff-b@example.com',
            'admin_status' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/staff/list');

        $response->assertOk();
        $response->assertSee('スタッフA');
        $response->assertSee('staff-a@example.com');
        $response->assertSee('スタッフB');
        $response->assertSee('staff-b@example.com');
        $response->assertDontSee($admin->email);
    }

    public function test_admin_can_view_staff_monthly_attendance(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '月別確認ユーザー',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-15',
            '09:05:00',
            '18:05:00'
        );

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(
                '/admin/attendance/staff/'
                . $user->id
                . '?date=2026-10'
            );

        $response->assertOk();
        $response->assertSee('月別確認ユーザー');
        $response->assertSee('2026/10');
        $response->assertSee('10/15');
        $response->assertSee('09:05');
        $response->assertSee('18:05');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
    }

    public function test_admin_can_view_previous_month_for_staff(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '前月スタッフ',
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-09-15',
            '09:10:00',
            '18:10:00'
        );

        $response = $this->actingAs($admin)
            ->get(
                '/admin/attendance/staff/'
                . $user->id
                . '?date=2026-09'
            );

        $response->assertOk();
        $response->assertSee('2026/09');
        $response->assertSee('09/15');
        $response->assertSee('09:10');
        $response->assertSee('18:10');
    }

    public function test_admin_can_view_next_month_for_staff(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '翌月スタッフ',
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-11-15',
            '09:20:00',
            '18:20:00'
        );

        $response = $this->actingAs($admin)
            ->get(
                '/admin/attendance/staff/'
                . $user->id
                . '?date=2026-11'
            );

        $response->assertOk();
        $response->assertSee('2026/11');
        $response->assertSee('11/15');
        $response->assertSee('09:20');
        $response->assertSee('18:20');
    }

    public function test_staff_attendance_detail_link_points_to_admin_detail(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '詳細リンクスタッフ',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-15'
        );

        $response = $this->actingAs($admin)
            ->get(
                '/admin/attendance/staff/'
                . $user->id
                . '?date=2026-10'
            );

        $response->assertOk();
        $response->assertSee(
            '/admin/attendance/' . $attendance->id,
            false
        );

        $detailResponse = $this->actingAs($admin)
            ->get('/admin/attendance/' . $attendance->id);

        $detailResponse->assertOk();
        $detailResponse->assertSee('詳細リンクスタッフ');
    }
}