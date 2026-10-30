<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * POST /quotes/{id}/lines and PATCH /quotes/{id}/lines/{lineId}.
 */
final class QuoteLineRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Enum|Exists|In>>
     */
    public function rules(): array
    {
        return QuoteLineRules::rules('', !$this->isMethod('PATCH'));
    }
}
