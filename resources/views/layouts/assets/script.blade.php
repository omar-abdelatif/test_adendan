{{-- jQuery + the DataTables engine (no theme), feather icons, then the dashboard behaviour. --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
<script src="{{ asset('assets/js/adendan.js') }}?v={{ filemtime(public_path('assets/js/adendan.js')) }}"></script>
<script>
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
</script>
