<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_record_list_returns_data_and_meta(): void
    {
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '一覧テスト',
        ]);

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_attendance_record_detail_returns_user_breaks_and_applications(): void
    {
        $user = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '詳細テスト',
        ]);

        BreakTime::create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        Application::create([
            'attendance_record_id' => $record->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正申請',
            'is_approved' => false,
        ]);

        $response = $this->getJson(
            "/api/v1/attendance-records/{$record->id}"
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'breaks',
                    'applications',
                ],
            ]);
    }

    public function test_nonexistent_attendance_record_returns_404(): void
    {
        $response = $this->getJson(
            '/api/v1/attendance-records/999999'
        );

        $response->assertStatus(404)
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_authenticated_user_can_create_attendance_record(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API登録',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'comment' => 'API登録',
        ]);
    }

    public function test_invalid_create_returns_422_with_japanese_errors(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'user_id' => $user->id,
            'date' => '2026/10/01',
            'clock_in' => '09:00',
            'clock_out' => '08:00:00',
            'comment' => str_repeat('あ', 256),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'date',
                'clock_in',
                'comment',
            ])
            ->assertJsonPath(
                'errors.date.0',
                '勤怠日は YYYY-MM-DD 形式で指定してください。'
            )
            ->assertJsonPath(
                'errors.clock_in.0',
                '出勤時刻は HH:MM:SS 形式で指定してください。'
            )
            ->assertJsonPath(
                'errors.comment.0',
                '備考は 255 文字以内で入力してください。'
            );
    }

    public function test_authenticated_owner_can_update_attendance_record(): void
    {
        $user = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/v1/attendance-records/{$record->id}",
            [
                'comment' => '更新後',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.comment', '更新後');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'comment' => '更新後',
        ]);
    }

    public function test_update_nonexistent_attendance_record_returns_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson(
            '/api/v1/attendance-records/999999',
            [
                'comment' => '更新テスト',
            ]
        );

        $response->assertStatus(404)
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_authenticated_owner_can_delete_attendance_record(): void
    {
        $user = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '削除テスト',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$record->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $record->id,
        ]);
    }

    public function test_delete_nonexistent_attendance_record_returns_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            '/api/v1/attendance-records/999999'
        );

        $response->assertStatus(404)
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_unauthenticated_write_requests_return_401(): void
    {
        $user = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '認証テスト',
        ]);

        $postResponse = $this->postJson(
            '/api/v1/attendance-records',
            [
                'user_id' => $user->id,
                'date' => '2026-10-02',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]
        );

        $putResponse = $this->putJson(
            "/api/v1/attendance-records/{$record->id}",
            [
                'comment' => '未認証更新',
            ]
        );

        $deleteResponse = $this->deleteJson(
            "/api/v1/attendance-records/{$record->id}"
        );

        foreach ([
            $postResponse,
            $putResponse,
            $deleteResponse,
        ] as $response) {
            $response->assertStatus(401)
                ->assertExactJson([
                    'message' => 'Unauthenticated.',
                ]);
        }
    }

    public function test_other_user_cannot_update_or_delete_attendance_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $owner->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '権限テスト',
        ]);

        Sanctum::actingAs($otherUser);

        $updateResponse = $this->putJson(
            "/api/v1/attendance-records/{$record->id}",
            [
                'comment' => '他人による更新',
            ]
        );

        $updateResponse->assertStatus(403)
            ->assertExactJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $deleteResponse = $this->deleteJson(
            "/api/v1/attendance-records/{$record->id}"
        );

        $deleteResponse->assertStatus(403)
            ->assertExactJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'comment' => '権限テスト',
        ]);
    }
}