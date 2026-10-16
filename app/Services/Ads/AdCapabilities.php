<?php

namespace App\Services\Ads;

/**
 * A network's limits on one text ad, and which targeting fields it reads.
 * Checked in code before a creative is stored, so the agent hears "headline 3
 * is 34 characters, the limit is 30" in the same run instead of a network
 * error a day later.
 */
final readonly class AdCapabilities
{
    /**
     * @param  list<string>  $targeting  which of keywords, negative_keywords, countries, languages this network uses
     */
    public function __construct(
        public int $minHeadlines,
        public int $maxHeadlines,
        public int $headlineMaxLength,
        public int $minDescriptions,
        public int $maxDescriptions,
        public int $descriptionMaxLength,
        public array $targeting,
    ) {}

    /**
     * Null when the copy fits, otherwise every problem in one sentence each.
     *
     * @param  list<string>  $headlines
     * @param  list<string>  $descriptions
     */
    public function problemsWith(array $headlines, array $descriptions): ?string
    {
        $problems = [
            ...$this->countProblem('headlines', count($headlines), $this->minHeadlines, $this->maxHeadlines),
            ...$this->lengthProblems('Headline', $headlines, $this->headlineMaxLength),
            ...$this->countProblem('descriptions', count($descriptions), $this->minDescriptions, $this->maxDescriptions),
            ...$this->lengthProblems('Description', $descriptions, $this->descriptionMaxLength),
        ];

        return $problems === [] ? null : implode(' ', $problems);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'headlines' => "{$this->minHeadlines} to {$this->maxHeadlines}, at most {$this->headlineMaxLength} characters each",
            'descriptions' => "{$this->minDescriptions} to {$this->maxDescriptions}, at most {$this->descriptionMaxLength} characters each",
            'targeting' => $this->targeting,
        ];
    }

    /**
     * @return list<string>
     */
    private function countProblem(string $field, int $count, int $min, int $max): array
    {
        return $count < $min || $count > $max ? ["Give {$min} to {$max} {$field}, not {$count}."] : [];
    }

    /**
     * @param  list<string>  $texts
     * @return list<string>
     */
    private function lengthProblems(string $label, array $texts, int $max): array
    {
        $problems = [];

        foreach ($texts as $index => $text) {
            $length = mb_strlen($text);

            if ($length > $max) {
                $problems[] = "{$label} ".($index + 1)." is {$length} characters, the limit is {$max}.";
            }
        }

        return $problems;
    }
}
