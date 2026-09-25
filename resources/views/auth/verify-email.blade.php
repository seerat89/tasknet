<x-auth-layout title="Check your email" description="Verify your email address using the link in your inbox to finish setting up your TaskNet account.">
    <p class="text-sm leading-6 text-stone-600 dark:text-stone-400">
        We sent a verification link to <span class="font-medium break-all text-stone-900 dark:text-stone-100">{{ auth()->user()->email }}</span>.
        If you don’t see it, check your spam folder or request a new link below.
    </p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-auth.button>Resend verification email</x-auth.button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="rounded-sm text-sm font-medium text-stone-600 underline underline-offset-4 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600 dark:text-stone-400 dark:hover:text-stone-100">Log out</button>
    </form>
</x-auth-layout>
