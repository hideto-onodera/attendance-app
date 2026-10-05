<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_current_date_and_time_are_displayed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 9, 30, 0));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('2026年10月4日');
        $response->assertSee('09:30');
    }

    public function test_status_is_off_duty_before_clocking_in(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    public function test_status_is_working_after_clocking_in(): void
    {
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    public function test_status_is_on_break_during_break(): void
    {
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
        ]);

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    public function test_user_can_clock_in(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 9, 0, 0));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-10-04',
            'clock_in' => '09:00:00',
        ]);
    }

    public function test_clock_in_button_is_not_displayed_after_clocking_in(): void
    {
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertDontSee('value="clock_in"', false);
    }

    public function test_user_can_start_break(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0));

        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-04',
            'clock_in' => '09:00:00',
        ]);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
        ]);
    }

    public function test_user_can_end_break(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0));

        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-04',
            'clock_in' => '09:00:00',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 10, 4, 13, 0, 0));

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }

    public function test_user_can_take_break_multiple_times(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 9, 0, 0));

        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-04',
            'clock_in' => '09:00:00',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 10, 4, 13, 0, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 10, 4, 15, 0, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 10, 4, 15, 15, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        $this->assertDatabaseCount('breaks', 2);

        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '15:00:00',
            'break_out' => '15:15:00',
        ]);
    }

    public function test_user_can_clock_out(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 18, 0, 0));

        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-04',
            'clock_in' => '09:00:00',
        ]);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_status_is_finished_after_clocking_out(): void
    {
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }
}