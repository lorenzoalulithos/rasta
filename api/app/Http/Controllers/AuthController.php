<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Database\v1\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Bearer token auth for the PWA (api.md, Auth).
 */
final class AuthController extends Controller
{
    /**
     * @param LoginRequest $request
     *
     * @throws ValidationException
     *
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
                    ->where('email', $request->string('email')->lower()->toString())
                    ->first();

        if (null === $user || !Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email o password non corretti.']);
        }

        $token = $user->createToken($request->string('device_name', 'pwa')->toString());

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'user' => new UserResource($user->load('tenant')),
            ],
        ]);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    /**
     * @param Request $request
     *
     * @return UserResource
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('tenant'));
    }
}
