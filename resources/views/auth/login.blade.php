<x-auth-layout title="Log in" description="Enter the Demo ID and password of your temporary demo workspace.">
    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
        @csrf
        <x-auth.input name="demo_id" label="Demo ID" autocomplete="username" autocapitalize="characters" spellcheck="false" placeholder="TN-XXXXXX" required autofocus />
        <x-auth.input name="password" label="Password" type="password" autocomplete="current-password" required />

        <label for="remember" class="flex items-center gap-2 text-sm">
            <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember')) class="size-4 rounded border-stone-300 accent-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
            Remember me
        </label>

        <x-auth.button>Log in</x-auth.button>
    </form>

    <form method="POST" action="{{ route('demo.store') }}" class="text-center text-sm text-stone-600 dark:text-stone-400">
        @csrf
        No workspace yet?
        <button type="submit" class="rounded-sm font-medium text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:text-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600 dark:text-teal-400 dark:hover:text-teal-300">Start a demo</button>
    </form>

    <x-demo-notice />
</x-auth-layout>
