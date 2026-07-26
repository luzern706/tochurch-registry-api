<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChurchProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {
            'updateProfile' => [
                'name'           => ['sometimes', 'string', 'max:200'],
                'group_no'       => ['sometimes', 'integer', 'min:1'],
                'phone'          => ['sometimes', 'string', 'max:20'],
                'phone2'         => ['sometimes', 'nullable', 'string', 'max:20'],
                'email'          => ['sometimes', 'nullable', 'email', 'max:200'],
                'homepage'       => ['sometimes', 'nullable', 'string', 'max:200'],
                'youtube_url'    => ['sometimes', 'nullable', 'string', 'max:200'],
                'tax_no'         => ['sometimes', 'nullable', 'string', 'max:45'],
                'church_create_date' => ['sometimes', 'nullable', 'date'],
                'address'        => ['sometimes', 'string', 'max:500'],
                'address_detail' => ['sometimes', 'nullable', 'string', 'max:200'],
                'postcode'       => ['sometimes', 'nullable', 'string', 'max:10'],
                // 다음 주소 검색 응답의 시/도·시/군/구·동 — RegionMatcher가 gh_sido/gh_sigungu/gh_dong
                // 번호로 변환. address와 함께 보내야 함(주소만 오면 지역 매칭 없이 좌표만 갱신됨).
                'sido_name'      => ['sometimes', 'nullable', 'string', 'max:50'],
                'sigungu_name'   => ['sometimes', 'nullable', 'string', 'max:50'],
                'dong_name'      => ['sometimes', 'nullable', 'string', 'max:50'],
                'head_pastor_no' => ['sometimes', 'nullable', 'integer'],
                'receipt_notice' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ],
            'uploadFile' => [
                'slot' => ['required', 'string', 'in:logo,photo,affiliation_cert,letterhead'],
                'file' => ['required', 'file'],
            ],
            'deleteFile' => [
                'slot' => ['required', 'string', 'in:logo,photo,affiliation_cert,letterhead'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'name.required'  => '교회명은 필수입니다.',
            'email.email'    => '올바른 이메일 형식이 아닙니다.',
            'slot.in'        => '알 수 없는 파일 종류입니다.',
            'file.required'  => '파일을 선택해주세요.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
