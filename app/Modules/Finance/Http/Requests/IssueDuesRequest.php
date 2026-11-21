<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Shared\Domain\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Two dashboard forms issue dues to every member:
 * - subscription year: fields `item` (the year) and `amount`
 * - donation campaign: `donation_type`, `delay_other_amount` (the custom name
 *   when the type is "أخرى"; the field is misnamed in the view) and `delay_amount`
 */
class IssueDuesRequest extends FormRequest
{
    public const OTHER = 'أخرى';

    public function rules(): array
    {
        if ($this->routeIs('delays.uploadDonations')) {
            return [
                'donation_type' => ['required', 'string', 'max:255'],
                'delay_other_amount' => ['nullable', 'required_if:donation_type,'.self::OTHER, 'string', 'max:255'],
                'delay_amount' => ['required', 'numeric', 'min:1'],
            ];
        }

        return [
            'item' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'item' => 'السنة',
            'amount' => 'المبلغ',
            'donation_type' => 'نوع التبرع',
            'delay_other_amount' => 'نوع التبرع الأخر',
            'delay_amount' => 'مبلغ المديونية',
        ];
    }

    public function item(): string
    {
        if ($this->routeIs('delays.uploadDonations')) {
            return $this->input('donation_type') === self::OTHER
                ? (string) $this->input('delay_other_amount')
                : (string) $this->input('donation_type');
        }

        return (string) $this->input('item');
    }

    public function amount(): Money
    {
        return Money::pounds($this->input($this->routeIs('delays.uploadDonations') ? 'delay_amount' : 'amount'));
    }
}
