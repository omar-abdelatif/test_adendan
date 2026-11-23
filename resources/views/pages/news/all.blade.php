@php
    $categories = ['أخبار عامة', 'أخبار ثقافية', 'أفراح', 'عزاء', 'أخبار رياضية'];
    $tones = ['أخبار عامة' => 'teal', 'أخبار ثقافية' => 'green', 'أفراح' => 'gold', 'عزاء' => 'ink', 'أخبار رياضية' => 'terra'];
    $image = fn ($n) => $n->img ? asset('assets/images/news-imgs/'.$n->img)
        : ($n->category === 'عزاء' ? asset('assets/frontend/images/bg/news/death/0205f1b1728e6eacf3e5935c553516b8.jpg') : asset('assets/frontend/images/icons/default/download.jpeg'));
    $thisMonth = $news->filter(fn ($n) => $n->created_at?->isSameMonth(now()))->count();
@endphp
@extends('layouts.master')
@section('title', 'الأخبار')
@section('content')
    <x-nb.page title="الأخبار" subtitle="الأخبار اللي بتظهر في التطبيق والموقع" :crumbs="['التواصل']">
        <x-slot:actions>
            <x-nb.button icon="plus" open="#news-add">إضافة خبر</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="كل الأخبار" :value="number_format($news->count())" icon="book-open" />
            <x-nb.stat label="الشهر ده" :value="$thisMonth" icon="calendar" tone="gold" />
            <x-nb.stat label="أخبار عزاء" :value="$news->where('category', 'عزاء')->count()" icon="moon" tone="terra" />
            <x-nb.stat label="أخبار أفراح" :value="$news->where('category', 'أفراح')->count()" icon="heart" tone="green" />
        </x-nb.stats>

        <x-nb.card title="كل الأخبار" icon="book-open" flush>
            <x-slot:actions>
                <div class="nb-chips">
                    <a href="#" class="nb-chip is-on" data-nb-filter="#news-table" data-col="2" data-value="">الكل</a>
                    @foreach ($categories as $category)
                        <a href="#" class="nb-chip" data-nb-filter="#news-table" data-col="2" data-value="{{ $category }}">{{ $category }} <em>{{ $news->where('category', $category)->count() }}</em></a>
                    @endforeach
                </div>
            </x-slot:actions>
            <x-nb.table id="news-table" :order="[[3, 'desc']]">
                <thead>
                    <tr><th class="no-sort">الصورة</th><th>العنوان</th><th>التصنيف</th><th>تاريخ النشر</th><th class="no-sort is-actions"></th></tr>
                </thead>
                <tbody>
                    @foreach ($news as $new)
                        <tr>
                            <td><img class="nb-thumb" src="{{ $image($new) }}" alt="" loading="lazy" onerror="this.style.visibility='hidden'"></td>
                            <td class="nb-strong" style="min-width: 280px">{{ $new->title }}</td>
                            <td><x-nb.badge :tone="$tones[$new->category] ?? null">{{ $new->category }}</x-nb.badge></td>
                            <td class="nb-num nb-nowrap" data-order="{{ $new->created_at }}">{{ $new->created_at?->format('Y-m-d') }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    <x-nb.icon-button icon="image" label="الصور والفيديو" :href="route('show.thumbs', $new->id)" />
                                    @if ($new->slug)
                                        <x-nb.icon-button icon="link" label="نسخ رابط الخبر" data-nb-copy="{{ route('site.single_news', $new->slug) }}" />
                                    @endif
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#news-edit-'.$new->id" />
                                    <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#news-delete-'.$new->id" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        </x-nb.card>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="news-add" title="خبر جديد" icon="plus" size="lg" :action="route('news.store')" files submit="نشر الخبر">
        <div class="nb-form-grid">
            <x-nb.field label="عنوان الخبر" name="title" required span="full" />
            <x-nb.choice label="التصنيف" name="category" :options="$categories" required span="full" />
            <x-nb.field label="محتوى الخبر" name="description" type="textarea" rows="7" span="full" data-nb-editor />
            <x-nb.field label="صورة الخبر" name="img" type="file" accept="image/*" />
            <x-nb.field label="صور مصغرة (معرض)" name="thumbnail[]" type="file" accept="image/*" multiple placeholder="اختار صورة أو أكتر" />
            <div class="nb-field nb-span-full">
                <span class="nb-label">روابط فيديو (لو فيه)</span>
                <div class="nb-repeat" id="news-add-videos">
                    <div class="nb-repeat__row"><input type="url" name="url[]" class="nb-input" dir="ltr" placeholder="https://youtube.com/..."></div>
                    <template><div class="nb-repeat__row"><input type="url" name="url[]" class="nb-input" dir="ltr" placeholder="https://youtube.com/..."><button type="button" class="nb-icon-btn nb-icon-btn--danger" data-nb-repeat-remove aria-label="حذف الرابط"><i data-feather="x"></i></button></div></template>
                </div>
                <button type="button" class="nb-btn nb-btn--ghost nb-btn--sm" data-nb-repeat-add="#news-add-videos" style="align-self: flex-start"><i data-feather="plus"></i>رابط تاني</button>
            </div>
        </div>
    </x-nb.modal>

    @foreach ($news as $new)
        <x-nb.modal :id="'news-edit-'.$new->id" :title="'تعديل خبر'" icon="edit-2" size="lg" :action="route('news.update')" files>
            <input type="hidden" name="id" value="{{ $new->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="عنوان الخبر" name="title" :value="$new->title" :row="$new->id" required span="full" />
                <x-nb.choice label="التصنيف" name="category" :options="$categories" :value="$new->category" required span="full" />
                <x-nb.field label="محتوى الخبر" name="description" type="textarea" rows="7" :value="$new->description" :row="$new->id" span="full" data-nb-editor />
                <div class="nb-field">
                    <span class="nb-label">الصورة الحالية</span>
                    <img id="news-img-{{ $new->id }}" class="nb-thumb" style="width: 100%; height: 120px" src="{{ $image($new) }}" alt="" loading="lazy">
                </div>
                <div class="nb-stack-sm">
                    <x-nb.field label="صورة جديدة (اختياري)" name="img" type="file" accept="image/*" :row="$new->id" :preview="'#news-img-'.$new->id" />
                    <x-nb.field label="إضافة صور مصغرة" name="thumbnail[]" type="file" accept="image/*" multiple :row="$new->id" />
                </div>
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'news-delete-'.$new->id" :action="route('news.destroy', $new->id)" :message="'هتحذف خبر «'.$new->title.'»؟'" />
    @endforeach
@endpush

@push('scripts')
    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
    <script>
        // The rich editor starts only when its modal opens, so a long list doesn't build dozens of editors.
        // Bound once: after a form is saved the page is swapped in place and this script runs again.
        (function () {
        if (window.nbNewsBound) return;
        window.nbNewsBound = true;
        const started = new WeakSet();
        document.addEventListener('click', (e) => {
            const opener = e.target.closest('[data-nb-open]');
            if (!opener || !window.ClassicEditor) return;
            const area = document.querySelector(opener.dataset.nbOpen + ' textarea[data-nb-editor]');
            if (!area || started.has(area)) return;
            started.add(area);
            ClassicEditor.create(area, { language: { ui: 'ar', content: 'ar' }, toolbar: ['bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'] })
                .catch(() => started.delete(area));
        });
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-nb-copy]');
            if (!btn) return;
            try { await navigator.clipboard.writeText(btn.dataset.nbCopy); nbToast('اتنسخ رابط الخبر', 'success'); }
            catch (err) { nbToast(btn.dataset.nbCopy, 'info'); }
        });
        })();
    </script>
@endpush

@push('styles')
    <style>
        .ck.ck-editor__main > .ck-editor__editable { min-height: 180px; border-radius: 0 0 12px 12px !important; font-family: 'Cairo', sans-serif; }
        .ck.ck-toolbar { border-radius: 12px 12px 0 0 !important; background: var(--nb-sand) !important; }
        .ck-content { line-height: 1.9; }
    </style>
@endpush
