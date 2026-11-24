{{--
    One donation form for the fund's pages. Buttons open it with
    data-donation-open='{"mode":"add","donator_id":…}' or '{"mode":"edit","url":…,"amount":…,…}'.
    Delete buttons carry data-donation-delete='{"url":…,"text":…}'.
--}}
@php
    use App\Modules\Finance\Domain\Enums\Account;
    $phone = fn ($n) => $n ? '0'.ltrim((string) $n, '0') : '';
    $reopen = old('_form') === 'donation';
@endphp
<x-nb.modal id="donation-form" :title="'تبرع جديد · '.$fund->label()" icon="plus-circle" size="lg" :action="route('funds.donations.store', $fund)" submit="تسجيل التبرع" :autoopen="$reopen">
    <input type="hidden" name="_form" value="donation">
    <input type="hidden" name="_donation_url" value="{{ old('_donation_url') }}" data-donation-url>
    <input type="hidden" name="_method" value="PUT" @disabled(! old('_donation_url')) data-donation-method>
    <div class="nb-form-grid">
        <x-nb.field label="المتبرع" name="donator_id" type="select" required span="full" id="df-donor"
                    :options="$donors->mapWithKeys(fn ($d) => [$d->id => $d->name.' · '.$phone($d->mobile_number)])->all()" empty="— اختار المتبرع —" />
        <x-nb.field label="المبلغ" name="amount" type="number" min="1" required id="df-amount" />
        <x-nb.field label="رقم الإيصال" name="invoice_no" id="df-invoice" />
        <x-nb.field label="تاريخ التبرع" name="donated_on" type="date" :value="old('donated_on', now()->toDateString())" :max="now()->toDateString()" required id="df-date" />
        <x-nb.choice label="اتدفع فين" name="account" :options="[Account::Safe->value => 'الخزنة (كاش)', Account::Bank->value => 'البنك (تحويل)']" :value="old('account', Account::Safe->value)" required />
        @if ($categories)
            <x-nb.field label="بند التبرع" name="category" type="select" :options="$categories" required id="df-category" />
            <div data-nb-when="category:أخرى"><x-nb.field label="البند إيه؟" name="category_other" id="df-category-other" /></div>
        @endif
        <x-nb.checks label="عن شهور" name="months[]" :options="$months" :cols="6" class="nb-span-full" />
        <p class="nb-span-full nb-muted" style="margin: -8px 0 0; font-size: 12.5px">سيب الشهور فاضية لو التبرع مرة واحدة.</p>
        <x-nb.field label="ملاحظات" name="notes" span="full" id="df-notes" />
    </div>
</x-nb.modal>

<x-nb.modal id="donation-delete" title="إلغاء التبرع" icon="trash-2" size="sm" :action="route('funds.donations.store', $fund)" method="DELETE" submit="أيوه، الغي التبرع" danger>
    <div class="nb-confirm">
        <span class="nb-confirm__icon" aria-hidden="true"><i data-feather="alert-triangle"></i></span>
        <strong data-donation-delete-text></strong>
        <span class="nb-muted">هيتسجل قيد بيطلّع المبلغ من {{ 'خزنة '.$fund->label() }}، والرصيد يرجع زي ما كان قبل التبرع.</span>
    </div>
</x-nb.modal>

@push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('donation-form');
            if (!modal) return;
            const form = modal.querySelector('form');
            const storeUrl = form.getAttribute('action');
            const addTitle = modal.querySelector('.nb-modal__head h2').textContent;
            const today = @json(now()->toDateString());

            function set(name, value) {
                form.querySelectorAll(`[name="${name}"]`).forEach((el) => {
                    if (el.type === 'radio') el.checked = String(el.value) === String(value ?? '');
                    else if (el.type === 'checkbox') el.checked = (value || []).includes(el.value);
                    else el.value = value ?? '';
                });
            }

            function setup(o) {
                const editing = o.mode === 'edit';
                form.setAttribute('action', editing ? o.url : storeUrl);
                form.querySelector('[data-donation-url]').value = editing ? o.url : '';
                form.querySelector('[data-donation-method]').disabled = !editing;
                modal.querySelector('.nb-modal__head h2').textContent = editing ? 'تعديل التبرع' : addTitle;
                modal.querySelector('[type=submit]').textContent = editing ? 'حفظ التعديل' : 'تسجيل التبرع';
                set('donator_id', o.donator_id);
                form.querySelector('[name="donator_id"]').disabled = editing;
                set('amount', o.amount);
                set('invoice_no', o.invoice_no);
                set('donated_on', o.donated_on || today);
                set('account', o.account || 'safe');
                set('category', o.category);
                set('category_other', o.category_other);
                set('months[]', o.months || []);
                set('notes', o.notes);
                form.querySelectorAll('.nb-error').forEach((el) => el.remove());
                form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
                const category = form.querySelector('[name="category"]');
                if (category) category.dispatchEvent(new Event('change', { bubbles: true }));
            }

            $(document).on('click', '[data-donation-open]', function () {
                document.querySelectorAll('.nb-modal--drawer:not([hidden])').forEach((d) => nbModal.close('#' + d.id));
                setup($(this).data('donationOpen'));
                nbModal.open('#donation-form');
            });

            $(document).on('click', '[data-donation-delete]', function () {
                const o = $(this).data('donationDelete');
                const m = document.getElementById('donation-delete');
                m.querySelector('form').setAttribute('action', o.url);
                m.querySelector('[data-donation-delete-text]').textContent = o.text;
                document.querySelectorAll('.nb-modal--drawer:not([hidden])').forEach((d) => nbModal.close('#' + d.id));
                nbModal.open('#donation-delete');
            });

            // Reopened after a refused save: keep what was typed, and the edit address.
            const url = form.querySelector('[data-donation-url]').value;
            if (url) {
                form.setAttribute('action', url);
                form.querySelector('[name="donator_id"]').disabled = true;
            }
        })();
    </script>
@endpush
