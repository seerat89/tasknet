{{-- Feedback while a page request is in flight; shown by resources/js/loading.js. Popovers keep it above open dialogs. --}}
<div data-progress popover="manual" hidden aria-hidden="true" class="tn-progress pointer-events-none fixed inset-x-0 top-0 m-0 h-0.5 w-full origin-left overflow-hidden border-0 bg-teal-600 p-0"></div>

<div data-loader popover="manual" hidden aria-hidden="true" class="tn-loader pointer-events-none fixed inset-0 m-0 h-full w-full max-w-none overflow-hidden border-0 bg-stone-50/70 p-0">
    <div class="flex h-full items-center justify-center">
        <img src="{{ asset('brand/tasknet-mark.svg') }}" alt="" width="56" height="56" class="tn-loader-mark size-14">
    </div>
</div>

<div data-loading-status role="status" aria-live="polite" class="sr-only"></div>
