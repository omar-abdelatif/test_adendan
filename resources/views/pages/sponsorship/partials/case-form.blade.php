{{--
    One case form for the page: a new case, or an edit. "إضافة حالة" buttons carry data-case-add,
    "تعديل" buttons carry data-case-edit='{"url":…, "name":…, …}' (Sponsorship\Http\CaseJson).
--}}
@php
    use App\Modules\Sponsorship\Domain\Enums\BenefitPeriod;
    use App\Modules\Sponsorship\Domain\Enums\BenefitType;
    use App\Modules\Sponsorship\Domain\Enums\MaritalStatus;
    $reopen = old('_form') === 'case';
@endphp
<x-nb.modal id="case-form" title="إضافة حالة" icon="user-plus" size="xl" :action="route('sponsorship.cases.store')" files submit="حفظ الحالة" :autoopen="$reopen">
    <input type="hidden" name="_form" value="case">
    <input type="hidden" name="_case_url" value="{{ old('_case_url') }}" data-case-url>
    <input type="hidden" name="_method" value="PUT" @disabled(! old('_case_url')) data-case-method>
    <div class="nb-form-grid" style="--cols: 3">
        <x-nb.section icon="user">بيانات الحالة</x-nb.section>
        <x-nb.field label="الاسم" name="name" required span="2" id="cf-name" />
        <x-nb.field label="الرقم القومي" name="national_id" inputmode="numeric" maxlength="14" dir="ltr" id="cf-national-id" />
        <x-nb.field label="رقم الموبايل" name="phone" type="tel" inputmode="numeric" dir="ltr" id="cf-phone" />
        <x-nb.field label="موبايل تاني" name="phone_alt" type="tel" inputmode="numeric" dir="ltr" id="cf-phone-alt" />
        <x-nb.field label="السن" name="age" type="number" min="0" max="120" id="cf-age" />
        <x-nb.field label="المحافظة" name="governorate" list="cf-governorates" id="cf-governorate" />
        <x-nb.field label="العنوان" name="address" span="2" id="cf-address" />
        <datalist id="cf-governorates">
            @foreach (array_unique([...$governorates, 'القاهرة', 'الجيزة', 'أسوان', 'نصر النوبة', 'الإسكندرية']) as $gov)
                <option value="{{ $gov }}"></option>
            @endforeach
        </datalist>

        <x-nb.section icon="home">الأسرة والحالة</x-nb.section>
        <x-nb.field label="الحالة الاجتماعية" name="marital_status" type="select" :options="MaritalStatus::options()" id="cf-marital" />
        <x-nb.field label="عدد الولاد" name="sons" type="number" min="0" max="30" id="cf-sons" />
        <x-nb.field label="عدد البنات" name="daughters" type="number" min="0" max="30" id="cf-daughters" />
        <x-nb.field label="الحالة الصحية" name="health_status" span="full" placeholder="مثلاً: سليمة، سكر وضغط، عجز" id="cf-health" />

        <x-nb.section icon="dollar-sign">الدخل في الشهر</x-nb.section>
        <x-nb.field label="الدخل الشهري" name="monthly_income" type="number" min="0" id="cf-monthly" />
        <x-nb.field label="المعاش" name="pension_income" type="number" min="0" id="cf-pension" />
        <x-nb.field label="دخل من مصدر تاني" name="other_income" type="number" min="0" id="cf-other" />
        <p class="nb-span-full nb-muted" style="margin: -4px 0 0; font-size: 13px">الإجمالي: <strong class="nb-num" data-case-total>0</strong> ج.م</p>

        <x-nb.section icon="gift">الاستفادة</x-nb.section>
        <x-nb.choice label="نوع الاستفادة" name="benefit_type" :options="BenefitType::options()" required />
        <x-nb.choice label="مدة الاستفادة" name="benefit_period" :options="BenefitPeriod::options()" required />
        <x-nb.field label="ملاحظات" name="notes" type="textarea" rows="2" span="full" id="cf-notes" />

        <x-nb.section icon="paperclip">المستندات</x-nb.section>
        <x-nb.field label="صورة البطاقة" name="id_card" type="file" accept=".pdf,image/*" id="cf-id-card" />
        <x-nb.field label="بحث الحالة" name="research" type="file" accept=".pdf,image/*" id="cf-research" />
        <x-nb.field label="صور تانية" name="photos[]" type="file" accept="image/*" multiple id="cf-photos" hint="بتتحفظ جوه الداشبورد بس" />
    </div>
</x-nb.modal>

@push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('case-form');
            if (!modal) return;
            const form = modal.querySelector('form');
            const storeUrl = form.getAttribute('action');
            const fields = ['name', 'national_id', 'phone', 'phone_alt', 'age', 'address', 'governorate', 'marital_status', 'health_status', 'sons', 'daughters', 'monthly_income', 'pension_income', 'other_income', 'benefit_type', 'benefit_period', 'notes'];

            function total() {
                const n = (name) => Number(form.querySelector(`[name="${name}"]`).value || 0);
                form.querySelector('[data-case-total]').textContent = (n('monthly_income') + n('pension_income') + n('other_income')).toLocaleString('en-US');
            }

            function setup(o) {
                const editing = !!o.url;
                form.setAttribute('action', editing ? o.url : storeUrl);
                form.querySelector('[data-case-url]').value = editing ? o.url : '';
                form.querySelector('[data-case-method]').disabled = !editing;
                modal.querySelector('.nb-modal__head h2').textContent = editing ? `تعديل ${o.name}` : 'إضافة حالة';
                fields.forEach((name) => {
                    const inputs = form.querySelectorAll(`[name="${name}"]`);
                    const value = o[name] ?? '';
                    inputs.forEach((el) => {
                        if (el.type === 'radio') el.checked = String(el.value) === String(value);
                        else el.value = value;
                    });
                });
                form.querySelectorAll('input[type=file]').forEach((el) => { el.value = ''; el.dispatchEvent(new Event('change', { bubbles: true })); });
                form.querySelectorAll('.nb-error').forEach((el) => el.remove());
                form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
                total();
            }

            $(document).on('click', '[data-case-add]', () => { setup({}); nbModal.open('#case-form'); });
            $(document).on('click', '[data-case-edit]', function () {
                document.querySelectorAll('.nb-modal--drawer:not([hidden])').forEach((d) => nbModal.close('#' + d.id));
                setup($(this).data('caseEdit'));
                nbModal.open('#case-form');
            });
            $(document).on('input', '#case-form [name$="_income"]', total);

            // Reopened after a refused save: the typed values are already in the fields.
            const url = form.querySelector('[data-case-url]').value;
            if (url) form.setAttribute('action', url);
            total();
        })();
    </script>
@endpush
