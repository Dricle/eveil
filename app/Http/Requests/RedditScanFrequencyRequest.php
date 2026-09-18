<?php

namespace App\Http\Requests;

use App\Enums\RedditScanFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedditScanFrequencyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reddit_scan_frequency' => ['required', Rule::enum(RedditScanFrequency::class)],
        ];
    }
}
