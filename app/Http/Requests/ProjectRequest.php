<?php

namespace App\Http\Requests;

use App\Enums\AutonomyLevel;
use App\Models\Organization;
use App\Rules\ReachableUrl;
use App\Support\CurrentProject;
use App\Support\Url;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creating and editing a project ask for the same fields, so they share one
 * request rather than two classes that would have to be kept identical.
 */
class ProjectRequest extends FormRequest
{
    /**
     * Only `store` can ever add a SECOND project to a trial organization —
     * `update` edits the one that already exists, so the trial's one-project
     * limit has nothing to say about it.
     */
    public function authorize(CurrentProject $currentProject): bool
    {
        if ($this->route()?->getName() !== 'projects.store') {
            return true;
        }

        if ($this->organization($currentProject)->hasReachedTrialProjectLimit()) {
            throw ValidationException::withMessages([
                'name' => ['A trial organization may have one project. Add a payment method to create more.'],
            ]);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url:http,https', 'max:255', new ReachableUrl],
            // Only the edit screen sends this one. Creating a project asks for
            // as little as possible, and house style is something you write
            // once you have read what the agent produces without it.
            'prompt_instructions' => ['nullable', 'string', 'max:2000'],
            // Edit screen only, like the instructions above: how much a project
            // is left to do by itself is a decision you take once you have
            // watched it work, not one you can make before it has run.
            'autonomy_level' => ['sometimes', Rule::enum(AutonomyLevel::class)],
            // The throttle on continuous discovery. Null stays uncapped.
            'daily_lead_limit' => ['nullable', 'integer', 'min:1'],
            'lead_limit' => ['nullable', 'integer', 'min:1'],
            // `store` only, and only ever sent by `organizations/Create`'s
            // follow-on redirect: which organization a brand new project with
            // no history joins cannot be read from `CurrentProject`, since a
            // just-created organization has no project of its own yet for the
            // session to have selected.
            'organization_id' => ['sometimes', 'integer'],
        ];
    }

    /**
     * The organization a project being created should join: the one named
     * explicitly (only ever the organization just created on the previous
     * screen, and only usable if this user actually belongs to it), else the
     * usual `CurrentProject`-derived one. Shared between `authorize()` and
     * the controller so both ask the same question the same way.
     */
    public function organization(CurrentProject $currentProject): Organization
    {
        $organizationId = $this->integer('organization_id') ?: null;

        return $organizationId !== null
            ? $this->user()->organizations()->findOrFail($organizationId)
            : $currentProject->organizationForNewProject($this->user());
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('url');

        if (is_string($url)) {
            $this->merge(['url' => Url::fromInput($url)]);
        }
    }
}
