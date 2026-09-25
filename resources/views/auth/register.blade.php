<x-auth-layout title="Create your account" description="Bring your projects, tasks, and AI agents together in TaskNet.">
    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
        @csrf
        <x-auth.input name="name" label="Name" autocomplete="name" maxlength="255" required autofocus />
        <x-auth.input name="email" label="Email address" type="email" autocomplete="username" maxlength="255" required />
        <x-auth.input name="password" label="Password" type="password" autocomplete="new-password" required />
        <x-auth.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        <x-auth.button>Create account</x-auth.button>
    </form>

    <p class="text-center text-sm text-stone-600 dark:text-stone-400">
        Already have an account? <x-auth.link :href="route('login')">Log in</x-auth.link>
    </p>
</x-auth-layout>
