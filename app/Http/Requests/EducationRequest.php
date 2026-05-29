<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class EducationRequest extends FormRequest
{
    private const COURSE_STATUSES = ['upcoming', 'ongoing', 'completed'];
    private const ENROLLMENT_STATUSES = ['ongoing', 'completed', 'dropped'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'                => $this->courseListRules(),
            'getDetail'              => $this->courseKeyRules(),
            'register'               => $this->registerCourseRules(),
            'update'                 => $this->updateCourseRules(),
            'delete'                 => $this->courseKeyRules(),
            'enroll'                 => $this->mappingKeyRules(),
            'updateProgress'         => $this->updateProgressRules(),
            'withdraw'               => $this->mappingKeyRules(),
            'getEnrollmentsByCourse' => $this->getEnrollmentsByCourseRules(),
            'getCoursesByMember'     => $this->getCoursesByMemberRules(),
            default                  => [],
        };
    }

    public function attributes(): array
    {
        return [
            'course_id' => '교육 과정 ID',
            'member_id' => '교인 ID',
            'name'      => '과정명',
            'progress'  => '진도율',
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

    private function courseListRules(): array
    {
        return [
            'status'    => ['nullable', 'in:' . implode(',', self::COURSE_STATUSES)],
            'from_date' => ['nullable', 'date'],
            'to_date'   => ['nullable', 'date', 'after_or_equal:from_date'],
            'keyword'   => ['nullable', 'string', 'max:100'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'size'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function courseKeyRules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerCourseRules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'      => ['nullable', 'in:' . implode(',', self::COURSE_STATUSES)],
        ];
    }

    private function updateCourseRules(): array
    {
        return [
            'course_id'   => ['required', 'integer', 'min:1'],
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'start_date'  => ['sometimes', 'nullable', 'date'],
            'end_date'    => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status'      => ['sometimes', 'in:' . implode(',', self::COURSE_STATUSES)],
        ];
    }

    private function mappingKeyRules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'min:1'],
            'member_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function updateProgressRules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'min:1'],
            'member_id' => ['required', 'integer', 'min:1'],
            'progress'  => ['required', 'integer', 'between:0,100'],
            'status'    => ['nullable', 'in:' . implode(',', self::ENROLLMENT_STATUSES)],
        ];
    }

    private function getEnrollmentsByCourseRules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'min:1'],
            'status'    => ['nullable', 'in:' . implode(',', self::ENROLLMENT_STATUSES)],
            'page'      => ['nullable', 'integer', 'min:1'],
            'size'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function getCoursesByMemberRules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
