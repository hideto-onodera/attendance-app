@extends('layouts.admin-app')

@section('css')
@vite('resources/css/admin/admin-application-detail.css')
@endsection

@section('content')
<div class="detail__content">
    <div class="detail__header">
        <h1 class="content__header--item">勤怠詳細</h1>
    </div>

    <form
        class="applied-form"
        action="{{ url('/stamp_correction_request/approve/' . $application->id) }}"
        method="post"
    >
        @csrf

        <div class="applied-form__content">
            <div class="applied-form__group">
                <label class="applied-form__header">名前</label>
                <div class="applied-form__input-group">
                    <input
                        class="applied-form__input"
                        type="text"
                        value="{{ $user->name }}"
                        readonly
                    >
                </div>
            </div>

            <div class="applied-form__group">
                <label class="applied-form__header">日付</label>
                <div class="applied-form__input-group">
                    <input
                        class="applied-form__input"
                        type="text"
                        value="{{ \Carbon\Carbon::parse($application->attendanceRecord->date)->format('Y年') }}"
                        readonly
                    >
                    <input
                        class="applied-form__input"
                        type="text"
                        value="{{ \Carbon\Carbon::parse($application->attendanceRecord->date)->format('n月j日') }}"
                        readonly
                    >
                </div>
            </div>

            <div class="applied-form__group">
                <label class="applied-form__header">出勤・退勤</label>
                <div class="applied-form__input-group">
                    <input
                        class="applied-form__input"
                        type="text"
                        value="{{ \Carbon\Carbon::parse($application->clock_in)->format('H:i') }}"
                        readonly
                    >
                    <p>〜</p>
                    <input
                        class="applied-form__input"
                        type="text"
                        value="{{ \Carbon\Carbon::parse($application->clock_out)->format('H:i') }}"
                        readonly
                    >
                </div>
            </div>

            @foreach ($application->applicationBreaks as $index => $break)
                <div class="applied-form__group">
                    <label class="applied-form__header">
                        {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                    </label>

                    <div class="applied-form__input-group">
                        <input
                            class="applied-form__input"
                            type="text"
                            value="{{ \Carbon\Carbon::parse($break->break_in)->format('H:i') }}"
                            readonly
                        >
                        <p>〜</p>
                        <input
                            class="applied-form__input"
                            type="text"
                            value="{{ \Carbon\Carbon::parse($break->break_out)->format('H:i') }}"
                            readonly
                        >
                    </div>
                </div>
            @endforeach

            <div class="applied-form__group">
                <label class="applied-form__header">
                    {{ $application->applicationBreaks->count() === 0
                        ? '休憩'
                        : '休憩' . ($application->applicationBreaks->count() + 1) }}
                </label>

                <div class="applied-form__input-group">
                    <input
                        class="applied-form__input"
                        type="text"
                        value=""
                        readonly
                    >
                    <p>〜</p>
                    <input
                        class="applied-form__input"
                        type="text"
                        value=""
                        readonly
                    >
                </div>
            </div>

            <div class="applied-form__group">
                <label class="applied-form__header">備考</label>
                <div class="applied-form__input-group">
                    <textarea
                        class="applied-form__textarea"
                        readonly
                    >{{ $application->comment }}</textarea>
                </div>
            </div>
        </div>

        <div class="applied-form__button">
            @if (!$application->is_approved)
                <button
                    class="applied-form__button--submit"
                    type="submit"
                >
                    承認
                </button>
            @else
                <p class="applied-form__item">承認済み</p>
            @endif
        </div>
    </form>
</div>
@endsection