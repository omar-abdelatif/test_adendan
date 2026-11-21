<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Miscellaneous;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Infrastructure\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Petty-cash expenses (نثريات), paid from the safe.
 *
 * Editing an amount or deleting an expense records a correcting ledger entry,
 * so the safe balance and the history both stay right (the old code left the
 * safe untouched).
 */
class ManageExpenses
{
    public const IMAGE_FOLDER = 'miscellaneous';

    public function __construct(private readonly Ledger $ledger, private readonly ImageStorage $images) {}

    public function record(string $category, ?string $otherCategory, Money $amount, ?UploadedFile $invoice): Miscellaneous
    {
        $image = $invoice ? $this->images->store($invoice, self::IMAGE_FOLDER) : null;

        return DB::transaction(function () use ($category, $otherCategory, $amount, $image) {
            $expense = Miscellaneous::create([
                'category' => $category,
                'other_category' => $otherCategory,
                'amount' => $amount->toPounds(),
                'invoice_img' => $image,
            ]);

            $this->post($expense, Direction::Out, $amount, $this->item($expense));

            return $expense;
        });
    }

    public function update(Miscellaneous $expense, string $category, ?string $otherCategory, Money $amount, ?UploadedFile $invoice): Miscellaneous
    {
        return DB::transaction(function () use ($expense, $category, $otherCategory, $amount, $invoice) {
            $before = Money::pounds($expense->amount);

            if ($invoice) {
                $expense->invoice_img = $this->images->replace($expense->invoice_img, $invoice, self::IMAGE_FOLDER);
            }
            $expense->fill(['category' => $category, 'other_category' => $otherCategory, 'amount' => $amount->toPounds()])->save();

            if ($amount->greaterThan($before)) {
                $this->post($expense, Direction::Out, $amount->minusFloor($before), 'تعديل '.$this->item($expense));
            } elseif ($before->greaterThan($amount)) {
                $this->post($expense, Direction::In, $before->minusFloor($amount), 'تعديل '.$this->item($expense));
            }

            return $expense;
        });
    }

    public function delete(Miscellaneous $expense): void
    {
        DB::transaction(function () use ($expense) {
            $this->post($expense, Direction::In, Money::pounds($expense->amount), 'إلغاء '.$this->item($expense));
            $expense->delete();
        });

        $this->images->delete($expense->invoice_img, self::IMAGE_FOLDER);
    }

    private function post(Miscellaneous $expense, Direction $direction, Money $amount, string $item): void
    {
        $this->ledger->record(new LedgerEntry(
            type: TransactionType::Miscellaneous,
            direction: $direction,
            amount: $amount,
            item: $item,
            invoiceNo: $expense->id,
            proofImage: $expense->invoice_img ? self::IMAGE_FOLDER.'/'.$expense->invoice_img : null,
        ), allowOverdraft: true);
    }

    /** Same label format as the existing rows: نثريات-<category>-<other>. */
    private function item(Miscellaneous $expense): string
    {
        return 'نثريات-'.$expense->category.'-'.$expense->other_category;
    }
}
