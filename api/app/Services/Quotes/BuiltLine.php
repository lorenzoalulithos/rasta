<?php declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Database\v1\QuoteLine;
use App\Services\Calculator\LineResult;

/**
 * A calculated line not saved yet: the model with its `materials` relation set, and the result.
 */
final readonly class BuiltLine
{
    /**
     * @param QuoteLine  $line   unsaved line with unsaved materials in the `materials` relation
     * @param LineResult $result calculator output for the line
     */
    public function __construct(
        public QuoteLine $line,
        public LineResult $result,
    ) {
    }
}
