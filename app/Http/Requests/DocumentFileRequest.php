<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class DocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'file' => [
                'bail',
                'required',
                'file',
                'max:1024',
                'extensions:pdf',
                'mimes:pdf',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        $fail('Le fichier est invalide.');

                        return;
                    }

                    $handle = fopen($value->getRealPath(), 'rb');
                    if ($handle === false) {
                        $fail('Impossible de lire le fichier.');

                        return;
                    }

                    $signature = fread($handle, 5);
                    fclose($handle);

                    if ($signature !== '%PDF-') {
                        $fail('La signature du fichier PDF est invalide.');
                    }
                },
            ],
        ];
    }
}
