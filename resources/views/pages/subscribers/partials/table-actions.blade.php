{{-- Row actions for the members DataTable (MembersController@table). Blade escapes every value. --}}
<div class="btn-group" role="group">
    <button class="btn btn-info rounded ms-0" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fa-solid fa-ellipsis-vertical"></i>
    </button>
    <div class="dropdown-menu text-center py-2 px-3">
        <a class="btn btn-primary px-2 py-1 me-2" title="المستحقات السابقة" role="button" href="{{ route('subscription.history', $row->id) }}">
            <i class="icofont icofont-eye"></i>
        </a>
        <a class="btn btn-warning px-2 py-1 me-2" title="تعديل البيانات" role="button" href="{{ route('subscriber.details', $row->id) }}">
            <i class="icofont icofont-ui-edit text-dark"></i>
        </a>
        <button type="button" class="btn btn-info px-2 py-1 ms-0" title="تبرع جديد" data-bs-toggle="modal" data-bs-target="#newdonating_{{ $row->id }}">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </button>
    </div>
</div>
<div class="modal fade" id="newdonating_{{ $row->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5 text-white">تبرع من العضو {{ $row->name }}</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('donations.store') }}" method="post" id="donation_form_{{ $row->id }}" data-donation-id="{{ $row->id }}">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group mb-3">
                                <label class="text-muted text-right">رقم العضوية</label>
                                <input type="number" name="member_id" class="form-control" value="{{ $row->member_id }}" readonly>
                            </div>
                            <div class="form-group mb-3">
                                <label class="text-muted">رقم الإيصال</label>
                                <input type="number" class="form-control" placeholder="رقم الإيصال" name="invoice_no">
                            </div>
                            <div class="form-group">
                                <label class="text-muted">نوع التبرع</label>
                                <select name="donation_type" class="form-select" id="donation_type_{{ $row->id }}" data-donation-id="{{ $row->id }}">
                                    <option value="" selected disabled>نوع التبرع</option>
                                    <option value="مادي">مادي</option>
                                    <option value="أخرى">أخرى</option>
                                </select>
                                <select name="donation_category" id="category-donation-type_{{ $row->id }}" class="text-muted form-select mt-3 d-none" data-donation-id="{{ $row->id }}" disabled>
                                    <option selected disabled>-- إختر نوع التبرع المادي --</option>
                                    <option value="تبرع تنمية">تبرع تنمية</option>
                                    <option value="تبرع إنتساب">تبرع إنتساب</option>
                                    <option value="مقابر قديمة">مقابر قديمة</option>
                                    <option value="ص.مقر">ص.مقر</option>
                                    <option value="ص.سيارة">ص.سيارة</option>
                                </select>
                                <input type="text" class="form-control mt-3 d-none" placeholder="نوع التبرع الأخر" data-donation-id="{{ $row->id }}" id="otherDonation_{{ $row->id }}" name="other_donation" disabled>
                                <input type="number" class="form-control mt-3 d-none" placeholder="المبلغ" data-donation-id="{{ $row->id }}" id="otherDonation_{{ $row->id }}" name="amount" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer mt-3">
                        <button type="button" class="btn btn-danger text-muted" data-bs-dismiss="modal">إغلاق</button>
                        <button type="submit" role="button" class="btn btn-primary text-muted">تأكيد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
