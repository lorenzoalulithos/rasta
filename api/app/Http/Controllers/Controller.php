<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Class Controller.
 */
abstract class Controller
{
    /**
     * Default page size of paginated lists (api.md).
     *
     * @var int
     */
    private const DEFAULT_PER_PAGE = 25;

    /**
     * Largest page size a client can ask for.
     *
     * @var int
     */
    private const MAX_PER_PAGE = 100;

    /**
     * `?per_page=` clamped between 1 and 100, 25 by default.
     *
     * @param Request $request
     *
     * @return int
     */
    protected function perPage(Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, $request->integer('per_page', self::DEFAULT_PER_PAGE)));
    }
}
