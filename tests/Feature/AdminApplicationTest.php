<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationBreak;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_pending_application_in_list(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '申請ユーザー',
            'admin_status' => false,
        ]);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前',
        ]);

        Application::create([
            'attendance_record_id' => $attendanceRecord->id,
            'clock_in' => '08:30:00',
            'clock_out' => '18:30:00',
            'comment' => '未承認申請テスト',
            'is_approved' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee('承認待ち');
        $response->assertSee('申請ユーザー');
        $response->assertSee('2026/10/01');
        $response->assertSee('未承認申請テスト');
    }

    public function test_admin_can_view_approved_application_in_list(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '承認済みユーザー',
            'admin_status' => false,
        ]);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前',
        ]);

        Application::create([
            'attendance_record_id' => $attendanceRecord->id,
            'clock_in' => '08:45:00',
            'clock_out' => '18:15:00',
            'comment' => '承認済み申請テスト',
            'is_approved' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee('承認済み');
        $response->assertSee('承認済みユーザー');
        $response->assertSee('2026/10/02');
        $response->assertSee('承認済み申請テスト');
    }

    public function test_admin_can_view_application_detail(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '詳細確認ユーザー',
            'admin_status' => false,
        ]);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-03',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前',
        ]);

        $application = Application::create([
            'attendance_record_id' => $attendanceRecord->id,
            'clock_in' => '08:30:00',
            'clock_out' => '18:30:00',
            'comment' => '詳細画面テスト',
            'is_approved' => false,
        ]);

        ApplicationBreak::create([
            'application_id' => $application->id,
            'break_in' => '12:15:00',
            'break_out' => '13:15:00',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/stamp_correction_request/approve/'.$application->id);

        $response->assertOk();
        $response->assertSee('詳細確認ユーザー');
        $response->assertSee('2026年');
        $response->assertSee('10月3日');
        $response->assertSee('08:30');
        $response->assertSee('18:30');
        $response->assertSee('12:15');
        $response->assertSee('13:15');
        $response->assertSee('詳細画面テスト');
        $response->assertSee('承認');
    }

    public function test_admin_can_approve_application_and_update_attendance(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-04',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前',
        ]);

        BreakTime::create([
            'attendance_record_id' => $attendanceRecord->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $application = Application::create([
            'attendance_record_id' => $attendanceRecord->id,
            'clock_in' => '08:30:00',
            'clock_out' => '19:00:00',
            'comment' => '承認後コメント',
            'is_approved' => false,
        ]);

        ApplicationBreak::create([
            'application_id' => $application->id,
            'break_in' => '12:30:00',
            'break_out' => '13:30:00',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/stamp_correction_request/approve/'.$application->id);

        $response->assertRedirect(
            route(
                'admin.stamp-correction-request.detail',
                ['id' => $application->id]
            )
        );

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'is_approved' => true,
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '08:30:00',
            'clock_out' => '19:00:00',
            'comment' => '承認後コメント',
        ]);

        $this->assertDatabaseMissing('breaks', [
            'attendance_record_id' => $attendanceRecord->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendanceRecord->id,
            'break_in' => '12:30:00',
            'break_out' => '13:30:00',
        ]);
    }

    public function test_approved_application_detail_displays_approved_status(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-05',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前',
        ]);

        $application = Application::create([
            'attendance_record_id' => $attendanceRecord->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:30:00',
            'comment' => '承認済み詳細テスト',
            'is_approved' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/stamp_correction_request/approve/'.$application->id);

        $response->assertOk();
        $response->assertSee('承認済み');
        $response->assertDontSee(
            '<button class="applied-form__button--submit"',
            false
        );
    }
}
