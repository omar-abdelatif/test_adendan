<?php

namespace App\Modules\Finance\Http\Requests;

use App\Models\FundDonation;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Fund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A donation to the sponsorship or new-tombs fund. Receipt numbers are unique inside their fund. */
class FundDonationRequest extends FormRequest
{
    public function rules(): array
    {
        $fund = $this->fund();
        $donation = $this->route('donation');
        $categories = $fund->donationCategories();

        return [
            'donator_id' => [$donation ? 'nullable' : 'required', 'integer', Rule::exists('donators', 'id')],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'account' => ['required', Rule::enum(Account::class)],
            'invoice_no' => ['nullable', 'string', 'max:30', Rule::unique('fund_donations', 'invoice_no')
                ->where('fund', $fund->value)->ignore($donation instanceof FundDonation ? $donation->id : null)],
            'category' => $categories ? ['required', Rule::in($categories)] : ['prohibited'],
            'category_other' => ['nullable', 'required_if:category,أخرى', 'string', 'max:255'],
            'months' => ['nullable', 'array'],
            'months.*' => [Rule::in(FundDonation::MONTHS)],
            'donated_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'donator_id' => 'المتبرع', 'amount' => 'المبلغ', 'account' => 'اتدفع فين', 'invoice_no' => 'رقم الإيصال', 'category' => 'بند التبرع',
            'category_other' => 'وصف البند', 'months' => 'الشهور', 'donated_on' => 'تاريخ التبرع', 'notes' => 'ملاحظات',
        ];
    }

    public function messages(): array
    {
        return ['invoice_no.unique' => 'رقم الإيصال ده متسجل قبل كده في '.$this->fund()->label().'.', 'category_other.required_if' => 'اكتب البند لما تختار "أخرى".'];
    }

    /** @return array{amount: int, account: string, invoice_no: ?string, category: ?string, category_other: ?string, months: ?list<string>, donated_on: string, notes: ?string} */
    public function donationData(): array
    {
        /** @var array{amount: int, account: string, invoice_no: ?string, category: ?string, category_other: ?string, months: ?list<string>, donated_on: string, notes: ?string} */
        return [
            'amount' => (int) $this->input('amount'),
            'account' => (string) $this->input('account'),
            'invoice_no' => $this->input('invoice_no'),
            'category' => $this->input('category'),
            'category_other' => $this->input('category_other'),
            'months' => array_values((array) $this->input('months', [])),
            'donated_on' => (string) $this->input('donated_on'),
            'notes' => $this->input('notes'),
        ];
    }

    private function fund(): Fund
    {
        $fund = $this->route('fund');

        return $fund instanceof Fund ? $fund : Fund::from((string) $fund);
    }
}
