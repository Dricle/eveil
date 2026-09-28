<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SocialAccountResource;
use App\Support\CurrentProject;
use App\Support\LinkedinCredentials;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The LinkedIn accounts an organization has connected. Connecting goes
 * through `LinkedinOAuthController`; granting to projects and disconnecting
 * are shared with Bluesky (`SocialAccountController`).
 */
class LinkedinAccountController extends Controller
{
    public function index(CurrentProject $currentProject, LinkedinCredentials $credentials): Response
    {
        $organization = $currentProject->organization();

        return Inertia::render('settings/Linkedin', [
            'accounts' => SocialAccountResource::collection(
                $organization->socialAccounts()->where('platform', SocialPlatform::Linkedin)->with('projects')->orderBy('id')->get()
            ),
            'projects' => ProjectResource::collection(
                $organization->projects()->orderBy('name')->get()->each->setRelation('organization', $organization)
            ),
            // The "Connect performance polling" button only exists at all
            // once the superadmin has configured the second app - a feature
            // that is invisible, not just inert, on an instance without it.
            'statsConfigured' => $credentials->isStatsConfigured(),
        ]);
    }
}
