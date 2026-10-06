<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $now = now();

        $formattedDate = $now->format('Y年n月j日');
        $formattedTime = $now->format('H:i');

        return view('user.attendance-register', compact(
            'user',
            'formattedDate',
            'formattedTime'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $action = $request->input('action');

        if ($action === 'clock_in') {
            AttendanceRecord::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => today()->toDateString(),
                ],
                [
                    'clock_in' => now()->format('H:i:s'),
                ]
            );
        }

        if ($action === 'break_in') {
            $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
                ->whereDate('date', today())
                ->first();

            if ($attendanceRecord) {
                BreakTime::create([
                    'attendance_record_id' => $attendanceRecord->id,
                    'break_in' => now()->format('H:i:s'),
                    'break_out' => null,
                ]);
            }
        }

        if ($action === 'break_out') {
            $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
                ->whereDate('date', today())
                ->first();

            if ($attendanceRecord) {
                $break = $attendanceRecord->breaks()
                    ->whereNull('break_out')
                    ->latest()
                    ->first();

                if ($break) {
                    $break->update([
                        'break_out' => now()->format('H:i:s'),
                    ]);
                }
            }
        }

        if ($action === 'clock_out') {
            $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
                ->whereDate('date', today())
                ->whereNull('clock_out')
                ->first();

            if ($attendanceRecord) {
                $attendanceRecord->update([
                    'clock_out' => now()->format('H:i:s'),
                ]);
            }
        }

        return redirect('/attendance');
    }

    public function list(Request $request)
    {
        $user = $request->user();

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfMonth()
            : now()->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->orderBy('date')
            ->get();

        $formattedAttendanceRecords = $attendanceRecords->map(function ($attendanceRecord) {
            $totalBreakMinutes = $attendanceRecord->breaks->sum(function ($break) {
                if (! $break->break_in || ! $break->break_out) {
                    return 0;
                }

                return Carbon::parse($break->break_in)
                    ->diffInMinutes(Carbon::parse($break->break_out));
            });

            $totalBreakTime = null;
            $totalTime = null;

            if ($attendanceRecord->clock_in && $attendanceRecord->clock_out) {
                $workMinutes = Carbon::parse($attendanceRecord->clock_in)
                    ->diffInMinutes(Carbon::parse($attendanceRecord->clock_out));

                $totalWorkMinutes = max(0, $workMinutes - $totalBreakMinutes);

                $totalBreakTime = sprintf(
                    '%02d:%02d',
                    intdiv($totalBreakMinutes, 60),
                    $totalBreakMinutes % 60
                );

                $totalTime = sprintf(
                    '%02d:%02d',
                    intdiv($totalWorkMinutes, 60),
                    $totalWorkMinutes % 60
                );
            }

            return [
                'id' => $attendanceRecord->id,
                'date' => Carbon::parse($attendanceRecord->date)->format('m/d'),
                'clock_in' => $attendanceRecord->clock_in
                    ? Carbon::parse($attendanceRecord->clock_in)->format('H:i')
                    : '',
                'clock_out' => $attendanceRecord->clock_out
                    ? Carbon::parse($attendanceRecord->clock_out)->format('H:i')
                    : '',
                'total_break_time' => $totalBreakTime,
                'total_time' => $totalTime,
            ];
        });

        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    public function report(Request $request)
    {
        $user = $request->user();

        $startMonth = now()->subMonthsNoOverflow(5)->startOfMonth();
        $endMonth = now()->endOfMonth();

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startMonth->toDateString(),
                $endMonth->toDateString(),
            ])
            ->orderBy('date')
            ->get();

        $calculatedRecords = $attendanceRecords->map(function ($attendanceRecord) {
            if (! $attendanceRecord->clock_in || ! $attendanceRecord->clock_out) {
                return [
                    'date' => Carbon::parse($attendanceRecord->date),
                    'clock_in' => $attendanceRecord->clock_in,
                    'clock_out' => $attendanceRecord->clock_out,
                    'work_minutes' => 0,
                    'overtime_minutes' => 0,
                ];
            }

            $breakMinutes = $attendanceRecord->breaks->sum(function ($break) {
                if (! $break->break_in || ! $break->break_out) {
                    return 0;
                }

                return Carbon::parse($break->break_in)
                    ->diffInMinutes(Carbon::parse($break->break_out));
            });

            $elapsedMinutes = Carbon::parse($attendanceRecord->clock_in)
                ->diffInMinutes(Carbon::parse($attendanceRecord->clock_out));

            $workMinutes = max(0, $elapsedMinutes - $breakMinutes);

            return [
                'date' => Carbon::parse($attendanceRecord->date),
                'clock_in' => $attendanceRecord->clock_in,
                'clock_out' => $attendanceRecord->clock_out,
                'work_minutes' => $workMinutes,
                'overtime_minutes' => max(0, $workMinutes - 480),
            ];
        });

        $totalWorkMinutes = $calculatedRecords->sum('work_minutes');
        $totalOvertimeMinutes = $calculatedRecords->sum('overtime_minutes');

        $workedDays = $calculatedRecords
            ->where('work_minutes', '>', 0)
            ->count();

        $averageWorkMinutes = $workedDays > 0
            ? (int) round($totalWorkMinutes / $workedDays)
            : 0;

        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $averageWorkMinutes,
        ];

        $monthlyTrend = collect(range(5, 0))
            ->map(function ($monthsAgo) use ($calculatedRecords) {
                $month = now()
                    ->subMonthsNoOverflow($monthsAgo)
                    ->startOfMonth();

                $monthRecords = $calculatedRecords->filter(function ($record) use ($month) {
                    return $record['date']->year === $month->year
                        && $record['date']->month === $month->month;
                });

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => $monthRecords->sum('work_minutes'),
                    'overtime_minutes' => $monthRecords->sum('overtime_minutes'),
                ];
            })
            ->values()
            ->all();

        $currentMonthRecords = $calculatedRecords->filter(function ($record) {
            return $record['date']->year === now()->year
                && $record['date']->month === now()->month;
        });

        $lateCount = $currentMonthRecords->filter(function ($record) {
            return $record['clock_in']
                && Carbon::parse($record['clock_in'])->gt(
                    Carbon::createFromTime(9, 0)
                );
        })->count();

        $earlyLeaveCount = $currentMonthRecords->filter(function ($record) {
            return $record['clock_out']
                && Carbon::parse($record['clock_out'])->lt(
                    Carbon::createFromTime(18, 0)
                );
        })->count();

        $longWorkCount = $currentMonthRecords->filter(function ($record) {
            return $record['work_minutes'] > 600;
        })->count();

        $anomalies = [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];

        return view('reports.index', compact(
            'summary',
            'monthlyTrend',
            'anomalies'
        ));
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $attendanceRecord = AttendanceRecord::with([
            'breaks',
            'applications',
        ])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $application = $attendanceRecord->applications
            ->where('is_approved', false)
            ->sortByDesc('id')
            ->first();

        $data = [
            'id' => $attendanceRecord->id,
            'year' => Carbon::parse($attendanceRecord->date)->format('Y年'),
            'date' => Carbon::parse($attendanceRecord->date)->format('n月j日'),
            'clock_in' => $attendanceRecord->clock_in
                ? Carbon::parse($attendanceRecord->clock_in)->format('H:i')
                : '',
            'clock_out' => $attendanceRecord->clock_out
                ? Carbon::parse($attendanceRecord->clock_out)->format('H:i')
                : '',
            'breaks' => $attendanceRecord->breaks->map(function ($break) {
                return [
                    'break_in' => $break->break_in
                        ? Carbon::parse($break->break_in)->format('H:i')
                        : '',
                    'break_out' => $break->break_out
                        ? Carbon::parse($break->break_out)->format('H:i')
                        : '',
                ];
            })->values()->all(),
            'comment' => $attendanceRecord->comment ?? '',
            'application' => $application,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }

    public function requestCorrection(AttendanceCorrectionRequest $request, $id)
    {
        $user = $request->user();

        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->findOrFail($id);

        $validated = $request->validated();

        $application = Application::create([
            'attendance_record_id' => $attendanceRecord->id,
            'clock_in' => $validated['new_clock_in'],
            'clock_out' => $validated['new_clock_out'],
            'comment' => $validated['comment'],
            'is_approved' => false,
        ]);

        $breakIns = $validated['new_break_in'] ?? [];
        $breakOuts = $validated['new_break_out'] ?? [];

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            if (! $breakIn || ! $breakOut) {
                continue;
            }

            $application->applicationBreaks()->create([
                'break_in' => $breakIn,
                'break_out' => $breakOut,
            ]);
        }

        return redirect('/attendance/detail/'.$attendanceRecord->id);
    }

    public function applicationList(Request $request)
    {
        $user = $request->user();

        if ($user->admin_status) {
            return app(AdminAttendanceController::class)
                ->applicationList();
        }

        $applications = Application::with('attendanceRecord')
            ->whereHas('attendanceRecord', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->latest()
            ->get();

        $formattedApplications = $applications->map(function ($application) {
            return [
                'id' => $application->id,
                'attendance_record_id' => $application->attendance_record_id,
                'approval_status' => $application->is_approved
                    ? '承認済み'
                    : '承認待ち',
                'date' => Carbon::parse($application->attendanceRecord->date)
                    ->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => Carbon::parse($application->created_at)
                    ->format('Y/m/d'),
            ];
        });

        return view('user.user-application-list', compact(
            'user',
            'formattedApplications'
        ));
    }
}
