<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50'],
        ];
    }
}
