{{-- Add or edit a tomb. $tomb = null for a new one; $allRegions = every region. --}}
@php
    $editing = isset($tomb) && $tomb;
    $modalId = $editing ? 'tomb-edit' : 'tomb-add';
    $regionOptions = $allRegions->pluck('name', 'id')->all();
@endphp
<x-nb.modal :id="$modalId" :title="$editing ? 'تعديل '.$tomb->name : 'مقبرة جديدة'" :icon="$editing ? 'edit-2' : 'plus'" size="lg"
            :action="$editing ? route('cemeteries.tombs.update', $tomb) : route('cemeteries.tombs.store')" :method="$editing ? 'PUT' : 'POST'"
            :submit="$editing ? 'حفظ التعديلات' : 'إضافة المقبرة'" :autoopen="old('_form') === $modalId">
    <input type="hidden" name="_form" value="{{ $modalId }}">
    <div class="nb-form-grid">
        <x-nb.field label="المنطقة" name="region_id" type="select" :options="$regionOptions" :value="$editing ? $tomb->region_id : null" required :row="$modalId" />
        <x-nb.field label="اسم المقبرة" name="name" :value="$editing ? $tomb->name : null" required :row="$modalId" />
        <x-nb.choice label="النوع" name="kind" :options="['eyes' => 'عيون', 'lahd' => 'لحد']" :value="$editing ? $tomb->kind->value : 'eyes'" required />
        <x-nb.choice label="لمين" name="serves" :options="['men' => 'رجال', 'women' => 'سيدات', 'mixed' => 'مختلطة']" :value="$editing ? $tomb->serves->value : 'mixed'" required />
        <x-nb.field label="عدد العيون أو اللحود" name="chambers" type="number" min="0" max="300" :value="$editing ? $tomb->chambers->count() : 0" required :row="$modalId"
                    hint="العين 6 أماكن واللحد دفنة واحدة. العدد يزيد، ويقل بس بالعيون اللي ما اتدفنش فيها حد" />
        <x-nb.field label="التكلفة السنوية" name="annual_cost" type="number" min="0" :value="$editing ? $tomb->annual_cost : null" :row="$modalId" />
        <x-nb.field label="اسم التربي" name="guard_name" :value="$editing ? $tomb->guard_name : null" :row="$modalId" />
        <x-nb.field label="تليفون التربي" name="guard_phone" type="tel" dir="ltr" inputmode="numeric" :value="$editing ? $tomb->guard_phone : null" :row="$modalId" />
        <x-nb.field label="رابط الموقع على الخريطة" name="location" dir="ltr" :value="$editing ? $tomb->location : null" span="full" :row="$modalId" />
    </div>
</x-nb.modal>
