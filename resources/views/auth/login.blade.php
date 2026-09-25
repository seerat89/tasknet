<x-auth-layout title="Welcome back" description="Log in to your TaskNet account to keep your work moving.">
    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
        @csrf
        <x-auth.input name="email" label="Email address" type="email" autocomplete="username" required autofocus />
        <x-auth.input name="password" label="Password" type="password" autocomplete="current-password" required />

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <label for="remember" class="flex items-center gap-2">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember')) class="size-4 rounded border-stone-300 accent-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                Remember me
            </label>
            <x-auth.link :href="route('password.request')">Forgot password?</x-auth.link>
        </div>

        <x-auth.button>Log in</x-auth.button>
    </form>

    <p class="text-center text-sm text-stone-600 dark:text-stone-400">
        New to TaskNet? <x-auth.link :href="route('register')">Create an account</x-auth.link>
    </p>
</x-auth-layout>
