<x-auth-layout title="Reset your password" description="Choose a new password for your TaskNet account.">
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        @error('token')
            <p role="alert" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror
        <x-auth.input name="email" label="Email address" type="email" :value="$request->query('email')" autocomplete="username" required autofocus />
        <x-auth.input name="password" label="New password" type="password" autocomplete="new-password" required />
        <x-auth.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
        <x-auth.button>Reset password</x-auth.button>
    </form>

    <p class="text-center text-sm">
        <x-auth.link :href="route('login')">Back to login</x-auth.link>
    </p>
</x-auth-layout>
