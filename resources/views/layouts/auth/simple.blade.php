<!DOCTYPE html>
<html lang="th" class="auth-theme">
    <head>
        @include('partials.head')
        <link rel="stylesheet" href="{{ asset('css/waongpa-auth.css') }}">
    </head>
    <body class="auth-page min-h-screen antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <img src="{{ asset('favicon.svg') }}" alt="" width="32" height="32" class="auth-logo flex size-14 items-center justify-center rounded-2xl" aria-hidden="true">
                    <span class="auth-brand">Waongpa</span>
                </a>
                <div class="auth-card flex flex-col gap-6 rounded-3xl p-6 sm:p-8">{{ $slot }}</div>
                <a class="mx-auto text-sm text-zinc-600 hover:underline" href="{{ route('home') }}">← กลับหน้าหลัก</a>
            </div>
        </div>
        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist
        @fluxScripts
    </body>
</html>
