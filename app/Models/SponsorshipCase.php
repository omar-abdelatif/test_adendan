<?php

namespace App\Models;

use App\Modules\Sponsorship\Domain\Enums\BenefitPeriod;
use App\Modules\Sponsorship\Domain\Enums\BenefitType;
use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
use App\Modules\Sponsorship\Domain\Enums\MaritalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A family the association sponsors. Written only through the Sponsorship actions.
 *
 * @property int $id
 * @property string $name
 * @property string|null $national_id
 * @property string|null $phone
 * @property string|null $phone_alt
 * @property int|null $age
 * @property string|null $address
 * @property string|null $governorate
 * @property MaritalStatus|null $marital_status
 * @property string|null $health_status
 * @property int $sons
 * @property int $daughters
 * @property int $monthly_income
 * @property int $pension_income
 * @property int $other_income
 * @property BenefitType $benefit_type
 * @property BenefitPeriod $benefit_period
 * @property string|null $notes
 */
class SponsorshipCase extends Model
{
    use LogsActivity;

    protected $table = 'sponsorship_cases';

    protected $fillable = [
        'name', 'national_id', 'phone', 'phone_alt', 'age', 'address', 'governorate', 'marital_status', 'health_status',
        'sons', 'daughters', 'monthly_income', 'pension_income', 'other_income', 'benefit_type', 'benefit_period', 'notes',
        'legacy_source', 'legacy_id',
    ];

    protected $casts = [
        'benefit_type' => BenefitType::class,
        'benefit_period' => BenefitPeriod::class,
        'marital_status' => MaritalStatus::class,
        'age' => 'integer',
        'sons' => 'integer',
        'daughters' => 'integer',
        'monthly_income' => 'integer',
        'pension_income' => 'integer',
        'other_income' => 'integer',
    ];

    /** @return HasMany<SponsorshipCaseFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(SponsorshipCaseFile::class, 'case_id')->latest('id');
    }

    public function totalIncome(): int
    {
        return $this->monthly_income + $this->pension_income + $this->other_income;
    }

    public function children(): int
    {
        return $this->sons + $this->daughters;
    }

    /**
     * What the case file still needs: the required papers, then the ID number
     * and a phone. Reads the files relation, so eager-load it for lists.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $kinds = $this->files->map(fn (SponsorshipCaseFile $f) => $f->kind)->all();
        $missing = [];
        foreach (CaseDocument::required() as $document) {
            if (! in_array($document, $kinds, true)) {
                $missing[] = $document->label();
            }
        }
        if (! $this->national_id) {
            $missing[] = 'الرقم القومي';
        }
        if (! $this->phone) {
            $missing[] = 'رقم الموبايل';
        }

        return $missing;
    }

    /**
     * Name, national ID or phone. Digits typed with or without the leading zero both match.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }
        $digits = ltrim((string) preg_replace('/\D/', '', $term), '0');
        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%");
            if ($digits !== '') {
                $q->orWhere('national_id', 'like', "%{$digits}%")->orWhere('phone', 'like', "%{$digits}%")->orWhere('phone_alt', 'like', "%{$digits}%");
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('Sponsorship')
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => "تم إضافة حالة {$this->name}.",
                'deleted' => "تم حذف حالة {$this->name}.",
                default => "تم تعديل بيانات حالة {$this->name}.",
            });
    }
}
