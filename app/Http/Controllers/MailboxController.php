<?php

namespace App\Http\Controllers;

use App\Http\Requests\MailboxRequest;
use App\Http\Resources\MailboxResource;
use App\Http\Resources\ProjectResource;
use App\Models\EmailAccount;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The mailboxes an organization sends from, and which of its projects may use
 * each one.
 *
 * Scoped to the ORGANIZATION rather than the current project, because that is
 * where the mailbox belongs: one address is often used by two products and
 * never by a third. A project reaches one only through the pivot, so a project
 * created tomorrow starts unable to send until somebody attaches an address on
 * purpose: the safe failure, and the reason this is not a nullable column.
 */
class MailboxController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function index(): Response
    {
        $organization = $this->currentProject->organization();

        return Inertia::render('settings/Mailboxes', [
            'mailboxes' => MailboxResource::collection(
                $organization->emailAccounts()->with('projects')->orderBy('id')->get()
            ),
            // Which projects a mailbox may be granted to: everything this
            // organization owns, so "use for all projects" is a select-all in
            // the UI and never a state in the schema.
            'projects' => ProjectResource::collection($organization->projects()->orderBy('name')->get()),
            // Said out loud on the screen it affects: an instance quietly sending
            // every mail to one address looks exactly like outreach working.
            'redirectTo' => config('eveil.outreach.redirect_to'),
        ]);
    }

    public function store(MailboxRequest $request): RedirectResponse
    {
        $organization = $this->currentProject->organization();

        $mailbox = $organization->emailAccounts()->create($request->safe()->except('projects'));

        $mailbox->projects()->sync($request->validated('projects', []));

        return to_route('settings.mailboxes.index');
    }

    public function update(MailboxRequest $request, int $mailbox): RedirectResponse
    {
        $mailbox = EmailAccount::query()->ownedBy($request->user())->findOrFail($mailbox);

        $mailbox->update($request->safe()->except('projects'));
        $mailbox->projects()->sync($request->validated('projects', []));

        return to_route('settings.mailboxes.index');
    }

    public function destroy(Request $request, int $mailbox): RedirectResponse
    {
        EmailAccount::query()->ownedBy($request->user())->findOrFail($mailbox)->delete();

        return to_route('settings.mailboxes.index');
    }
}
