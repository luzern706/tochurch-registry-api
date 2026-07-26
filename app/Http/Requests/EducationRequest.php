<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class EducationRequest extends FormRequest
{
    private const COURSE_STATUSES     = ['upcoming', 'ongoing', 'completed'];
    private const ENROLLMENT_STATUSES = ['ongoing', 'completed', 'dropped'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            // 과정 템플릿
            'getCourseList'   => $this->courseListRules(),
            'getCourseDetail' => $this->courseKeyRules(),
            'registerCourse'  => $this->registerCourseRules(),
            'updateCourse'    => $this->updateCourseRules(),
            'deleteCourse'    => $this->courseKeyRules(),
            // 기수
            'getSessionList'   => $this->sessionListRules(),
            'getSessionDetail' => $this->sessionKeyRules(),
            'registerSession'  => $this->registerSessionRules(),
            'updateSession'    => $this->updateSessionRules(),
            'deleteSession'    => $this->sessionKeyRules(),
            // 수강
            'enroll'                => $this->enrollKeyRules(),
            'updateProgress'        => $this->updateProgressRules(),
            'withdraw'              => $this->enrollKeyRules(),
            'getEnrollmentsBySession'  => $this->getEnrollmentsBySessionRules(),
            'getSessionsByMember'      => $this->getMemberRules(),
            // 출결
            'getAttendanceSheet'       => $this->sessionKeyRules(),
            'saveRoundAttendance'      => $this->saveRoundAttendanceRules(),
            'getMemberAttendanceStats' => $this->sessionKeyRules(),
            default                    => [],
        };
    }

    public function attributes(): array
    {
        return [
            'course_id'  => '교육 과정 ID',
            'session_id' => '기수 ID',
            'member_id'  => '교인 ID',
            'name'       => '과정명',
            'generation' => '기수명',
            'progress'   => '진도율',
        ];
    }

    public function messages(): array
    {
        return [
            'required'       => ':attribute 값을 입력해주세요.',
            'integer'        => ':attribute 은(는) 정수여야 합니다.',
            'date'           => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'in'             => ':attribute 값이 허용된 값이 아닙니다.',
            'min'            => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'            => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
            'between'        => ':attribute 은(는) :min ~ :max 사이여야 합니다.',
            'after_or_equal' => ':attribute 은(는) :date 이후여야 합니다.',
        ];
    }

    // ── 과정 템플릿 ──

    private function courseListRules(): array
    {
        return [
            'keyword'  => ['nullable', 'string', 'max:100'],
            'status'   => ['nullable', 'in:' . implode(',', self::COURSE_STATUSES)],
            'target'   => ['nullable', 'string', 'max:50'],
            'edu_type' => ['nullable', 'string', 'max:50'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'size'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function courseKeyRules(): array
    {
        return ['course_id' => ['required', 'integer', 'min:1']];
    }

    private function registerCourseRules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:100'],
            'description'     => ['nullable', 'string'],
            'recommendation'  => ['nullable', 'string'],
            'target'          => ['nullable', 'string', 'max:50'],
            'edu_type'        => ['nullable', 'string', 'max:50'],
            'total_weeks'     => ['nullable', 'integer', 'min:1', 'max:104'],
            'total_rounds'    => ['nullable', 'integer', 'min:1', 'max:999'],
            'use_attendance'  => ['nullable', 'boolean'],
            'completion_rate' => ['nullable', 'integer', 'between:0,100'],
            'repeat_mode'     => ['nullable', 'string', 'in:annual,ondemand'],
            'auto_complete'   => ['nullable', 'boolean'],
        ];
    }

    private function updateCourseRules(): array
    {
        return [
            'course_id'       => ['required', 'integer', 'min:1'],
            'name'            => ['sometimes', 'string', 'max:100'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'recommendation'  => ['sometimes', 'nullable', 'string'],
            'target'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'edu_type'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'total_weeks'     => ['sometimes', 'nullable', 'integer', 'min:1', 'max:104'],
            'total_rounds'    => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'use_attendance'  => ['sometimes', 'boolean'],
            'completion_rate' => ['sometimes', 'integer', 'between:0,100'],
            'repeat_mode'     => ['sometimes', 'nullable', 'string', 'in:annual,ondemand'],
            'auto_complete'   => ['sometimes', 'boolean'],
        ];
    }

    // ── 기수 ──

    private function sessionListRules(): array
    {
        return [
            'course_id' => ['nullable', 'integer', 'min:1'],
            'status'    => ['nullable', 'in:' . implode(',', self::COURSE_STATUSES)],
            'from_date' => ['nullable', 'date'],
            'to_date'   => ['nullable', 'date', 'after_or_equal:from_date'],
            'keyword'   => ['nullable', 'string', 'max:100'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'size'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function sessionKeyRules(): array
    {
        return ['session_id' => ['required', 'integer', 'min:1']];
    }

    private function registerSessionRules(): array
    {
        return [
            'course_id'    => ['required', 'integer', 'min:1'],
            'generation'   => ['nullable', 'string', 'max:50'],
            'instructor'   => ['nullable', 'string', 'max:100'],
            'start_date'   => ['nullable', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'capacity'     => ['nullable', 'integer', 'min:1'],
            'total_rounds' => ['nullable', 'integer', 'min:1', 'max:999'],
            'status'       => ['nullable', 'in:' . implode(',', self::COURSE_STATUSES)],
            'memo'         => ['nullable', 'string'],
        ];
    }

    private function updateSessionRules(): array
    {
        return [
            'session_id'   => ['required', 'integer', 'min:1'],
            'generation'   => ['sometimes', 'string', 'max:50'],
            'instructor'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'start_date'   => ['sometimes', 'nullable', 'date'],
            'end_date'     => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'capacity'     => ['sometimes', 'nullable', 'integer', 'min:1'],
            'total_rounds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'status'       => ['sometimes', 'in:' . implode(',', self::COURSE_STATUSES)],
            'memo'         => ['sometimes', 'nullable', 'string'],
        ];
    }

    // ── 수강 ──

    private function enrollKeyRules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'min:1'],
            'member_id'  => ['required', 'integer', 'min:1'],
        ];
    }

    private function updateProgressRules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'min:1'],
            'member_id'  => ['required', 'integer', 'min:1'],
            'progress'   => ['required', 'integer', 'between:0,100'],
            'status'     => ['nullable', 'in:' . implode(',', self::ENROLLMENT_STATUSES)],
        ];
    }

    private function getEnrollmentsBySessionRules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'min:1'],
            'status'     => ['nullable', 'in:' . implode(',', self::ENROLLMENT_STATUSES)],
            'page'       => ['nullable', 'integer', 'min:1'],
            'size'       => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    private function getMemberRules(): array
    {
        return ['member_id' => ['required', 'integer', 'min:1']];
    }

    private function saveRoundAttendanceRules(): array
    {
        return [
            'session_id'          => ['required', 'integer', 'min:1'],
            'round_no'            => ['required', 'integer', 'min:1', 'max:999'],
            'session_date'        => ['nullable', 'date'],
            'records'             => ['required', 'array', 'min:1'],
            'records.*.member_id' => ['required', 'integer', 'min:1'],
            'records.*.status'    => ['required', 'in:present,absent,late,excused'],
            'records.*.note'      => ['nullable', 'string', 'max:200'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
