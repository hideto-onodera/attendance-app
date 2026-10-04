<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalTime = $this->calculated_total_time
            ?? $this->total_time;

        $totalBreakTime = $this->calculated_total_break_time
            ?? $this->total_break_time;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => new UserResource(
                $this->whenLoaded('user')
            ),
            'date' => $this->date,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_time' => $totalTime,
            'total_break_time' => $totalBreakTime,
            'comment' => $this->comment,
            'breaks' => AttendanceBreakResource::collection(
                $this->whenLoaded('breaks')
            ),
            'applications' => ApplicationResource::collection(
                $this->whenLoaded('applications')
            ),
        ];
    }
}