<x-app-layout>
    <div class="max-w-3xl">
        <div class="mb-8">
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">Profile Settings</h1>
            <p class="mt-1 text-sm text-slate-500">Manage your account details and password</p>
        </div>

        <div class="space-y-6">
            {{-- Profile Information --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-base font-display font-semibold text-slate-900">Profile Information</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Update your name and email address</p>
                </div>
                <div class="p-6">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            {{-- Update Password --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-base font-display font-semibold text-slate-900">Update Password</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Ensure your account uses a secure password</p>
                </div>
                <div class="p-6">
                    <livewire:profile.update-password-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
