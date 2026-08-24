<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the members screen. `projects` is only meaningful for a `member`
 * role: Owner and Admin bypass the grant entirely and see every project, so
 * the screen only shows the picker for rows where it does something.
 *
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // `pivot` is hydrated by the `organizations()` BelongsToMany at
            // query time, not a declared property: `getAttribute()` reads it
            // without claiming a static type the model doesn't actually have.
            'role' => $this->resource->getAttribute('pivot')?->role,
            'is_you' => $this->id === $request->user()?->id,
            'projects' => $this->projects->pluck('id')->all(),
        ];
    }
}
