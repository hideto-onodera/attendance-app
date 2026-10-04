<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendance_record_id' => $this->attendance_record_id,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'comment' => $this->comment,
            'is_approved' => (bool) $this->is_approved,
        ];
    }
}