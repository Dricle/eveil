<?php

namespace App\Jobs;

use App\Actions\FindContacts;
use App\Enums\ContactSearchStatus;
use App\Models\Company;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reading one company's site for the people worth writing to. Fetching contact
 * pages and calling a model takes long enough that the request never waits for
 * it, and the company row carries the state so the list can say where it got to.
 *
 * Four qualified companies with no address are worth nothing, so this is the
 * step that decides whether the no-purchased-database bet pays off.
 */
class FindCompanyContacts implements ShouldQueue
{
    use Queueable;

    public function __construct(public Company $company, public bool $guessGeneric = false)
    {
        $this->onQueue('ai');
    }

    public function handle(FindContacts $find, CurrentProject $currentProject): void
    {
        $currentProject->run($this->company->project, function () use ($find): void {
            $find->handle($this->company, $this->guessGeneric);

            $this->company->update([
                'contacts_status' => ContactSearchStatus::Done,
                'contacts_searched_at' => now(),
            ]);
        });
    }

    /**
     * "We could not read the site" and "nobody publishes an address here" are
     * different findings, and only the first is worth trying again.
     */
    public function failed(Throwable $e): void
    {
        $this->company->update([
            'contacts_status' => ContactSearchStatus::Failed,
            'contacts_searched_at' => now(),
        ]);
    }
}
