<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_attendance_list_displays_logged_in_users_records(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0));

        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'テスト備考',
        ]);

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        $response->assertStatus(200);
        $response->assertSee('10/01');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
    }

    public function test_attendance_list_does_not_display_other_users_records(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0));

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $otherUser->id,
            'date' => '2026-10-01',
            'clock_in' => '08:31:00',
            'clock_out' => '17:29:00',
            'comment' => '他ユーザーの備考',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        $response->assertStatus(200);
        $response->assertDontSee('08:31');
        $response->assertDontSee('17:29');
    }

    public function test_current_month_is_displayed_when_opening_attendance_list(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0));

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('2026/10');
    }

    public function test_previous_month_can_be_displayed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0));

        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:01:00',
            'clock_out' => '18:01:00',
            'comment' => '前月データ',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-09');

        $response->assertStatus(200);
        $response->assertSee('2026/09');
        $response->assertSee('09/15');
        $response->assertSee('09:01');
        $response->assertSee('18:01');
    }

    public function test_next_month_can_be_displayed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0));

        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '09:02:00',
            'clock_out' => '18:02:00',
            'comment' => '翌月データ',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-11');

        $response->assertStatus(200);
        $response->assertSee('2026/11');
        $response->assertSee('11/10');
        $response->assertSee('09:02');
        $response->assertSee('18:02');
    }

    public function test_attendance_detail_displays_selected_date(): void
    {
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '日付確認テスト',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/detail/'.$attendance->id);

        $response->assertStatus(200);
        $response->assertSee('2026年');
        $response->assertSee('10月1日');
    }

    public function test_attendance_detail_displays_selected_record(): void
    {
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '詳細確認テスト',
        ]);

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/detail/'.$attendance->id);

        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
        $response->assertSee('詳細確認テスト');
    }
}
