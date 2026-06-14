<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'yandex_url' => [
                'required',
                'string',
                'url',
                'max:2000',
                function ($attribute, $value, $fail) {
                    $allowedHosts = [
                        'yandex.ru',
                        'yandex.uz',
                        'yandex.com',
                        'maps.yandex.ru',
                        'maps.yandex.uz',
                        'yandex.com/maps',
                    ];

                    $host = parse_url($value, PHP_URL_HOST);

                    if (! $host || ! str_contains($host, 'yandex.')) {
                        $fail('Yandex Maps havolasi noto‘g‘ri.');
                    }
                },
            ],
        ];
    }
}
