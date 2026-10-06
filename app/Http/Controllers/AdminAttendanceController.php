<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : today();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->whereDate('date', $date)
            ->get();

        $attendanceRecords->each(function ($attendanceRecord) {
            $totalBreakMinutes = $attendanceRecord->breaks->sum(function ($break) {
                if (! $break->break_in || ! $break->break_out) {
                    return 0;
                }

                return Carbon::parse($break->break_in)
                    ->diffInMinutes(Carbon::parse($break->break_out));
            });

            $attendanceRecord->formatted_total_break_time = null;
            $attendanceRecord->formatted_total_time = null;

            if ($attendanceRecord->clock_in && $attendanceRecord->clock_out) {
                $workMinutes = Carbon::parse($attendanceRecord->clock_in)
                    ->diffInMinutes(Carbon::parse($attendanceRecord->clock_out));

                $totalWorkMinutes = max(
                    0,
                    $workMinutes - $totalBreakMinutes
                );

                $attendanceRecord->formatted_total_break_time = sprintf(
                    '%02d:%02d',
                    intdiv($totalBreakMinutes, 60),
                    $totalBreakMinutes % 60
                );

                $attendanceRecord->formatted_total_time = sprintf(
                    '%02d:%02d',
                    intdiv($totalWorkMinutes, 60),
                    $totalWorkMinutes % 60
                );
            }
        });

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }

    public function show($id)
    {
        $attendanceRecord = AttendanceRecord::with([
            'user',
            'breaks',
        ])->findOrFail($id);

        $user = $attendanceRecord->user;

        $attendanceRecordData = [
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
        ];

        return view('admin.admin-detail', [
            'user' => $user,
            'attendanceRecord' => $attendanceRecordData,
        ]);
    }

    public function update(
        AdminAttendanceUpdateRequest $request,
        $id
    ) {
        $attendanceRecord = AttendanceRecord::with('breaks')
            ->findOrFail($id);

        $validated = $request->validated();

        DB::transaction(function () use ($attendanceRecord, $validated) {
            $attendanceRecord->update([
                'clock_in' => $validated['new_clock_in'],
                'clock_out' => $validated['new_clock_out'],
                'comment' => $validated['comment'],
            ]);

            $attendanceRecord->breaks()->delete();

            $breakIns = $validated['new_break_in'] ?? [];
            $breakOuts = $validated['new_break_out'] ?? [];

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (! $breakIn || ! $breakOut) {
                    continue;
                }

                $attendanceRecord->breaks()->create([
                    'break_in' => $breakIn,
                    'break_out' => $breakOut,
                ]);
            }
        });

        return redirect()->route(
            'admin.attendance.detail',
            ['id' => $attendanceRecord->id]
        );
    }

    public function staffList()
    {
        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        return view('admin.staff-list', compact('users'));
    }

    public function staffAttendanceList(Request $request, $id)
    {
        $user = User::where('admin_status', false)
            ->findOrFail($id);

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

        $formattedAttendanceRecords = $attendanceRecords->map(
            function ($attendanceRecord) {
                $totalBreakMinutes = $attendanceRecord->breaks->sum(
                    function ($break) {
                        if (! $break->break_in || ! $break->break_out) {
                            return 0;
                        }

                        return Carbon::parse($break->break_in)
                            ->diffInMinutes(Carbon::parse($break->break_out));
                    }
                );

                $totalBreakTime = null;
                $totalTime = null;

                if ($attendanceRecord->clock_in && $attendanceRecord->clock_out) {
                    $workMinutes = Carbon::parse($attendanceRecord->clock_in)
                        ->diffInMinutes(Carbon::parse($attendanceRecord->clock_out));

                    $totalWorkMinutes = max(
                        0,
                        $workMinutes - $totalBreakMinutes
                    );

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
                    'date' => Carbon::parse($attendanceRecord->date)
                        ->format('m/d'),
                    'clock_in' => $attendanceRecord->clock_in
                        ? Carbon::parse($attendanceRecord->clock_in)->format('H:i')
                        : '',
                    'clock_out' => $attendanceRecord->clock_out
                        ? Carbon::parse($attendanceRecord->clock_out)->format('H:i')
                        : '',
                    'total_break_time' => $totalBreakTime,
                    'total_time' => $totalTime,
                ];
            }
        );

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    public function exportStaffAttendanceCsv(Request $request, $id)
    {
        $user = User::where('admin_status', false)
            ->findOrFail($id);

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfMonth()
            : now()->startOfMonth();

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->orderBy('date')
            ->get();

        $fileName = sprintf(
            'attendance_%s_%s.csv',
            $user->id,
            $date->format('Y_m')
        );

        return response()->streamDownload(function () use ($attendanceRecords) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                '日付',
                '出勤',
                '退勤',
                '休憩',
                '合計',
            ]);

            foreach ($attendanceRecords as $attendanceRecord) {
                $totalBreakMinutes = $attendanceRecord->breaks->sum(
                    function ($break) {
                        if (! $break->break_in || ! $break->break_out) {
                            return 0;
                        }

                        return Carbon::parse($break->break_in)
                            ->diffInMinutes(Carbon::parse($break->break_out));
                    }
                );

                $totalBreakTime = '';
                $totalTime = '';

                if ($attendanceRecord->clock_in && $attendanceRecord->clock_out) {
                    $workMinutes = Carbon::parse($attendanceRecord->clock_in)
                        ->diffInMinutes(Carbon::parse($attendanceRecord->clock_out));

                    $totalWorkMinutes = max(
                        0,
                        $workMinutes - $totalBreakMinutes
                    );

                    $totalBreakTime = sprintf(
                        '%d:%02d',
                        intdiv($totalBreakMinutes, 60),
                        $totalBreakMinutes % 60
                    );

                    $totalTime = sprintf(
                        '%d:%02d',
                        intdiv($totalWorkMinutes, 60),
                        $totalWorkMinutes % 60
                    );
                }

                fputcsv($handle, [
                    Carbon::parse($attendanceRecord->date)->format('Y/m/d'),
                    $attendanceRecord->clock_in
                        ? Carbon::parse($attendanceRecord->clock_in)->format('H:i')
                        : '',
                    $attendanceRecord->clock_out
                        ? Carbon::parse($attendanceRecord->clock_out)->format('H:i')
                        : '',
                    $totalBreakTime,
                    $totalTime,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function applicationList()
    {
        $applications = Application::with([
            'attendanceRecord.user',
            'applicationBreaks',
        ])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.admin-application-list', compact(
            'applications'
        ));
    }

    public function applicationDetail($id)
    {
        $application = Application::with([
            'attendanceRecord.user',
            'applicationBreaks',
        ])->findOrFail($id);

        $user = $application->attendanceRecord->user;

        return view('admin.admin-application-detail', compact(
            'application',
            'user'
        ));
    }

    public function approveApplication($id)
    {
        $application = Application::with([
            'attendanceRecord.breaks',
            'applicationBreaks',
        ])->findOrFail($id);

        if (! $application->is_approved) {
            DB::transaction(function () use ($application) {
                $attendanceRecord = $application->attendanceRecord;

                $attendanceRecord->update([
                    'clock_in' => $application->clock_in,
                    'clock_out' => $application->clock_out,
                    'comment' => $application->comment,
                ]);

                $attendanceRecord->breaks()->delete();

                foreach ($application->applicationBreaks as $applicationBreak) {
                    $attendanceRecord->breaks()->create([
                        'break_in' => $applicationBreak->break_in,
                        'break_out' => $applicationBreak->break_out,
                    ]);
                }

                $application->update([
                    'is_approved' => true,
                ]);
            });
        }

        return redirect()->route(
            'admin.stamp-correction-request.detail',
            ['id' => $application->id]
        );
    }
}
