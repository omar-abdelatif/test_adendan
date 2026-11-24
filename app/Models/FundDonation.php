<?php

namespace App\Models;

use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Fund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A donation to the sponsorship or new-tombs fund. Written only through
 * Finance\Application\Actions\RecordFundDonation, which also writes the ledger.
 *
 * @property int $id
 * @property Fund $fund
 * @property int $donator_id
 * @property int $amount
 * @property Account $account
 * @property string|null $invoice_no
 * @property string|null $category
 * @property string|null $category_other
 * @property list<string>|null $months
 * @property Carbon $donated_on
 * @property string|null $notes
 * @property int|null $transaction_id
 */
class FundDonation extends Model
{
    use LogsActivity;

    /** Months a donation can cover; a one-time gift leaves them empty. */
    public const MONTHS = ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيه', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    protected $fillable = [
        'fund', 'donator_id', 'amount', 'account', 'invoice_no', 'category', 'category_other', 'months', 'donated_on', 'notes',
        'transaction_id', 'legacy_source', 'legacy_id',
    ];

    protected $casts = [
        'fund' => Fund::class,
        'account' => Account::class,
        'months' => 'array',
        'donated_on' => 'date',
        'amount' => 'integer',
    ];

    /** @return BelongsTo<Donators, $this> */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donators::class, 'donator_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeInFund(Builder $query, Fund $fund): void
    {
        $query->where('fund', $fund->value);
    }

    public function categoryLabel(): ?string
    {
        return $this->category === 'أخرى' && $this->category_other ? $this->category_other : $this->category;
    }

    public function monthsLabel(): string
    {
        return $this->months ? implode('، ', $this->months) : 'مرة واحدة';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('FundDonations')
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => "تم تسجيل تبرع {$this->fund->label()} بمبلغ {$this->amount}.",
                'deleted' => "تم إلغاء تبرع {$this->fund->label()} بمبلغ {$this->amount}.",
                default => "تم تعديل تبرع {$this->fund->label()}.",
            });
    }
}
