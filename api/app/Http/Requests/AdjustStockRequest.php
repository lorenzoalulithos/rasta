<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /stock/{materialId}/adjust: `{ closed, opened, notes? }` as absolute values.
 */
final class AdjustStockRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'closed' => ['required', 'integer', 'min:0'],
            'opened' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
