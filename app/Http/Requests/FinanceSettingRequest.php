<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FinanceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'saveSettings' => [
                'settings'   => ['required', 'array', 'min:1'],
                'settings.*' => ['nullable', 'string'],
            ],
            'uploadSeal' => [
                'file' => ['required', 'file', 'image', 'max:1024'],
            ],
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'file' => '직인 이미지',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'image'    => ':attribute 은(는) 이미지 파일만 업로드할 수 있습니다.',
            'max'      => ':attribute 은(는) 최대 크기를 초과했습니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
