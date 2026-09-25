<x-auth-layout title="Forgot your password?" description="Enter your email address and we’ll send you a link to reset your password.">
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf
        <x-auth.input name="email" label="Email address" type="email" autocomplete="email" required autofocus />
        <x-auth.button>Send reset link</x-auth.button>
    </form>

    <p class="text-center text-sm">
        <x-auth.link :href="route('login')">Back to login</x-auth.link>
    </p>
</x-auth-layout>
