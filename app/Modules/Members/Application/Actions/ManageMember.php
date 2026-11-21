<?php

namespace App\Modules\Members\Application\Actions;

use App\Models\Subscribers;
use App\Modules\Members\Domain\MemberException;
use App\Modules\Members\Domain\MemberStatus;
use App\Modules\Shared\Infrastructure\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Editing and removing a member's record. */
class ManageMember
{
    public function __construct(private readonly ImageStorage $images) {}

    /**
     * Marking a member deceased also clears their outstanding dues.
     *
     * @param  array<string, mixed>  $attributes  validated member fields
     */
    public function update(Subscribers $member, array $attributes, ?UploadedFile $photo, ?UploadedFile $idCard, bool $deceased): Subscribers
    {
        if ($photo) {
            $member->img = $this->images->replace($member->img, $photo, RegisterMember::PHOTO_FOLDER);
        }
        if ($idCard) {
            $member->id_img = $this->images->replace($member->id_img, $idCard, RegisterMember::ID_FOLDER);
        }

        return DB::transaction(function () use ($member, $attributes, $deceased) {
            $member->fill($attributes)->save();

            if ($deceased && (int) $member->status !== MemberStatus::Deceased->value) {
                $member->update(['status' => MemberStatus::Deceased->value]);
                $member->dues()->delete();
            }

            return $member;
        });
    }

    /** Only members without any recorded payment can be deleted; the books must keep the rest. */
    public function delete(Subscribers $member): void
    {
        if ($member->paymentTransactions()->exists()) {
            throw MemberException::hasFinancialHistory();
        }

        // Dues and the SMS subscription go with the member (database cascade).
        $member->delete();

        $this->images->delete($member->img, RegisterMember::PHOTO_FOLDER);
        $this->images->delete($member->id_img, RegisterMember::ID_FOLDER);
    }
}
