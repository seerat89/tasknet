<button {{ $attributes->merge(['type' => 'submit', 'class' => 'disabled:opacity-50 disabled:hover:bg-teal-700 inline-flex w-full items-center justify-center rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600']) }}>
    {{ $slot }}
</button>
