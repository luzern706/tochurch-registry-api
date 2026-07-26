<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IntroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'updateIntro' => [
                'slogan'            => ['sometimes', 'nullable', 'string', 'max:300'],
                // vision/message는 Quill 리치 텍스트 에디터 출력(HTML) 저장 — 태그 오버헤드 감안해 넉넉하게.
                'vision'            => ['sometimes', 'nullable', 'string', 'max:20000'],
                'message'           => ['sometimes', 'nullable', 'string', 'max:20000'],
                'hero_title'        => ['sometimes', 'nullable', 'string', 'max:50'],
                'hero_subtitle'     => ['sometimes', 'nullable', 'string', 'max:100'],
                'hero_buttons'      => ['sometimes', 'nullable', 'array', 'max:2'],
                'hero_buttons.*.label' => ['nullable', 'string', 'max:30'],
                'hero_buttons.*.url'   => ['nullable', 'string', 'max:500'],
                'intro_title'       => ['sometimes', 'nullable', 'string', 'max:200'],
                'welcome_title'     => ['sometimes', 'nullable', 'string', 'max:100'],
                'welcome_highlight' => ['sometimes', 'nullable', 'string', 'max:200'],
                'bible_quote'       => ['sometimes', 'nullable', 'array'],
                'bible_quote.mode'        => ['nullable', 'string', 'in:select,manual'],
                'bible_quote.book'        => ['nullable', 'string', 'max:20'],
                'bible_quote.chapter'     => ['nullable', 'integer', 'min:1'],
                'bible_quote.verse'       => ['nullable', 'string', 'max:20'],
                'bible_quote.manual_text' => ['nullable', 'string', 'max:5000'],
                'directions'        => ['sometimes', 'nullable', 'array'],
                // location_detail/transit_subway/transit_car/parking_info도 에디터(HTML) 대상.
                'directions.location_detail' => ['nullable', 'string', 'max:5000'],
                'directions.contact_note'    => ['nullable', 'string', 'max:200'],
                'directions.transit_subway'  => ['nullable', 'string', 'max:5000'],
                'directions.transit_car'     => ['nullable', 'string', 'max:5000'],
                'directions.transit_navi'    => ['nullable', 'string', 'max:500'],
                'directions.parking_info'    => ['nullable', 'string', 'max:5000'],
            ],
            'updatePastor' => [
                'name'    => ['sometimes', 'string', 'max:50'],
                'type_no' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'career'  => ['sometimes', 'nullable', 'array', 'max:20'],
                'career.*' => ['string', 'max:200'],
            ],
            'uploadPastorPhoto' => [
                'photo' => ['required', 'file', 'image', 'max:10240'],
            ],
            default => [],
        };
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
