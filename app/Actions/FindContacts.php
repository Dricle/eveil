<?php

namespace App\Actions;

use App\Ai\Agents\ContactExtractor;
use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Enums\PathHintKind;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Discovery\EmailPattern;
use App\Services\Discovery\EmailVerifier;
use App\Services\Discovery\PageFetcher;
use App\Services\Discovery\PathHints;
use App\Services\Discovery\WebsiteFinder;
use App\Support\HtmlText;
use App\Support\ParsedPage;
use App\Support\Settings;
use App\Support\Url;
use Illuminate\Support\Collection;

/**
 * Turns qualified companies into people we can actually write to.
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
        private PathHints $hints,
        private Settings $settings,
        private WebsiteFinder $websiteFinder,
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
        if ($company->domain === null) {
            return $this->fromListing($company);
        }

        $pages = $this->contactPages($company);

        if ($pages->isEmpty()) {
            return new Collection;
        }

        $extracted = (new ContactExtractor($company->project, $company, $pages))->extract();

        $this->rememberPhone($company, $extracted->structured);

        return $this->persist($company, $extracted->structured, $guessGeneric);
    }

    /**
     * A business with no site of its own published SOMETHING to a source: a
     * directory listing publishes an email directly, kept as-is. A registry
     * record publishes only a legal name and an address, never a website or an
     * email - worth one search-engine shot at finding the site before giving
     * up, since a found site re-enters the normal crawl-and-extract path below
     * rather than a second, thinner one.
     *
     * @return Collection<int, Lead>
     */
    private function fromListing(Company $company): Collection
    {
        $email = $company->facts['email'] ?? null;

        if (is_string($email) && $email !== '' && ! $this->erased($company, $email)) {
            return new Collection([$this->store($company, [
                'email' => $email,
                'email_source' => EmailSource::Scraped,
            ])]);
        }

        $address = $company->facts['address'] ?? null;

        if (! is_string($address) || $address === '') {
            return new Collection;
        }

        $website = $this->websiteFinder->find($company->name, $address, $company->project);
        $domain = $website === null ? null : Url::host($website);

        // A domain another company in this project already owns is left
        // alone rather than colliding with the unique index: the two are
        // already two separate rows (found by different sources, keyed
        // differently with no domain to dedupe on), and merging them is not
        // this step's job.
        if ($domain === null || Company::where('project_id', $company->project_id)->where('domain', $domain)->exists()) {
            return new Collection;
        }

        $company->update(['website' => $website, 'domain' => $domain]);

        return $this->handle($company->fresh());
    }

    /**
     * The homepage plus the handful of pages that carry a name and an address.
     * Reuses the shared crawl cache, so a company already read during
     * qualification costs no request here.
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

        $links = $this->hints->pick(
            $parsed,
            PathHintKind::Contact,
            $company->project,
            $this->settings->int('contacts.max_pages'),
        );

        foreach ($links as $url) {
            $page = $this->fetcher->fetch($url);

            if ($page === null) {
                continue;
            }

            $read = $this->html->parse((string) $page->content, $url);
            $pages->push($read);

            // Both outcomes, not just the wins: a fragment that keeps choosing
            // pages with no address on them is spending a fetch every time, and
            // only the ratio makes that visible.
            $this->hints->record($url, PathHintKind::Contact, str_contains($read->text, '@'));
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

        // A generic address is a weak lead, but at a one-person business it is
        // often the only door, and no lead at all is worth less.
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
     * Last resort for a site that publishes only a phone number. Which is the
     * norm for small local businesses. Every candidate is verified before it is
     * kept: a guess that bounces costs the user's sending reputation, so only
     * an address the mail server CONFIRMS is stored.
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

            // `Valid` alone, never `Risky`. Risky is a catch-all domain or a
            // server that would not answer, so it means "we did not disprove
            // it", not "it exists". Guessing an address is only defensible
            // when the mail server confirms it: a guess nobody confirmed is a
            // bounce, and five bounces in a hundred sends pause the mailbox
            // for every good address behind them.
            //
            // On a segment hosted at a provider that refuses probes, that
            // means nothing is ever guessed. Which is the honest outcome: the
            // step disables itself instead of pretending.
            if ($status === EmailStatus::Valid) {
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
     * An erasure request outlives the address it wiped: the lead row stays,
     * stripped down to a hash, precisely so this check can still be made.
     * Without it the next run reads the same team page and contacts her again.
     */
    private function erased(Company $company, string $email): bool
    {
        return Lead::query()
            ->where('project_id', $company->project_id)
            ->where('email_hash', Lead::hashFor($email))
            ->whereNotNull('erased_at')
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
            ['project_id' => $company->project_id, 'email_hash' => Lead::hashFor((string) $attributes['email'])],
            array_merge($attributes, [
                'company_id' => $company->id,
                'email_status' => $status,
                'email_verified_at' => now(),
                'language' => $company->language,
                'source' => $company->source,
                'source_url' => $company->website ?? $company->source_url,
                'discovered_at' => now(),
            ]),
        );

        return $lead;
    }
}
