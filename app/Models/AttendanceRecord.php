<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks()
    {
        return $this->hasMany(BreakTime::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function getTotalBreakTimeAttribute(): int
    {
        return $this->breaks->sum(function ($break) {
            if (!$break->break_in || !$break->break_out) {
                return 0;
            }

            return Carbon::parse($break->break_in)
                ->diffInMinutes(Carbon::parse($break->break_out));
        });
    }

    public function getTotalTimeAttribute(): int
    {
        if (!$this->clock_in || !$this->clock_out) {
            return 0;
        }

        $elapsedMinutes = Carbon::parse($this->clock_in)
            ->diffInMinutes(Carbon::parse($this->clock_out));

        return max(
            0,
            $elapsedMinutes - $this->total_break_time
        );
    }
}