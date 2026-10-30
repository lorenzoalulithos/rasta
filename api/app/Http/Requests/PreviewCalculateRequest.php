<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Database\v1\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * POST /quotes/{id}/preview-calculate: `{ lines: [...] }` in the POST lines format, plus `id?`.
 */
final class PreviewCalculateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Enum|Exists|In>>
     */
    public function rules(): array
    {
        /** @var Quote $quote */
        $quote = $this->route('quote');

        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['sometimes', 'uuid', Rule::exists('quote_lines', 'id')->where('quote_id', $quote->id)],
            ...QuoteLineRules::rules('lines.*.', true, true),
        ];
    }
}
