<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * POST /quotes/{id}/transition: `{ to: "sent" | "accepted" | "rejected" | "draft" }`.
 */
final class TransitionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::in([
                QuoteStatus::Sent->value,
                QuoteStatus::Accepted->value,
                QuoteStatus::Rejected->value,
                QuoteStatus::Draft->value,
            ])],
        ];
    }

    /**
     * @return QuoteStatus
     */
    public function target(): QuoteStatus
    {
        return QuoteStatus::from($this->string('to')->toString());
    }
}
