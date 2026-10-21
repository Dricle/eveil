<?php

namespace App\Http\Requests;

use App\Enums\BrandAssetKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One image added to a project's visual identity library. PNG, JPEG or
 * WebP: what image models take as references, SVG not among them. The user
 * confirms they hold the rights: these are sent to the image provider and
 * end up in published images.
 */
class BrandAssetRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120', 'dimensions:min_width=64,min_height=64'],
            'kind' => ['required', Rule::enum(BrandAssetKind::class)],
            'description' => ['nullable', 'string', 'max:300'],
            'rights' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Use a PNG, JPEG or WebP file. SVG is not supported: export it as PNG.',
            'rights.accepted' => 'Confirm you hold the rights to this image: it is used in images published under your name.',
        ];
    }
}
