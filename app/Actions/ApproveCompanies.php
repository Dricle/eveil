<?php

namespace App\Actions;

use App\Enums\ContactSearchStatus;
use App\Jobs\FindCompanyContacts;
use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * The user saying yes to a company, which is the only human decision left in
 * the path from a search result to a mail.
 *
 * It is deliberately a decision about the COMPANY and not about the addresses
 * found there today: people turn up at a company for weeks after it was first
 * read, and a yes that only covered the three addresses on screen would have to
 * be given again every time. The permission is read through the company, so
 * whoever is found later inherits it.
 *
 * Saying yes also starts the search for people, when nobody has looked yet.
 * Approving and then waiting for a second click is the click this exists to
 * remove, and a company with nobody at it is worth nothing.
 */
class ApproveCompanies
{
    /**
     * @param  Collection<int, Company>  $companies
     * @return int how many changed hands
     */
    public function handle(Collection $companies, bool $approved = true): int
    {
        $changed = 0;

        foreach ($companies as $company) {
            if (($company->approved_at !== null) === $approved) {
                continue;
            }

            $company->update(['approved_at' => $approved ? now() : null]);
            $changed++;

            if (! $approved || $company->contacts_status !== null) {
                continue;
            }

            // Guessing generic addresses is safe here because only an address
            // the mail server CONFIRMS is kept: an unprovable guess is dropped
            // rather than written to. See `FindContacts::guessGeneric()`.
            $company->update(['contacts_status' => ContactSearchStatus::Queued]);

            FindCompanyContacts::dispatch($company, guessGeneric: true);
        }

        return $changed;
    }
}
