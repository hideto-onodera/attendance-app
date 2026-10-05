<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function createAttendance(User $user): AttendanceRecord
    {
        return AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前',
        ]);
    }

    public function test_clock_out_before_clock_in_is_invalid(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '18:00',
                'new_clock_out' => '09:00',
                'new_break_in' => [],
                'new_break_out' => [],
                'comment' => '修正申請',
            ]);

        $response->assertSessionHasErrors([
            'new_clock_in' => '出勤時間が不適切な値です',
        ]);
    }

    public function test_break_before_clock_in_is_invalid(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['08:00'],
                'new_break_out' => ['10:00'],
                'comment' => '修正申請',
            ]);

        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    public function test_break_after_clock_out_is_invalid(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['17:00'],
                'new_break_out' => ['19:00'],
                'comment' => '修正申請',
            ]);

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_comment_is_required(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '',
            ]);

        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }

    public function test_valid_correction_request_is_saved_as_pending(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '08:30',
                'new_clock_out' => '18:30',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '勤務時間を修正してください',
            ]);

        $response->assertRedirect('/attendance/detail/' . $attendance->id);

        $this->assertDatabaseHas('applications', [
            'attendance_record_id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '18:30:00',
            'comment' => '勤務時間を修正してください',
            'is_approved' => false,
        ]);

        $this->assertDatabaseHas('application_breaks', [
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }

    public function test_pending_application_is_displayed_in_users_application_list(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        Application::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '18:30:00',
            'comment' => '承認待ち確認テスト',
            'is_approved' => false,
        ]);

        $response = $this->actingAs($user)
            ->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認待ち');
        $response->assertSee($user->name);
        $response->assertSee('2026/10/01');
        $response->assertSee('承認待ち確認テスト');
    }

    public function test_approved_application_is_displayed_in_users_application_list(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        Application::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '18:30:00',
            'comment' => '承認済み確認テスト',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($user)
            ->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認済み');
        $response->assertSee($user->name);
        $response->assertSee('2026/10/01');
        $response->assertSee('承認済み確認テスト');
    }

    public function test_application_detail_link_points_to_attendance_detail(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        Application::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '18:30:00',
            'comment' => '詳細リンク確認テスト',
            'is_approved' => false,
        ]);

        $response = $this->actingAs($user)
            ->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee(
            '/attendance/detail/' . $attendance->id,
            false
        );

        $detailResponse = $this->actingAs($user)
            ->get('/attendance/detail/' . $attendance->id);

        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('勤怠詳細');
    }

    public function test_user_cannot_request_correction_for_other_users_attendance(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $attendance = $this->createAttendance($otherUser);

        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => [],
                'new_break_out' => [],
                'comment' => '他人の勤怠',
            ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('applications', [
            'attendance_record_id' => $attendance->id,
        ]);
    }
}