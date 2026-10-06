<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'clock_in',
        'clock_out',
        'comment',
        'is_approved',
    ];

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function applicationBreaks()
    {
        return $this->hasMany(ApplicationBreak::class);
    }
}
