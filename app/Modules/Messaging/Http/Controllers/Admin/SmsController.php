<?php

namespace App\Modules\Messaging\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\SMSFEES;
use App\Models\SMSMSGS;
use App\Models\SMSSubscribers;
use App\Models\Subscribers;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Members\Domain\MemberStatus;
use App\Modules\Messaging\Application\SmsCampaigns;
use App\Modules\Messaging\Application\SmsSubscriptions;
use App\Modules\Messaging\Domain\SmsSender;
use App\Modules\Messaging\Infrastructure\Imports\SmsSubscribersImport;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/** The paid SMS service in the dashboard. */
class SmsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct(private readonly SmsSubscriptions $subscriptions)
    {
        $this->middleware('permission:الرسائل')->except('sendReminders');
        $this->middleware('permission:الاشتراكات')->only('sendReminders');
    }

    public function index()
    {
        return view('pages.sms.index', [
            'sms' => SMSMSGS::latest('id')->get(),
            'totalAmount' => PaymentTransaction::inFund(Fund::General)->whereIn('transaction_type', TransactionType::smsValues())->sum('amount'),
            'nonMembers' => SMSSubscribers::whereNull('member_id')->count(),
            'members' => SMSSubscribers::whereNotNull('member_id')->count(),
            'fees' => SMSFEES::query()->orderBy('id')->first(),
        ]);
    }

    public function updateFee(Request $request)
    {
        $request->validate(['amount' => ['required', 'integer', 'min:0']]);
        $this->subscriptions->updateFee(Money::pounds($request->input('amount')));

        return $this->success('تم تحديث الرسوم بنجاح');
    }

    public function create()
    {
        $subscribedIds = SMSSubscribers::whereNotNull('member_id')->pluck('member_id');

        return view('pages.sms.create_sub', [
            'subscribed' => SMSSubscribers::latest('id')->get(),
            'subscribers' => Subscribers::select('id', 'member_id', 'name', 'mobile_no')
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', MemberStatus::Deceased->value))
                ->where('mobile_no', '!=', 0)
                ->whereNotIn('member_id', $subscribedIds)
                ->get(),
        ]);
    }

    /** Cash paid at the office. A member is chosen from the list; a non-member gives only a number. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'integer', 'exists:subscribers,member_id'],
            'mobile_no' => ['required', 'regex:/^0?1[0-9]{9}$/'],
        ], [], ['mobile_no' => 'رقم المحمول']);

        $this->subscriptions->subscribeAtOffice(isset($data['member_id']) ? (int) $data['member_id'] : null, $data['mobile_no']);

        return $this->success('تم التسجيل بنجاح');
    }

    public function renew(int $id): JsonResponse
    {
        $this->subscriptions->renewAtOffice(SMSSubscribers::findOrFail($id));

        return response()->json(['message' => 'تم التجديد بنجاح']);
    }

    public function send(Request $request, SmsCampaigns $campaigns): JsonResponse
    {
        $request->validate(['content' => ['required', 'string', 'max:1000']]);

        try {
            return response()->json($campaigns->announce($request->string('content')));
        } catch (DomainException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
    }

    public function balance(SmsSender $sender): JsonResponse
    {
        return response()->json(['balance' => $sender->balance()]);
    }

    public function import(Request $request)
    {
        $request->validate(['import-subscriber' => ['required', 'file', 'mimes:xlsx,xls']]);

        $import = new SmsSubscribersImport;
        Excel::import($import, $request->file('import-subscriber'), null, ExcelFormat::XLSX);

        return $this->success("تم استيراد {$import->imported} مشترك بنجاح");
    }

    public function destroy(int $id)
    {
        SMSSubscribers::findOrFail($id)->delete();

        return $this->success('تم حذف المشترك بنجاح');
    }

    /** The "send expiry reminders now" button on the subscriptions page. */
    public function sendReminders(): JsonResponse
    {
        Artisan::call('subscriptions:notify-expiry');

        return response()->json(['success' => true, 'message' => trim(Artisan::output())]);
    }
}
