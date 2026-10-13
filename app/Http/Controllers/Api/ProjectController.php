<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Support\TokenScope;
use Illuminate\Http\JsonResponse;

/**
 * The projects the token reaches, for the `{project}` in every other URL:
 * use the `slug`. Not paginated: an organization has a handful. Wrapped in
 * `data` by hand, like the paginated lists, since resources are unwrapped
 * app-wide for Inertia.
 */
class ProjectController extends Controller
{
    public function index(TokenScope $tokenScope): JsonResponse
    {
        return response()->json([
            'data' => ProjectResource::collection($tokenScope->projects()->with('organization')->orderBy('name')->get()),
        ]);
    }
}
