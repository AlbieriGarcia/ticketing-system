@guest
    <div class="flex items-center gap-2">
        <x-filament::button tag="a" :href="filament()->getLoginUrl()" color="gray" outlined size="sm">
            Sign in
        </x-filament::button>
        <x-filament::button tag="a" :href="filament()->getRegistrationUrl()" size="sm">
            Create account
        </x-filament::button>
    </div>
@endguest
