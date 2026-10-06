<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminAttendanceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string'],
        ];
    }

    /**
     * Get the validation error messages.
     */
    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.date_format' => '休憩時間もしくは退勤時間が不適切な値です',
            'comment.required' => '備考を記入してください',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $clockIn = $this->input('new_clock_in');
                $clockOut = $this->input('new_clock_out');

                if ($clockIn && $clockOut && $clockIn >= $clockOut) {
                    $validator->errors()->add(
                        'new_clock_in',
                        '出勤時間もしくは退勤時間が不適切な値です'
                    );
                }

                $breakIns = $this->input('new_break_in', []);
                $breakOuts = $this->input('new_break_out', []);

                foreach ($breakIns as $index => $breakIn) {
                    if (
                        $breakIn
                        && (
                            ($clockIn && $breakIn < $clockIn)
                            || ($clockOut && $breakIn > $clockOut)
                        )
                    ) {
                        $validator->errors()->add(
                            "new_break_in.$index",
                            '休憩時間が不適切な値です'
                        );
                    }
                }

                foreach ($breakOuts as $index => $breakOut) {
                    if ($breakOut && $clockOut && $breakOut > $clockOut) {
                        $validator->errors()->add(
                            "new_break_out.$index",
                            '休憩時間もしくは退勤時間が不適切な値です'
                        );
                    }
                }
            },
        ];
    }
}
