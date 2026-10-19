<x-auth-layout title="Start a demo" description="Create a temporary workspace to try TaskNet and connect your own AI agent.">
    <form method="POST" action="{{ route('demo.store') }}" class="flex flex-col gap-5">
        @csrf

        <div class="flex flex-col gap-2">
            <div class="flex items-start gap-3">
                <input id="terms" name="terms" type="checkbox" value="1" required @checked(old('terms')) @error('terms') aria-invalid="true" aria-describedby="terms-error" @enderror class="mt-1 size-4 shrink-0 rounded border-stone-300 accent-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                <label for="terms" class="text-sm leading-6">I agree to the <a href="{{ route('terms') }}" class="rounded-sm font-medium text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:text-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600 dark:text-teal-400 dark:hover:text-teal-300">Terms</a> and understand that TaskNet is a demonstration service and should not be used for sensitive, confidential or important information.</label>
            </div>
            @error('terms')
                <p id="terms-error" role="alert" class="text-sm font-medium text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
            <p class="pl-7 text-xs leading-5 text-stone-500 dark:text-stone-400">By creating a demo workspace, you acknowledge the <a href="{{ route('privacy') }}" class="rounded-sm underline decoration-stone-300 underline-offset-4 hover:text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600 dark:hover:text-teal-400">Privacy Notice</a>.</p>
        </div>

        <x-auth.button data-loading-text="Starting…">Start demo</x-auth.button>
    </form>

    <p class="text-center text-sm text-stone-600 dark:text-stone-400">
        Already have a workspace?
        <a href="{{ route('login') }}" class="rounded-sm font-medium text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:text-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600 dark:text-teal-400 dark:hover:text-teal-300">Sign in</a>
    </p>
</x-auth-layout>
