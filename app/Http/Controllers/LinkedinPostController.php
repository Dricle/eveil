<?php

namespace App\Http\Controllers;

use App\Enums\LinkedinPostStatus;
use App\Http\Requests\LinkedinPostRequest;
use App\Http\Resources\LinkedinPostResource;
use App\Jobs\PublishLinkedinPost;
use App\Models\LinkedinPost;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The approval queue every source (knowledge base, client win, news, manual
 * via Evie) lands in. Nothing publishes without a human approving here,
 * whatever the project's autonomy level says.
 *
 * `LinkedinPost` is project-scoped (`BelongsToProject`), so per
 * `.ai/rules/controllers.md` it is never route-model-bound: `SubstituteBindings`
 * runs before `project.set`, so a bound model would be fetched with the scope
 * not yet applied. Take the id and look it up here instead.
 */
class LinkedinPostController extends Controller
{
    public function index(CurrentProject $currentProject): Response
    {
        $account = $currentProject->getOrFail()->linkedinAccounts()->first();

        return Inertia::render('campaigns/LinkedinPosts', [
            'posts' => LinkedinPostResource::collection(
                LinkedinPost::query()->latest()->get()
            ),
            'hasAccount' => $account !== null,
        ]);
    }

    public function update(LinkedinPostRequest $request, int $linkedinPost): RedirectResponse
    {
        LinkedinPost::query()->findOrFail($linkedinPost)->update($request->validated());

        return to_route('campaigns.linkedin-posts.index');
    }

    public function approve(CurrentProject $currentProject, int $linkedinPost): RedirectResponse
    {
        $post = LinkedinPost::query()->findOrFail($linkedinPost);
        $account = $currentProject->getOrFail()->linkedinAccounts()->first();

        if ($account === null) {
            return to_route('campaigns.linkedin-posts.index')->with('status', 'Connect a LinkedIn account first.');
        }

        $post->update(['status' => LinkedinPostStatus::Approved, 'linkedin_account_id' => $account->id]);

        // Only one of a named/anonymized pair can ever go out: the user just
        // chose which by approving this one.
        $post->sibling()->update(['status' => LinkedinPostStatus::Rejected]);

        PublishLinkedinPost::dispatch($post);

        return to_route('campaigns.linkedin-posts.index');
    }

    public function destroy(int $linkedinPost): RedirectResponse
    {
        LinkedinPost::query()->findOrFail($linkedinPost)->update(['status' => LinkedinPostStatus::Rejected]);

        return to_route('campaigns.linkedin-posts.index');
    }
}
