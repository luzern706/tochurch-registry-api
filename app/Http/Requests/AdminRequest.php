<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {
            'getList'   => [
                'search_type'  => ['sometimes', 'nullable', 'string', 'in:id,name', 'required_with:search_value'],
                'search_value' => ['sometimes', 'nullable', 'string', 'max:100', 'required_with:search_type'],
                'status'       => ['sometimes', 'nullable', 'string', 'in:ALIVE,INACTIVE,DELETE'],
                'admin_type'   => ['sometimes', 'nullable', 'string', 'in:admin,pastor,minister,volunteer'],
                'page'         => ['sometimes', 'integer', 'min:1'],
                'size'         => ['sometimes', 'integer', 'min:1', 'max:100'],
            ],
            'getDetail' => [
                'admin_no' => ['required', 'integer'],
            ],
            'register'  => [
                'login_id'   => ['required', 'string', 'max:100'],
                'name'       => ['required', 'string', 'max:50'],
                'password'   => ['required', 'string', 'min:8', 'max:255'],
                'admin_type' => ['required', 'string', 'in:admin,pastor,minister,volunteer'],
            ],
            'update'    => [
                'admin_no'   => ['required', 'integer'],
                'name'       => ['sometimes', 'string', 'max:50'],
                'password'   => ['sometimes', 'string', 'min:8', 'max:255'],
                'admin_type' => ['sometimes', 'string', 'in:admin,pastor,minister,volunteer'],
            ],
            'delete', 'suspend', 'activate', 'purge' => [
                'admin_no' => ['required', 'integer'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'admin_no.required'   => '관리자 번호를 입력해주세요.',
            'login_id.required'   => '아이디를 입력해주세요.',
            'name.required'       => '이름을 입력해주세요.',
            'name.max'            => '이름은 50자 이하여야 합니다.',
            'password.required'   => '비밀번호를 입력해주세요.',
            'password.min'        => '비밀번호는 8자 이상이어야 합니다.',
            'admin_type.required' => '관리자 유형을 선택해주세요.',
            'admin_type.in'       => '관리자 유형은 admin, pastor, minister, volunteer 중 하나여야 합니다.',
            'search_type.in'              => '검색 유형은 id 또는 name 이어야 합니다.',
            'search_type.required_with'   => '검색값을 입력한 경우 검색 유형을 선택해주세요.',
            'search_value.required_with'  => '검색 유형을 선택한 경우 검색값을 입력해주세요.',
            'search_value.max'            => '검색값은 100자 이하여야 합니다.',
            'status.in'                   => '상태 값은 ALIVE, INACTIVE, DELETE 중 하나여야 합니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
