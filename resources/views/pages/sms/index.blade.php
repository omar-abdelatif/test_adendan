@extends('layouts.master')
@section('title', 'الرسائل')
@section('content')
    <x-nb.page title="رسالة عزاء" subtitle="املا البيانات والرسالة بتتكتب لوحدها، وبتتبعت لكل المشتركين في الرسائل" :crumbs="['التواصل']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="smartphone" :href="route('sms.createNew')">مشتركي الرسائل</x-nb.button>
            <x-nb.button variant="ghost" icon="upload" open="#sms-bulk">مشتركين بالجملة</x-nb.button>
            <x-nb.button variant="ghost" icon="sliders" open="#sms-fees">رسوم الاشتراك</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="أعضاء مشتركين" :value="number_format($members)" icon="users" />
            <x-nb.stat label="غير أعضاء مشتركين" :value="number_format($nonMembers)" icon="user" tone="gold" />
            <x-nb.stat label="المبلغ المحصل" :value="number_format($totalAmount)" unit="ج.م" icon="dollar-sign" tone="green" :note="'الرسوم '.number_format($fees?->amount ?? 0).' ج.م'" />
            <x-nb.stat label="الرصيد المتبقي" value="…" unit="رسالة" icon="message-square" main id="sms-balance-card" />
        </x-nb.stats>

        <form id="sms-form" class="nb-split" style="grid-template-columns: minmax(0, 1fr) 380px" novalidate>
            <x-nb.card title="بيانات الرسالة" icon="feather">
                <div class="nb-form-grid" style="--cols: 2">
                    <x-nb.section icon="user">المتوفي</x-nb.section>
                    <x-nb.field label="اسم المتوفي (ثلاثي)" name="deceased_name" id="name" placeholder="محمد أحمد محمود" required data-sms />
                    <x-nb.choice label="جنس المتوفي" name="gender" :options="['توفي' => 'ذكر', 'توفيت' => 'أنثى']" value="توفي" data-sms />
                    <x-nb.choice label="مكان الوفاة" name="death_place" :options="['بالمنزل' => 'المنزل', 'بمستشفى' => 'المستشفى']" value="بالمنزل" data-sms />
                    <x-nb.field label="عنوان مكان الوفاة" name="death_address" id="death_address" placeholder="مثلاً: بعين شمس" data-sms />

                    <x-nb.section icon="map-pin">الجنازة والدفن</x-nb.section>
                    <x-nb.choice label="وقت صلاة الجنازة" name="prayer_time" span="full" data-sms
                                 :options="['عقب صلاة الظهر' => 'الظهر', 'عقب صلاة الجمعة' => 'الجمعة', 'عقب صلاة العصر' => 'العصر', 'عقب صلاة المغرب' => 'المغرب', 'عقب صلاة العشاء' => 'العشاء']" value="عقب صلاة العصر" />
                    <x-nb.field label="مكان صلاة الجنازة" name="mosque_name" id="mosque_name" placeholder="مسجد النور" data-sms />
                    <x-nb.field label="مكان الدفن" name="burial_place" id="burial_place" type="select" :options="['اكتوبر', 'الفيوم', 'مايو', 'القطامية', 'الغفير', 'زينهم', 'العين السخنة']" data-sms />

                    <x-nb.section icon="home">العزاء</x-nb.section>
                    <label class="nb-check nb-span-full"><input type="checkbox" id="has_condolence" checked data-sms> يوجد عزاء</label>
                    <div class="nb-span-full nb-stack-sm" id="condolence_options">
                        <div class="nb-row">
                            <label class="nb-check"><input type="checkbox" id="men" checked data-sms> عزاء الرجال بالمقر</label>
                            <div class="nb-chips" id="men_days" style="margin-inline-start: 12px">
                                @foreach (['اليوم', 'السبت', 'الأحد', 'الاثنين', 'الأربعاء'] as $day)
                                    <label class="nb-check nb-chip"><input type="checkbox" class="day-check" value="{{ $day }}" data-sms>{{ $day }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="nb-row">
                            <label class="nb-check"><input type="checkbox" id="women" data-sms> عزاء السيدات</label>
                            <div id="women_text" style="flex: 1; min-width: 220px" hidden>
                                <input type="text" id="women_condolence_text" class="nb-input" placeholder="مثال: يوم السبت فقط" data-sms>
                            </div>
                        </div>
                    </div>
                </div>
            </x-nb.card>

            <div class="nb-stack">
                <section class="nb-card" style="padding: 18px">
                    <div style="background: var(--nb-ink); border-radius: 36px; padding: 12px">
                        <div style="background: #F2EEE6; border-radius: 26px; padding: 18px 16px; min-height: 300px">
                            <div class="nb-muted" style="text-align: center; font-size: 12px; margin-bottom: 10px"><strong style="display: block; color: var(--nb-ink); font-size: 14px">ADENDAN</strong>رسالة نصية</div>
                            <div id="sms-preview" style="background: #FFFFFF; border-radius: 16px 16px 16px 4px; padding: 12px 14px; line-height: 1.9; box-shadow: 0 1px 2px rgba(0,0,0,.06)">—</div>
                        </div>
                    </div>
                    <div class="nb-field" style="margin-top: 14px">
                        <label class="nb-label" for="msg_content">نص الرسالة (تقدر تعدّله)</label>
                        <textarea id="msg_content" class="nb-input" rows="4" required></textarea>
                        <span class="nb-hint" id="sms-counter"><span id="char_count">0</span> حرف · <span id="sms_count">1</span> رسالة لكل مشترك</span>
                    </div>
                    <button type="submit" class="nb-btn nb-btn--lg nb-btn--block" style="margin-top: 12px" id="sms-send"><i data-feather="send"></i>إرسال لكل المشتركين</button>
                </section>
            </div>
        </form>

        <x-nb.card title="الرسائل اللي اتبعتت" icon="clock" flush>
            @if ($sms->isEmpty())
                <x-nb.empty icon="message-square" title="لسه مفيش رسائل" />
            @else
                <x-nb.table id="sms-log" :order="[[0, 'desc']]">
                    <thead><tr><th>التاريخ</th><th>الرسالة</th><th>الحالة</th></tr></thead>
                    <tbody>
                        @foreach ($sms as $item)
                            <tr>
                                <td class="nb-num nb-nowrap" data-order="{{ $item->created_at }}">{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                                <td style="min-width: 380px; line-height: 1.8">{{ $item->message }}</td>
                                <td><x-nb.badge :tone="$item->sent_status === 'sent' ? 'green' : 'gold'">{{ $item->sent_status === 'sent' ? 'اتبعتت' : $item->sent_status }}</x-nb.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            @endif
        </x-nb.card>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="sms-bulk" title="إضافة مشتركين بالجملة" icon="upload" :action="route('sms.bulkstore')" files submit="رفع الملف">
        <x-nb.field label="ملف الإكسل" name="import-subscriber" type="file" accept=".xlsx,.xls" required />
    </x-nb.modal>
    <x-nb.modal id="sms-fees" title="رسوم اشتراك الرسائل" icon="sliders" size="sm" :action="route('sms.updateFees')" submit="حفظ الرسوم">
        <x-nb.field label="المبلغ (ج.م)" name="amount" type="number" min="0" :value="$fees?->amount ?? 0" required />
    </x-nb.modal>
    <x-nb.modal id="sms-result" title="نتيجة الإرسال" icon="send" size="sm">
        <div class="nb-stats" style="grid-template-columns: 1fr 1fr">
            <x-nb.stat label="اتبعتت" value="0" icon="check-circle" tone="green" id="sms-ok" />
            <x-nb.stat label="فشلت" value="0" icon="x-circle" tone="terra" id="sms-failed" />
        </div>
        <x-slot:footer>
            <button type="button" class="nb-btn nb-btn--ghost" data-nb-close>إغلاق</button>
            <button type="button" class="nb-btn nb-btn--gold" id="sms-retry" hidden>إعادة الإرسال للي فشلوا</button>
        </x-slot:footer>
    </x-nb.modal>
@endpush

@push('scripts')
    <script>
        (function () {
            const $ = (id) => document.getElementById(id);
            const routes = { send: @json(route('sms.storeMsg')), balance: @json(route('sms.balance')) };
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const value = (name) => (document.querySelector(`input[name="${name}"]:checked`) || {}).value || '';
            let failed = [];

            function count(msg) {
                const len = msg.length;
                const parts = len <= 70 ? 1 : Math.ceil((len - 70) / 67) + 1;
                $('char_count').textContent = len;
                $('sms_count').textContent = parts;
            }

            // Same wording as before: "توفي فلان بالمنزل ... و صلاة الجنازة ... والدفن ب... وعزاء الرجال ... بالمقر"
            function build() {
                const name = $('name').value.trim();
                const hasCond = $('has_condolence').checked, men = $('men').checked, women = $('women').checked;
                const days = [...document.querySelectorAll('.day-check:checked')].map((el) => el.value);
                const womenText = $('women_condolence_text').value.trim();
                $('condolence_options').hidden = !hasCond;
                $('men_days').hidden = !men;
                $('women_text').hidden = !women;
                if (!name) { $('sms-preview').textContent = '—'; $('msg_content').value = ''; count(''); return; }
                let msg = `${value('gender')} ${name} ${value('death_place')}`;
                const addr = $('death_address').value.trim();
                if (addr) msg += ` ${addr}`;
                msg += ` و صلاة الجنازة ${value('prayer_time')}`;
                const mosque = $('mosque_name').value.trim();
                if (mosque) msg += ` بـ${mosque}`;
                const burial = $('burial_place').value;
                if (burial) msg += ` والدفن ب${burial}`;
                const menText = ' وعزاء الرجال' + (days.length ? ` ${days.join(' و')} بالمقر` : ' بالمقر');
                if (!hasCond) msg += ' ولا يوجد عزاء للرجال ولا للسيدات';
                else if (men && women) msg += menText + (womenText ? ` وعزاء السيدات ${womenText}` : '');
                else if (men) msg += `${menText} ولا عزاء للسيدات`;
                else if (women) msg += womenText ? ` وعزاء السيدات ${womenText} ولا عزاء للرجال` : '';
                $('sms-preview').textContent = msg;
                $('msg_content').value = msg;
                count(msg);
            }
            document.querySelectorAll('[data-sms]').forEach((el) => el.addEventListener(el.type === 'text' ? 'input' : 'change', build));
            $('msg_content').addEventListener('input', (e) => { $('sms-preview').textContent = e.target.value || '—'; count(e.target.value); });
            build();

            async function send(numbers) {
                const content = $('msg_content').value.trim();
                if (!content) { nbToast('اكتب اسم المتوفي الأول', 'warning'); return; }
                const button = $('sms-send');
                button.disabled = true;
                button.lastChild.textContent = 'جاري الإرسال…';
                try {
                    const res = await fetch(routes.send, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify(numbers ? { content, numbers } : { content }),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || 'حصلت مشكلة في الإرسال');
                    failed = data.failed_numbers || [];
                    document.querySelector('#sms-ok .nb-stat__value').textContent = data.success_count ?? 0;
                    document.querySelector('#sms-failed .nb-stat__value').textContent = data.failed_count ?? 0;
                    $('sms-retry').hidden = !failed.length;
                    nbModal.open('#sms-result');
                } catch (err) {
                    nbToast(err.message, 'error');
                } finally {
                    button.disabled = false;
                    button.lastChild.textContent = 'إرسال لكل المشتركين';
                }
            }
            $('sms-form').addEventListener('submit', (e) => { e.preventDefault(); send(null); });
            $('sms-retry').addEventListener('click', () => { nbModal.close('#sms-result'); send(failed); });

            fetch(routes.balance, { headers: { Accept: 'application/json' } })
                .then((r) => r.ok ? r.json() : Promise.reject())
                .then((d) => { document.querySelector('#sms-balance-card .nb-stat__value').firstChild.textContent = Number(d.balance || 0).toLocaleString('en-US'); })
                .catch(() => { document.querySelector('#sms-balance-card .nb-stat__value').firstChild.textContent = 'غير متاح'; });
        })();
    </script>
@endpush
