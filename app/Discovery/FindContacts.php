<?php

namespace App\Discovery;

use App\Ai\Agents\ContactExtractor;
use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Models\Company;
use App\Models\Erasure;
use App\Models\Lead;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Turns qualified companies into people we can actually write to (story 5.4).
 *
 * Four qualified companies with no address are worth nothing, so this is the
 * step that decides whether the whole no-purchased-database bet pays off.
 */
class FindContacts
{
    public function __construct(
        private PageFetcher $fetcher,
        private HtmlText $html,
        private EmailVerifier $verifier,
    ) {}

    /**
     * Addresses worth trying when a site publishes none. Ordered by how often
     * they exist at a small business in this market.
     */
    private const COMMON_LOCAL_PARTS = ['info', 'contact', 'bonjour', 'hello', 'mail'];

    /**
     * @return Collection<int, Lead>
     */
    public function handle(Company $company, bool $guessGeneric = false): Collection
    {
        $pages = $this->contactPages($company);

        if ($pages->isEmpty()) {
            return new Collection;
        }

        /** @var StructuredAgentResponse $extracted */
        $extracted = (new ContactExtractor($company->project))->prompt($this->prompt($company, $pages));

        $this->rememberPhone($company, $extracted->structured);

        return $this->persist($company, $extracted->structured, $guessGeneric);
    }

    /**
     * The homepage plus the handful of pages that carry a name and an address.
     * Reuses the shared crawl cache, so a company already read during
     * qualification costs no request here (ADR-014).
     *
     * @return Collection<int, ParsedPage>
     */
    private function contactPages(Company $company): Collection
    {
        $home = $this->fetcher->fetch($company->website ?? 'https://'.$company->domain);

        if ($home === null) {
            return new Collection;
        }

        $parsed = $this->html->parse((string) $home->content, (string) $home->url);

        /** @var Collection<int, ParsedPage> $pages */
        $pages = new Collection($parsed->isEmpty() ? [] : [$parsed]);

        /** @var array<int, string> $wanted */
        $wanted = config('eveil.contacts.paths');

        $links = collect($parsed->links)
            ->filter(fn (string $url): bool => Url::host($url) === Url::host((string) $home->url))
            ->filter(function (string $url) use ($wanted): bool {
                $path = mb_strtolower(Url::path($url));

                return collect($wanted)->contains(fn (string $needle): bool => str_contains($path, $needle));
            })
            ->unique()
            ->take((int) config('eveil.contacts.max_pages'));

        foreach ($links as $url) {
            $page = $this->fetcher->fetch($url);

            if ($page !== null) {
                $pages->push($this->html->parse((string) $page->content, $url));
            }
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return Collection<int, Lead>
     */
    private function persist(Company $company, array $extracted, bool $guessGeneric = false): Collection
    {
        $domain = (string) $company->domain;
        $pattern = $this->pattern($extracted, $domain);

        /** @var Collection<int, Lead> $leads */
        $leads = new Collection;

        foreach ($extracted['people'] ?? [] as $person) {
            $lead = $this->storePerson($company, $person, $pattern, $domain);

            if ($lead !== null) {
                $leads->push($lead);
            }
        }

        // A generic address is a weak lead, but at a one-person friterie it is
        // often the only door — and no lead at all is worth less (story 5.4).
        if ($leads->isEmpty()) {
            foreach ($extracted['generic_emails'] ?? [] as $email) {
                $lead = $this->storeGeneric($company, (string) $email);

                if ($lead !== null) {
                    $leads->push($lead);

                    break;
                }
            }
        }

        if ($leads->isEmpty() && $guessGeneric) {
            $guessed = $this->guessGeneric($company);

            if ($guessed !== null) {
                $leads->push($guessed);
            }
        }

        return $leads;
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function pattern(array $extracted, string $domain): ?string
    {
        // A shape read off a real address on the site beats the model's guess.
        foreach ($extracted['people'] ?? [] as $person) {
            $email = (string) ($person['email'] ?? '');

            if ($email !== '' && str_ends_with(mb_strtolower($email), '@'.$domain)) {
                $detected = EmailPattern::detect($email, (string) ($person['first_name'] ?? ''), (string) ($person['last_name'] ?? ''));

                if ($detected !== null) {
                    return $detected;
                }
            }
        }

        $claimed = (string) ($extracted['email_pattern'] ?? '');

        return $claimed === '' ? null : $claimed;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function storePerson(Company $company, array $person, ?string $pattern, string $domain): ?Lead
    {
        $first = trim((string) ($person['first_name'] ?? ''));
        $last = trim((string) ($person['last_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($person['email'] ?? '')));
        $source = EmailSource::Scraped;

        if ($email === '' && $pattern !== null) {
            $email = (string) EmailPattern::apply($pattern, $first, $last, $domain);
            $source = EmailSource::Inferred;
        }

        if ($email === '' || $this->erased($company, $email)) {
            return null;
        }

        return $this->store($company, [
            'first_name' => $first ?: null,
            'last_name' => $last ?: null,
            'title' => trim((string) ($person['title'] ?? '')) ?: null,
            'email' => $email,
            'email_source' => $source,
        ]);
    }

    private function storeGeneric(Company $company, string $email): ?Lead
    {
        $email = mb_strtolower(trim($email));

        if ($email === '' || $this->erased($company, $email)) {
            return null;
        }

        return $this->store($company, ['email' => $email, 'email_source' => EmailSource::Scraped]);
    }

    /**
     * Last resort for a site that publishes only a phone number — which is the
     * norm for small local businesses. Every candidate is verified before it is
     * kept: a guess that bounces costs the user's sending reputation, so only
     * an address the mail server accepts is stored, and never as `valid`.
     */
    private function guessGeneric(Company $company): ?Lead
    {
        $domain = (string) $company->domain;

        foreach (self::COMMON_LOCAL_PARTS as $local) {
            $email = "{$local}@{$domain}";

            if ($this->erased($company, $email)) {
                continue;
            }

            $status = $this->verifier->verify($email);

            if ($status === EmailStatus::Valid || $status === EmailStatus::Risky) {
                return $this->store($company, [
                    'email' => $email,
                    'email_source' => EmailSource::Inferred,
                ], $status);
            }
        }

        return null;
    }

    /**
     * Kept on the company even when no email exists: for this segment the phone
     * is often the only way in, and a later channel will want it.
     *
     * @param  array<string, mixed>  $extracted
     */
    private function rememberPhone(Company $company, array $extracted): void
    {
        $phone = trim((string) ($extracted['phone'] ?? ''));

        if ($phone === '' || ($company->facts['phone'] ?? null) === $phone) {
            return;
        }

        $company->update(['facts' => array_merge($company->facts ?? [], ['phone' => $phone])]);
    }

    /**
     * An erasure request outlives the row it deleted: without this check the
     * next run finds the person again and contacts them (ADR-018).
     */
    private function erased(Company $company, string $email): bool
    {
        return Erasure::query()
            ->where('organization_id', $company->project->organization_id)
            ->where('email_hash', Erasure::hashFor($email))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(Company $company, array $attributes, ?EmailStatus $status = null): Lead
    {
        $status ??= $this->verifier->verify((string) $attributes['email']);

        /** @var Lead $lead */
        $lead = Lead::updateOrCreate(
            ['project_id' => $company->project_id, 'email' => $attributes['email']],
            array_merge($attributes, [
                'company_id' => $company->id,
                'email_status' => $status,
                'email_verified_at' => now(),
                'language' => $company->language,
                'source' => $company->source,
                'source_url' => $company->website,
                'discovered_at' => now(),
            ]),
        );

        return $lead;
    }

    /**
     * @param  Collection<int, ParsedPage>  $pages
     */
    private function prompt(Company $company, Collection $pages): string
    {
        $budget = 12_000;
        $sections = [];

        foreach ($pages as $page) {
            if ($budget <= 0) {
                break;
            }

            $text = mb_substr($page->text, 0, $budget);
            $budget -= mb_strlen($text);
            $sections[] = "## {$page->url}\n{$text}";
        }

        return "Company: {$company->name} ({$company->domain})\n\n".implode("\n\n---\n\n", $sections);
    }
}
