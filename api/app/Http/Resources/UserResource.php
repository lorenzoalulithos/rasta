<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user with its tenant (api.md, Auth).
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
        ];
    }
}
