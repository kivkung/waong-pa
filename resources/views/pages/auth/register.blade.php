<x-layouts::auth :title="__('สมัครสมาชิก')">
    <div class="flex flex-col gap-6">
        <x-auth-header title="สร้างบัญชีใหม่" description="สมัครบัญชี Waongpa เพื่อเริ่มจัดตารางและเข้าร่วมห้องนัดหมาย" />
        <x-auth-session-status class="text-center" :status="session('status')" />
        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input
                name="name" label="ชื่อที่ใช้แสดง" :value="old('name')" type="text"
                required autofocus autocomplete="name" placeholder="ชื่อของคุณ"
            />
            <flux:input
                name="email" label="อีเมล" :value="old('email')" type="email"
                required autocomplete="email" placeholder="email@example.com"
            />
            <flux:input
                name="password" label="รหัสผ่าน" type="password" required
                autocomplete="new-password" placeholder="ตั้งรหัสผ่าน"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <flux:input
                name="password_confirmation" label="ยืนยันรหัสผ่าน" type="password" required
                autocomplete="new-password" placeholder="กรอกรหัสผ่านอีกครั้ง"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">สมัครสมาชิก</flux:button>
            </div>
        </form>
        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>มีบัญชีอยู่แล้ว?</span>
            <flux:link :href="route('login')" wire:navigate>เข้าสู่ระบบ</flux:link>
        </div>
    </div>
</x-layouts::auth>
