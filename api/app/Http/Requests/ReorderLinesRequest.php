<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Database\v1\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * POST /quotes/{id}/lines/reorder: `{ ids: [] }` of lines of this quote.
 */
final class ReorderLinesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Exists>>
     */
    public function rules(): array
    {
        /** @var Quote $quote */
        $quote = $this->route('quote');

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid', 'distinct', Rule::exists('quote_lines', 'id')->where('quote_id', $quote->id)],
        ];
    }
}
