<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttendanceRecordController extends Controller
{
    /**
     * Display a paginated list of attendance records.
     */
    public function index(
        IndexAttendanceRecordRequest $request
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $query = AttendanceRecord::query()
            ->with('breaks')
            ->orderByDesc('date')
            ->orderByDesc('id');

        if (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (isset($validated['date'])) {
            $query->whereDate('date', $validated['date']);
        }

        if (isset($validated['month'])) {
            $query->whereYear(
                'date',
                substr($validated['month'], 0, 4)
            )->whereMonth(
                'date',
                substr($validated['month'], 5, 2)
            );
        }

        $perPage = $validated['per_page'] ?? 20;

        $attendanceRecords = $query->paginate($perPage);

        $attendanceRecords->getCollection()->each(function ($record) {
            $record->setAttribute(
                'calculated_total_break_time',
                $record->total_break_time
            );

            $record->setAttribute(
                'calculated_total_time',
                $record->total_time
            );

            $record->unsetRelation('breaks');
        });

        return AttendanceRecordResource::collection($attendanceRecords);
    }

    /**
     * Display the specified attendance record.
     */
    public function show(
        AttendanceRecord $attendanceRecord
    ): AttendanceRecordResource {
        $attendanceRecord->load([
            'user',
            'breaks',
            'applications',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * Store a newly created attendance record.
     */
    public function store(
        StoreAttendanceRecordRequest $request
    ): JsonResponse {
        $attendanceRecord = AttendanceRecord::create(
            $request->validated()
        );

        $attendanceRecord->load([
            'user',
            'breaks',
            'applications',
        ]);

        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified attendance record.
     */
    public function update(
        UpdateAttendanceRecordRequest $request,
        AttendanceRecord $attendanceRecord
    ): AttendanceRecordResource {
        $this->authorize('update', $attendanceRecord);

        $attendanceRecord->update(
            $request->validated()
        );

        $attendanceRecord->load([
            'user',
            'breaks',
            'applications',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * Remove the specified attendance record.
     */
    public function destroy(
        AttendanceRecord $attendanceRecord
    ): Response {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}