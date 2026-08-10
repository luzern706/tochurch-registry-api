<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExpenseRequest extends FormRequest
{
    private const METHODS = ['cash', 'transfer', 'card', 'etc'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'          => $this->listRules(),
            'getDetail'        => $this->keyRules(),
            'register'         => $this->registerRules(),
            'update'           => $this->updateRules(),
            'delete'           => $this->keyRules(),
            'getCategoryStats' => $this->categoryStatsRules(),
            'getReceiptStats'  => $this->listRules(),
            'uploadReceipt'    => $this->uploadReceiptRules(),
            default            => [],
        };
    }

    public function attributes(): array
    {
        return [
            'expense_id'   => '지출 ID',
            'expense_date' => '지출 날짜',
            'category'     => '지출 항목',
            'amount'       => '금액',
            'purpose'      => '사용 목적',
            'vendor'       => '거래처',
            'method'       => '결제 수단',
            'ledger'       => '출금 원장',
            'file'         => '증빙 파일',
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
            'after_or_equal' => ':attribute 은(는) :date 이후여야 합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'category'    => ['nullable', 'string', 'max:50'],
            'method'      => ['nullable', 'in:' . implode(',', self::METHODS)],
            'ledger'      => ['nullable', 'string', 'max:100'],
            'from_date'   => ['nullable', 'date'],
            'to_date'     => ['nullable', 'date', 'after_or_equal:from_date'],
            'has_receipt' => ['nullable', 'boolean'],
            'keyword'     => ['nullable', 'string', 'max:100'],
            'page'        => ['nullable', 'integer', 'min:1'],
            'size'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function keyRules(): array
    {
        return [
            'expense_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'expense_date'          => ['required', 'date'],
            'category'              => ['required', 'string', 'max:50'],
            'amount'                => ['required', 'integer', 'min:0'],
            'purpose'               => ['required', 'string', 'max:200'],
            'vendor'                => ['nullable', 'string', 'max:100'],
            'method'                => ['nullable', 'in:' . implode(',', self::METHODS)],
            'ledger'                => ['nullable', 'string', 'max:100'],
            'note'                  => ['nullable', 'string', 'max:1000'],
            'receipt_files'         => ['nullable', 'array', 'max:5'],
            'receipt_files.*.url'   => ['required_with:receipt_files', 'string', 'max:500'],
            'receipt_files.*.name'  => ['nullable', 'string', 'max:200'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'expense_id'            => ['required', 'integer', 'min:1'],
            'expense_date'          => ['sometimes', 'date'],
            'category'              => ['sometimes', 'string', 'max:50'],
            'amount'                => ['sometimes', 'integer', 'min:0'],
            'purpose'               => ['sometimes', 'string', 'max:200'],
            'vendor'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'method'                => ['sometimes', 'nullable', 'in:' . implode(',', self::METHODS)],
            'ledger'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'note'                  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'receipt_files'         => ['sometimes', 'nullable', 'array', 'max:5'],
            'receipt_files.*.url'   => ['required_with:receipt_files', 'string', 'max:500'],
            'receipt_files.*.name'  => ['nullable', 'string', 'max:200'],
        ];
    }

    private function categoryStatsRules(): array
    {
        return [
            'from_date' => ['required', 'date'],
            'to_date'   => ['required', 'date', 'after_or_equal:from_date'],
            'category'  => ['nullable', 'string', 'max:50'],
        ];
    }

    private function uploadReceiptRules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
