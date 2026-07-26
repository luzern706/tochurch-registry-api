<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'getCodes'      => [
                'group_key'  => 'required|string|max:50',
                'is_active'  => 'nullable|boolean',
            ],
            'registerCode'  => [
                'group_key'   => 'required|string|max:50',
                'code_value'  => 'required|string|max:50',
                'code_label'  => 'required|string|max:50',
                'description' => 'nullable|string|max:200',
                'sort_order'  => 'nullable|integer|min:0',
                'is_active'   => 'nullable|boolean',
            ],
            'updateCode'    => [
                'code_id'     => 'required|integer',
                'code_label'  => 'nullable|string|max:50',
                'description' => 'nullable|string|max:200',
                'sort_order'  => 'nullable|integer|min:0',
                'is_active'   => 'nullable|boolean',
            ],
            'deleteCode'    => [
                'code_id'    => 'required|integer',
            ],
            default         => [],
        };
    }
}
