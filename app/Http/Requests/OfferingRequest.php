<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class OfferingRequest extends FormRequest
{
    private const METHODS = ['cash', 'transfer'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'         => $this->listRules(),
            'getDetail'       => $this->keyRules(),
            'register'        => $this->registerRules(),
            'update'          => $this->updateRules(),
            'delete'          => $this->keyRules(),
            'getListByMember' => $this->getListByMemberRules(),
            default           => [],
        };
    }

    public function attributes(): array
    {
        return [
            'offering_id' => '헌금 ID',
            'member_id'   => '교인 ID',
            'offer_date'  => '헌금 날짜',
            'category'    => '헌금 항목',
            'amount'      => '금액',
            'method'      => '결제 방법',
        ];
    }

    public function messages(): array
    {
        return [
            'required'       => ':attribute 값을 입력해주세요.',
            'integer'        => ':attribute 은(는) 정수여야 합니다.',
            'numeric'        => ':attribute 은(는) 숫자여야 합니다.',
            'date'           => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'in'             => ':attribute 값이 허용된 값이 아닙니다.',
            'min'            => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'            => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
            'after_or_equal' => ':attribute 은(는) :date 이후여야 합니다.',
            'gte'            => ':attribute 은(는) :value 이상이어야 합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'member_id'  => ['nullable', 'integer', 'min:1'],
            'category'   => ['nullable', 'string', 'max:50'],
            'method'     => ['nullable', 'in:' . implode(',', self::METHODS)],
            'ledger'     => ['nullable', 'string', 'max:100'],
            'from_date'  => ['nullable', 'date'],
            'to_date'    => ['nullable', 'date', 'after_or_equal:from_date'],
            'min_amount' => ['nullable', 'integer', 'min:0'],
            'max_amount' => ['nullable', 'integer', 'gte:min_amount'],
            'keyword'    => ['nullable', 'string', 'max:100'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'size'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function keyRules(): array
    {
        return [
            'offering_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'member_id'  => ['nullable', 'integer', 'min:1'],
            'offer_date' => ['required', 'date'],
            'category'   => ['required', 'string', 'max:50'],
            'amount'     => ['required', 'integer', 'min:0'],
            'method'     => ['nullable', 'in:' . implode(',', self::METHODS)],
            'ledger'     => ['nullable', 'string', 'max:100'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'offering_id' => ['required', 'integer', 'min:1'],
            'member_id'   => ['sometimes', 'nullable', 'integer', 'min:1'],
            'offer_date'  => ['sometimes', 'date'],
            'category'    => ['sometimes', 'string', 'max:50'],
            'amount'      => ['sometimes', 'integer', 'min:0'],
            'method'      => ['sometimes', 'in:' . implode(',', self::METHODS)],
            'ledger'      => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    private function getListByMemberRules(): array
    {
        return array_merge(
            ['member_id' => ['required', 'integer', 'min:1']],
            array_diff_key($this->listRules(), ['member_id' => true])
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
