<?php

namespace App\Http\Requests\Api;

use App\Enums\AdPlatform;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A goal reached on the user's site, with the click id its visitor landed
 * with, under the parameter name the network put it in the URL (`gclid`,
 * `msclkid`, `fbclid`). Exactly one of them.
 */
class ConversionStoreRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $parameters = collect(AdPlatform::cases())->map(fn (AdPlatform $platform): string => $platform->clickIdParameter());

        return [
            ...$parameters->mapWithKeys(fn (string $parameter): array => [$parameter => [
                'required_without_all:'.$parameters->reject($parameter)->implode(','),
                'prohibits:'.$parameters->reject($parameter)->implode(','),
                'nullable',
                'string',
                'max:512',
            ]])->all(),
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    /**
     * The network whose click id was sent.
     */
    public function platform(): AdPlatform
    {
        return collect(AdPlatform::cases())->first(fn (AdPlatform $platform): bool => $this->filled($platform->clickIdParameter()));
    }

    public function clickId(): string
    {
        return $this->string($this->platform()->clickIdParameter())->value();
    }

    public function valueMicros(): ?int
    {
        return $this->filled('value') ? (int) round($this->float('value') * 1_000_000) : null;
    }
}
