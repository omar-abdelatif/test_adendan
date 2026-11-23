@extends('layouts.master')
@section('title', 'صور وفيديوهات الخبر')
@section('content')
    <x-nb.page title="صور وفيديوهات الخبر" :subtitle="$news->title" :crumbs="['الأخبار' => route('news.all')]">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="arrow-right" :href="route('news.all')">كل الأخبار</x-nb.button>
            <x-nb.button icon="plus" open="#video-add">إضافة فيديو</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-split-wide">
        <x-nb.card title="الصور المصغرة" :subtitle="$count.' صورة'" icon="image">
            @if ($count > 0)
                <div class="nb-gallery">
                    @foreach ($thumbnails as $thumb)
                        <figure class="nb-gallery__item" style="margin: 0">
                            <a href="{{ asset('assets/images/news-imgs/news-thumbnails/'.$thumb->thumbnail) }}" target="_blank" rel="noopener">
                                <img src="{{ asset('assets/images/news-imgs/news-thumbnails/'.$thumb->thumbnail) }}" alt="صورة من الخبر" loading="lazy">
                            </a>
                            <x-nb.icon-button icon="trash-2" label="حذف الصورة" tone="danger" :open="'#thumb-delete-'.$thumb->id" />
                        </figure>
                    @endforeach
                </div>
            @else
                <x-nb.empty icon="image" title="مفيش صور مصغرة للخبر ده" text="تقدر تضيف صور من تعديل الخبر." />
            @endif
        </x-nb.card>

        <x-nb.card title="الفيديوهات" icon="youtube" flush>
            <div class="nb-list">
                @forelse ($videos as $vid)
                    <div class="nb-list__row">
                        <span class="nb-card__icon" aria-hidden="true"><i data-feather="play"></i></span>
                        <div class="nb-list__main">
                            <strong>فيديو {{ $loop->iteration }}</strong>
                            <a href="{{ $vid->url }}" target="_blank" rel="noopener" dir="ltr" style="text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $vid->url }}</a>
                        </div>
                        <div class="nb-row-actions">
                            <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#video-edit-'.$vid->id" />
                            <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#video-delete-'.$vid->id" />
                        </div>
                    </div>
                @empty
                    <x-nb.empty icon="youtube" title="مفيش فيديوهات" />
                @endforelse
            </div>
        </x-nb.card>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="video-add" title="إضافة فيديو للخبر" icon="youtube" :action="route('video.store', $news->id)" submit="إضافة">
        <div class="nb-repeat" id="video-links">
            <div class="nb-repeat__row"><input type="url" name="url[]" class="nb-input" dir="ltr" placeholder="https://youtube.com/..." required></div>
            <template><div class="nb-repeat__row"><input type="url" name="url[]" class="nb-input" dir="ltr" placeholder="https://youtube.com/..."><button type="button" class="nb-icon-btn nb-icon-btn--danger" data-nb-repeat-remove aria-label="حذف الرابط"><i data-feather="x"></i></button></div></template>
        </div>
        <button type="button" class="nb-btn nb-btn--ghost nb-btn--sm" data-nb-repeat-add="#video-links" style="margin-top: 10px"><i data-feather="plus"></i>رابط تاني</button>
    </x-nb.modal>
    @foreach ($thumbnails as $thumb)
        <x-nb.confirm :id="'thumb-delete-'.$thumb->id" :action="route('thumbs.delete', $thumb->id)" message="هتحذف الصورة دي من الخبر؟" />
    @endforeach
    @foreach ($videos as $vid)
        <x-nb.modal :id="'video-edit-'.$vid->id" title="تعديل رابط الفيديو" icon="edit-2" size="sm" :action="route('video.update')">
            <input type="hidden" name="id" value="{{ $vid->id }}">
            <x-nb.field label="رابط الفيديو" name="url" type="url" dir="ltr" :value="$vid->url" :row="$vid->id" required />
        </x-nb.modal>
        <x-nb.confirm :id="'video-delete-'.$vid->id" :action="route('video.delete', $vid->id)" message="هتحذف الفيديو ده من الخبر؟" />
    @endforeach
@endpush
