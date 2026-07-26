<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'sermonGetList', 'churchVideoGetList', 'galleryGetList', 'ministryGetList' => $this->listRules(),

            'sermonSetFeaturedMode' => ['featured_mode' => ['required', 'string', 'in:auto,manual']],
            'sermonRegister' => $this->sermonRegisterRules(),
            'sermonUpdate'   => $this->sermonUpdateRules(),
            'sermonDelete'   => $this->keyRules(),

            'churchVideoRegister' => [
                'title'       => ['required', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:1000'],
                'video_url'   => ['nullable', 'string', 'max:400'],
                'category'    => ['nullable', 'string', 'max:20'],
            ],
            'churchVideoUpdate' => [
                'content_no'  => ['required', 'integer', 'min:1'],
                'title'       => ['sometimes', 'string', 'max:100'],
                'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'video_url'   => ['sometimes', 'nullable', 'string', 'max:400'],
                'category'    => ['sometimes', 'nullable', 'string', 'max:20'],
            ],
            'churchVideoDelete' => $this->keyRules(),

            'galleryRegister', 'ministryRegister' => $this->albumRegisterRules(),
            'galleryUpdate', 'ministryUpdate'     => $this->albumUpdateRules(),
            'galleryDelete', 'ministryDelete'     => $this->keyRules(),

            'uploadImage' => [
                'image' => ['required', 'file', 'image', 'max:10240'],
            ],

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
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
            'image'    => '이미지 파일만 업로드할 수 있습니다.',
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

    private function sermonRegisterRules(): array
    {
        return [
            'title'     => ['required', 'string', 'max:100'],
            'video_url' => ['nullable', 'string', 'max:400'],
            'speaker'   => ['nullable', 'string', 'max:50'],
            'verse'     => ['nullable', 'string', 'max:100'],
            'series'    => ['nullable', 'string', 'max:100'],
            'tags'      => ['nullable', 'array', 'max:10'],
            'tags.*'    => ['string', 'max:20'],
            'date'      => ['nullable', 'date'],
            'is_top'    => ['nullable', 'boolean'],
        ];
    }

    private function sermonUpdateRules(): array
    {
        return [
            'content_no' => ['required', 'integer', 'min:1'],
            'title'      => ['sometimes', 'string', 'max:100'],
            'video_url'  => ['sometimes', 'nullable', 'string', 'max:400'],
            'speaker'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'verse'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'series'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'tags'       => ['sometimes', 'array', 'max:10'],
            'tags.*'     => ['string', 'max:20'],
            'date'       => ['sometimes', 'nullable', 'date'],
            'is_top'     => ['sometimes', 'boolean'],
        ];
    }

    private function albumRegisterRules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cover_url'   => ['nullable', 'string', 'max:1000'],
            'photos'      => ['nullable', 'array', 'max:100'],
            'photos.*'    => ['string', 'max:1000'],
            'is_public'   => ['nullable', 'boolean'],
        ];
    }

    private function albumUpdateRules(): array
    {
        return [
            'content_no'  => ['required', 'integer', 'min:1'],
            'title'       => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cover_url'   => ['sometimes', 'nullable', 'string', 'max:1000'],
            'photos'      => ['sometimes', 'array', 'max:100'],
            'photos.*'    => ['string', 'max:1000'],
            'is_public'   => ['sometimes', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
