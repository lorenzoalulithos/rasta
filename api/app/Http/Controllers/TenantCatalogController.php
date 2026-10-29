<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use App\Services\Catalog\PainterCatalogImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;

/**
 * POST /tenant/seed-catalog: the "carica esempio" of the first start (flows.md §8).
 */
final class TenantCatalogController extends Controller
{
    /**
     * @param Request                $request
     * @param PainterCatalogImporter $importer
     *
     * @throws JsonException
     *
     * @return JsonResponse
     */
    public function seed(Request $request, PainterCatalogImporter $importer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Tenant $tenant */
        $tenant = $user->tenant;

        // Trade has only `painter` in v1: other trades are a non-goal, so there is one importer
        if ($importer->hasCatalog($tenant)) {
            return response()->json(['message' => 'Il catalogo non è vuoto: l\'esempio si carica solo al primo avvio.'], Response::HTTP_CONFLICT);
        }

        return response()->json(['data' => $importer->import($tenant)], Response::HTTP_CREATED);
    }
}
