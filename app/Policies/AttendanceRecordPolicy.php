<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * Allow administrators to perform all actions.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->admin_status) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can update the attendance record.
     */
    public function update(
        User $user,
        AttendanceRecord $attendanceRecord
    ): bool {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * Determine whether the user can delete the attendance record.
     */
    public function delete(
        User $user,
        AttendanceRecord $attendanceRecord
    ): bool {
        return $user->id === $attendanceRecord->user_id;
    }
}