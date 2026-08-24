<?php

namespace App\Http\Controllers\Settings;

use App\Actions\InviteMember;
use App\Actions\RemoveMember;
use App\Actions\SetMemberProjectAccess;
use App\Actions\UpdateMemberRole;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\RemoveMemberRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\ProjectResource;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scoped to the CURRENT project's organization, never `$user->organizations()
 * ->first()`: once a user can accept a second invitation, "first" stops
 * meaning anything.
 */
class MemberController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function index(): Response
    {
        $organization = $this->currentProject->organization();

        return Inertia::render('settings/Members', [
            'members' => MemberResource::collection($organization->users()->orderBy('name')->get()),
            'projects' => ProjectResource::collection($organization->projects()->orderBy('name')->get()),
        ]);
    }

    public function store(InviteMemberRequest $request, InviteMember $invite): RedirectResponse
    {
        $invite->handle(
            $this->currentProject->organization(),
            $request->validated('email'),
            OrganizationRole::from($request->validated('role')),
        );

        return to_route('settings.members.index');
    }

    public function update(
        UpdateMemberRoleRequest $request,
        int $user,
        UpdateMemberRole $updateRole,
        SetMemberProjectAccess $setAccess,
    ): RedirectResponse {
        $organization = $this->currentProject->organization();
        $target = $organization->users()->findOrFail($user);
        $role = OrganizationRole::from($request->validated('role'));

        $updateRole->handle($organization, $target, $role);

        if ($role === OrganizationRole::Member && $request->has('projects')) {
            $setAccess->handle($organization, $target, $request->validated('projects', []));
        }

        return to_route('settings.members.index');
    }

    public function destroy(RemoveMemberRequest $request, int $user, RemoveMember $remove): RedirectResponse
    {
        $organization = $this->currentProject->organization();

        $remove->handle($organization, $organization->users()->findOrFail($user));

        return to_route('settings.members.index');
    }
}
