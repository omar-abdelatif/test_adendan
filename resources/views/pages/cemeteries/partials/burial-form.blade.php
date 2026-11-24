{{--
    One burial form for the whole page: a new burial, a past burial, or an edit.
    Buttons open it with data-burial-open='{"mode":"add|past|edit", "region_id":…, "tomb_id":…, "chamber_id":…, …}'.
    The chamber list shows only chambers with room for the chosen gender ($choices from CemeteryViews::burialChoices).
--}}
@php($reopen = old('_form') === 'burial')
<script type="application/json" id="burial-choices">@json($choices)</script>
<x-nb.modal id="burial-form" title="تسجيل دفنة" icon="moon" size="xl" :action="route('cemeteries.burials.store')" files submit="حفظ الدفنة" :autoopen="$reopen">
    <input type="hidden" name="_form" value="burial">
    <input type="hidden" name="_method" value="POST" disabled data-burial-method>
    <input type="hidden" name="past" value="0" data-burial-past>
    <div class="nb-form-grid" style="--cols: 3"
         data-burial-old='@json($reopen ? old() : null)'>
        <x-nb.section icon="map">المكان</x-nb.section>
        <x-nb.field label="المنطقة" name="region_id" type="select" required id="bf-region" :options="collect($choices)->pluck('name', 'id')->all()" />
        <div data-burial-tomb-wrap><x-nb.field label="المقبرة" name="tomb_id" type="select" id="bf-tomb" :options="[]" empty="— اختار المنطقة الأول —" /></div>
        <div data-burial-chamber-wrap>
            <x-nb.field label="العين أو اللحد" name="chamber_id" type="select" id="bf-chamber" :options="[]" empty="— اختار المقبرة الأول —" />
            <span class="nb-choice-hint" data-burial-hint></span>
        </div>
        <div data-burial-places-wrap><x-nb.field label="عدد الأماكن" name="places" type="number" id="bf-places" min="1" max="6" value="1" hint="من سعة العين (6)" /></div>

        <x-nb.section icon="user">المتوفي</x-nb.section>
        <x-nb.field label="الاسم" name="name" required id="bf-name" span="2" />
        <x-nb.choice label="النوع" name="gender" :options="['male' => 'ذكر', 'female' => 'أنثى']" />
        <x-nb.field label="السن" name="age" type="number" min="0" max="130" id="bf-age" />
        <x-nb.field label="رقم العضوية" name="member_id" type="number" id="bf-member" hint="لو المتوفي عضو في الجمعية" />
        <x-nb.field label="مكان الوفاة" name="death_place" id="bf-death-place" />
        <x-nb.field label="تاريخ الوفاة" name="death_date" type="date" id="bf-death-date" />
        <x-nb.field label="تاريخ الدفن" name="burial_date" type="date" id="bf-burial-date" />
        <x-nb.field label="تكلفة الدفن" name="burial_cost" type="number" min="0" id="bf-cost" />
        <x-nb.field label="المغسل" name="washer" id="bf-washer" />
        <x-nb.field label="الحامل" name="carrier" id="bf-carrier" />
        <x-nb.field label="ملاحظات" name="notes" type="textarea" rows="2" id="bf-notes" span="full" />

        <div class="nb-span-full nb-form-grid" style="--cols: 2" data-burial-files>
            <x-nb.section icon="paperclip">المستندات</x-nb.section>
            <x-nb.field label="صورة" name="photo" type="file" accept="image/*" id="bf-photo" hint="بتتحفظ جوه الداشبورد بس، ومالهاش رابط عام" />
            <x-nb.field label="شهادة الوفاة" name="certificate" type="file" accept=".pdf,image/*" id="bf-certificate" hint="PDF أو صورة" />
        </div>
    </div>
</x-nb.modal>

<x-nb.modal id="burial-delete" title="حذف الدفنة" icon="trash-2" size="sm" :action="route('cemeteries.burials.store')" method="DELETE" submit="أيوه، احذف" danger>
    <div class="nb-confirm">
        <span class="nb-confirm__icon" aria-hidden="true"><i data-feather="alert-triangle"></i></span>
        <strong data-burial-delete-name></strong>
        <span class="nb-muted">لو عايز تفضّي العين، استخدم "تطهير العين" بدل الحذف علشان السجل يفضل.</span>
    </div>
</x-nb.modal>

@push('scripts')
    <script>
        // The burial form: region → tomb → chamber, filtered by the chosen gender.
        (function () {
            const choices = JSON.parse(document.getElementById('burial-choices').textContent || '[]');
            const modal = document.getElementById('burial-form');
            if (!modal) return;
            const form = modal.querySelector('form');
            const field = (name) => form.querySelector(`[name="${name}"]`);
            const wrap = (key) => form.querySelector(`[data-burial-${key}-wrap]`);
            const hint = form.querySelector('[data-burial-hint]');
            const storeUrl = form.getAttribute('action');
            const forWhom = { male: 'لراجل', female: 'لسيدة' };
            let mode = 'add';
            let ownChamber = null;
            let ownPlaces = 0;

            const gender = () => (form.querySelector('[name="gender"]:checked') || {}).value || '';
            const region = () => choices.find((r) => String(r.id) === field('region_id').value);
            const tomb = () => (region() ? region().tombs.find((t) => String(t.id) === field('tomb_id').value) : null);
            const accepts = (t, c, g) => {
                if (t.serves === 'men' && g === 'female') return false;
                if (t.serves === 'women' && g === 'male') return false;
                return !(t.serves === 'mixed' && c.occupants && g && c.occupants !== g);
            };
            const option = (value, text) => { const o = document.createElement('option'); o.value = value; o.textContent = text; return o; };

            function fillTombs(keep) {
                const box = field('tomb_id');
                const r = region();
                box.replaceChildren(option('', r ? (r.village ? '— من غير مقبرة —' : '— اختار المقبرة —') : '— اختار المنطقة الأول —'));
                (r ? r.tombs : []).forEach((t) => box.append(option(t.id, t.name)));
                box.value = keep && r && r.tombs.some((t) => String(t.id) === String(keep)) ? keep : '';
                const village = !!(r && r.village);
                const past = mode === 'past' || mode === 'edit-past';
                wrap('chamber').hidden = village || past;
                wrap('places').hidden = village || past;
                box.required = !village && !past;
                fillChambers(ownChamber);
            }

            function fillChambers(keep) {
                const box = field('chamber_id');
                const t = tomb();
                const g = gender();
                box.replaceChildren();
                hint.textContent = '';
                hint.classList.remove('is-bad');
                if (!t) { box.append(option('', '— اختار المقبرة الأول —')); syncPlaces(); return; }
                if (!t.chambers.length) { box.append(option('', '— المقبرة مفيهاش عيون لسه —')); hint.textContent = 'ضيف عيون للمقبرة من صفحتها الأول.'; hint.classList.add('is-bad'); syncPlaces(); return; }
                // While editing, the burial's own chamber stays listed (its places count as free for it).
                const usable = t.chambers.filter((c) => String(c.id) === String(ownChamber) || (c.free > 0 && accepts(t, c, g)));
                box.append(option('', usable.length ? '— اختار —' : '— مفيش مكان فاضي —'));
                usable.forEach((c) => {
                    const free = c.free + (String(c.id) === String(ownChamber) ? ownPlaces : 0);
                    box.append(option(c.id, t.kind === 'lahd' ? `${c.label} · فاضي` : `${c.label} · فاضي ${free} من 6`));
                });
                box.value = keep && usable.some((c) => String(c.id) === String(keep)) ? keep : '';
                if (!usable.length) {
                    hint.textContent = g ? `مفيش مكان فاضي ${forWhom[g]} في ${t.name}.` : `${t.name} مليانة.`;
                    hint.classList.add('is-bad');
                } else if (g) {
                    hint.textContent = `${usable.length} ${t.kind === 'lahd' ? 'لحد' : 'عين'} فيها مكان ${forWhom[g]}.`;
                } else {
                    hint.textContent = 'اختار النوع علشان تظهر الأماكن المناسبة.';
                }
                syncPlaces();
            }

            function syncPlaces() {
                const t = tomb();
                const c = t && t.chambers.find((x) => String(x.id) === field('chamber_id').value);
                const places = field('places');
                const lahd = t && t.kind === 'lahd';
                wrap('places').hidden = wrap('places').hidden || lahd;
                if (lahd) places.value = 1;
                places.max = c ? Math.max(1, c.free + (String(c.id) === String(ownChamber) ? ownPlaces : 0)) : 6;
            }

            function setValue(name, value) {
                const el = field(name);
                if (!el || el.type === 'file') return;
                if (el.type === 'radio') { form.querySelectorAll(`[name="${name}"]`).forEach((r) => { r.checked = String(r.value) === String(value ?? ''); }); return; }
                el.value = value ?? '';
            }

            function setup(o) {
                o = o || {};
                mode = o.mode === 'edit' && o.past ? 'edit-past' : (o.mode || 'add');
                ownChamber = mode === 'edit' ? o.chamber_id : null;
                ownPlaces = mode === 'edit' ? Number(o.places || 0) : 0;
                const editing = mode === 'edit' || mode === 'edit-past';
                form.reset();
                form.setAttribute('action', editing ? o.url : storeUrl);
                const method = form.querySelector('[data-burial-method]');
                method.disabled = !editing;
                method.value = editing ? 'PUT' : 'POST';
                form.querySelector('[data-burial-past]').value = mode === 'past' ? 1 : 0;
                modal.querySelector('.nb-modal__head h2').textContent = { add: 'تسجيل دفنة', past: 'متوفي سابق (من السجلات)', edit: 'تعديل الدفنة', 'edit-past': 'تعديل متوفي سابق' }[mode];
                ['name', 'gender', 'age', 'member_id', 'death_place', 'death_date', 'burial_date', 'burial_cost', 'washer', 'carrier', 'notes', 'places'].forEach((k) => { if (o[k] !== undefined) setValue(k, o[k]); });
                field('region_id').value = o.region_id || '';
                field('region_id').disabled = mode === 'edit-past';
                field('tomb_id').disabled = mode === 'edit-past';
                form.querySelector('[data-burial-files]').hidden = mode === 'past';
                fillTombs(o.tomb_id);
                if (o.chamber_id) { field('chamber_id').value = o.chamber_id; syncPlaces(); }
            }

            // A chamber drawer that is open gives way to the form (both are page-level modals).
            const closeDrawers = () => document.querySelectorAll('.nb-modal--drawer:not([hidden])').forEach((d) => nbModal.close('#' + d.id));

            $(document).on('click', '[data-burial-open]', function () {
                closeDrawers();
                setup($(this).data('burialOpen'));
                nbModal.open('#burial-form');
            });
            $(document).on('change', '#bf-region', () => fillTombs(null));
            $(document).on('change', '#bf-tomb', () => fillChambers(null));
            $(document).on('change', '#burial-form [name="gender"]', () => fillChambers(field('chamber_id').value));
            $(document).on('change', '#bf-chamber', syncPlaces);

            $(document).on('click', '[data-burial-delete]', function () {
                const o = $(this).data('burialDelete');
                const m = document.getElementById('burial-delete');
                m.querySelector('form').setAttribute('action', o.url);
                m.querySelector('[data-burial-delete-name]').textContent = `هتحذف دفنة ${o.name} نهائياً؟`;
                closeDrawers();
                nbModal.open('#burial-delete');
            });

            // After a refused save the form opens again with what was typed.
            const old = JSON.parse(form.querySelector('[data-burial-old]').dataset.burialOld || 'null');
            if (old) {
                setup({ ...old, mode: Number(old.past) ? 'past' : (old._method === 'PUT' ? 'edit' : 'add'), url: form.getAttribute('action') });
            } else {
                setup({ mode: 'add' });
            }
        })();
    </script>
@endpush
