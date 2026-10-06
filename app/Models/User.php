<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function getAttendanceStatusAttribute()
    {
        $attendanceRecord = $this->attendanceRecords()
            ->whereDate('date', today())
            ->first();

        if (! $attendanceRecord) {
            return '勤務外';
        }

        if ($attendanceRecord->clock_out) {
            return '退勤済';
        }

        $activeBreak = $attendanceRecord->breaks()
            ->whereNull('break_out')
            ->exists();

        if ($activeBreak) {
            return '休憩中';
        }

        return '出勤中';
    }
}
