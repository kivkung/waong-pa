<x-layouts::auth :title="__('เข้าสู่ระบบ')">
    <div class="flex flex-col gap-6">
        <x-auth-header title="เข้าสู่ระบบ" description="เข้าสู่ระบบเพื่อจัดการตารางเวลาและห้องนัดหมายของคุณ" />

        <x-auth-session-status class="text-center" :status="session('status')" />
        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input
                name="email"
                label="อีเมล"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />
            <div class="relative">
                <flux:input
                    name="password"
                    label="รหัสผ่าน"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="กรอกรหัสผ่าน"
                    viewable
                />
                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        ลืมรหัสผ่าน?
                    </flux:link>
                @endif
            </div>
            <flux:checkbox name="remember" label="จดจำการเข้าสู่ระบบ" :checked="old('remember')" />
            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">เข้าสู่ระบบ</flux:button>
            </div>
        </form>
        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>ยังไม่มีบัญชี?</span>
            <flux:link :href="route('register')" wire:navigate>สมัครสมาชิก</flux:link>
        </div>
    </div>
</x-layouts::auth>
