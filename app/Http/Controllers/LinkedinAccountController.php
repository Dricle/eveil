<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinkedinAccountRequest;
use App\Http\Resources\LinkedinAccountResource;
use App\Http\Resources\ProjectResource;
use App\Models\LinkedinAccount;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The LinkedIn accounts an organization has connected, and which of its
 * projects may draft/post through each one. Same shape as `MailboxController`:
 * organization-scoped, because one LinkedIn identity is often posted through
 * by several products and never a third.
 */
class LinkedinAccountController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function index(): Response
    {
        $organization = $this->currentProject->organization();

        return Inertia::render('settings/Linkedin', [
            'accounts' => LinkedinAccountResource::collection(
                $organization->linkedinAccounts()->with('projects')->orderBy('id')->get()
            ),
            'projects' => ProjectResource::collection(
                $organization->projects()->orderBy('name')->get()->each->setRelation('organization', $organization)
            ),
            'currentProjectFrequency' => $this->currentProject->getOrFail()->linkedin_post_frequency->value,
        ]);
    }

    public function update(LinkedinAccountRequest $request, int $linkedinAccount): RedirectResponse
    {
        $account = LinkedinAccount::query()->ownedBy($request->user())->findOrFail($linkedinAccount);

        $account->projects()->sync($request->validated('projects', []));

        return to_route('settings.linkedin.index');
    }

    public function destroy(Request $request, int $linkedinAccount): RedirectResponse
    {
        LinkedinAccount::query()->ownedBy($request->user())->findOrFail($linkedinAccount)->delete();

        return to_route('settings.linkedin.index');
    }
}
