<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList', 'bulletinGetList', 'qnaGetList', 'counselingGetList', 'testimonyGetList' => $this->listRules(),
            'counselingGetDetail' => $this->keyRules(),

            'register' => $this->newsRegisterRules(),
            'update'   => $this->newsUpdateRules(),
            'delete'   => $this->keyRules(),
            'togglePin' => ['content_no' => ['required', 'integer', 'min:1'], 'is_top' => ['required', 'boolean']],

            'bulletinRegister' => $this->bulletinRegisterRules(),
            'bulletinUpdate'   => $this->bulletinUpdateRules(),
            'bulletinDelete'   => $this->keyRules(),

            'qnaRegister' => $this->qnaRegisterRules(),
            'qnaUpdate'   => $this->qnaUpdateRules(),
            'qnaDelete'   => $this->keyRules(),
            'qnaAnswer'   => [
                'content_no' => ['required', 'integer', 'min:1'],
                'comment'    => ['required', 'string', 'max:2000'],
            ],

            'counselingUpdate' => $this->counselingUpdateRules(),
            'counselingDelete' => $this->keyRules(),

            'testimonyUpdate' => [
                'content_no'  => ['required', 'integer', 'min:1'],
                'is_selected' => ['required', 'boolean'],
            ],
            'testimonyDelete' => $this->keyRules(),

            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'content_no' => 'ID',
            'title'      => '제목',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'page'    => ['nullable', 'integer', 'min:1'],
            'size'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function keyRules(): array
    {
        return ['content_no' => ['required', 'integer', 'min:1']];
    }

    private function newsRegisterRules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:100'],
            'content' => ['nullable', 'string', 'max:2000'],
            'is_top'  => ['nullable', 'boolean'],
        ];
    }

    private function newsUpdateRules(): array
    {
        return [
            'content_no' => ['required', 'integer', 'min:1'],
            'title'      => ['sometimes', 'string', 'max:100'],
            'content'    => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_top'     => ['sometimes', 'boolean'],
        ];
    }

    private function bulletinRegisterRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'date'  => ['nullable', 'date'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*.name' => ['required_with:files', 'string', 'max:200'],
            'files.*.url'  => ['required_with:files', 'string', 'max:1000'],
            'files.*.type' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function bulletinUpdateRules(): array
    {
        return [
            'content_no' => ['required', 'integer', 'min:1'],
            'title'      => ['sometimes', 'string', 'max:100'],
            'date'       => ['sometimes', 'nullable', 'date'],
            'files'      => ['sometimes', 'array', 'max:10'],
            'files.*.name' => ['required_with:files', 'string', 'max:200'],
            'files.*.url'  => ['required_with:files', 'string', 'max:1000'],
            'files.*.type' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function qnaRegisterRules(): array
    {
        return [
            'title'     => ['required', 'string', 'max:100'],
            'content'   => ['nullable', 'string', 'max:2000'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }

    private function qnaUpdateRules(): array
    {
        return [
            'content_no' => ['required', 'integer', 'min:1'],
            'title'      => ['sometimes', 'string', 'max:100'],
            'content'    => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_public'  => ['sometimes', 'boolean'],
        ];
    }

    private function counselingUpdateRules(): array
    {
        return [
            'content_no'         => ['required', 'integer', 'min:1'],
            'status'             => ['sometimes', 'string', 'in:접수,처리중,완료'],
            'assignee_admin_no'  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'memo'               => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reply'              => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
