<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponse;

class PrayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {
            'getList'      => $this->listRules(),
            'getDetail'    => $this->detailRules(),
            'register'     => $this->registerRules(),
            'update'       => $this->updateRules(),
            'delete'       => $this->deleteRules(),
            'updateStatus' => $this->updateStatusRules(),
            default        => [],
        };
    }

    private function listRules(): array
    {
        return [
            'keyword'    => ['nullable', 'string', 'max:100'],
            'status'     => ['nullable', 'string', 'in:active,completed,cancelled'],
            'linked'     => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date'   => ['nullable', 'date_format:Y-m-d'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'size'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function detailRules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'member_id'          => ['nullable', 'integer', 'min:1'],
            'non_member_name'    => ['nullable', 'string', 'max:50'],
            'title'              => ['required', 'string', 'max:100'],
            'content'            => ['required', 'string', 'max:1000'],
            'visibility'         => ['nullable', 'string', 'in:public,leaders,private'],
            'manager_member_id'  => ['nullable', 'integer', 'min:1'],
            'visit_record_id'    => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'id'                 => ['required', 'integer', 'min:1'],
            'member_id'          => ['nullable', 'integer', 'min:1'],
            'non_member_name'    => ['nullable', 'string', 'max:50'],
            'title'              => ['nullable', 'string', 'max:100'],
            'content'            => ['nullable', 'string', 'max:1000'],
            'visibility'         => ['nullable', 'string', 'in:public,leaders,private'],
            'manager_member_id'  => ['nullable', 'integer', 'min:1'],
            'visit_record_id'    => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function deleteRules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function updateStatusRules(): array
    {
        return [
            'id'     => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:active,completed,cancelled'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
