@props([
    'sortable' => false,
    'thClass' => '',
])

@if($sortable)
<th class="th-name accounts-th-sortable {{ $thClass }}" data-sort-key="name" data-sort-type="text" aria-sort="none">
    <button type="button" class="accounts-sort-btn accounts-sort-btn--fullname" aria-haspopup="menu" aria-expanded="false">
        <span class="table-fullname-label">
            Full Name
            <span class="table-col-hint">LN, FN, MN, Suffix</span>
        </span>
        <span class="accounts-sort-icon" aria-hidden="true"></span>
    </button>
</th>
@else
<th class="th-fullname {{ $thClass }}">
    Full Name
    <div class="table-col-hint">LN, FN, MN, Suffix</div>
</th>
@endif
