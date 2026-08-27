@props(['title' => null, 'subtitle' => null])

<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="icon-btn" title="{{ __('Expand/collapse menu') }}" aria-label="{{ __('Expand/collapse menu') }}" @click="window.innerWidth < 760 ? mobileOpen = !mobileOpen : collapsed = !collapsed">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="4" y1="6" x2="20" y2="6"></line>
                <line x1="4" y1="12" x2="20" y2="12"></line>
                <line x1="4" y1="18" x2="20" y2="18"></line>
            </svg>
        </button>
        <div class="title-wrap">
            <div class="title">{{ $title ?? __('Main Panel') }}</div>
            <div class="subtitle">{{ $subtitle ?? __('General overview of the academic system') }}</div>
        </div>
    </div>

    <div class="topbar-right">
        <div class="font-switch" title="{{ __('Font size (accessibility)') }}">
            <button type="button" class="font-btn" :class="{ 'active': fontLevel === 'a' }" @click="setFont('a')" aria-label="{{ __('Normal font size') }}">A</button>
            <button type="button" class="font-btn" :class="{ 'active': fontLevel === 'aa' }" @click="setFont('aa')" aria-label="{{ __('Large font size') }}">AA</button>
            <button type="button" class="font-btn" :class="{ 'active': fontLevel === 'aaa' }" @click="setFont('aaa')" aria-label="{{ __('Extra-large font size') }}">AAA</button>
        </div>

        <button type="button" class="icon-btn round" @click="toggleDark()" aria-label="{{ __('Toggle high-contrast dark theme') }}">
            <svg x-show="!dark" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.2" y1="4.2" x2="5.6" y2="5.6"></line>
                <line x1="18.4" y1="18.4" x2="19.8" y2="19.8"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.2" y1="19.8" x2="5.6" y2="18.4"></line>
                <line x1="18.4" y1="5.6" x2="19.8" y2="4.2"></line>
            </svg>
            <svg x-show="dark" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
            </svg>
        </button>

        <div class="profile-wrap" x-data="{ profileOpen: false }" @click.outside="profileOpen = false">
            <div class="profile-btn" @click="profileOpen = !profileOpen">

                <div class="avatar">
                    @if (auth()->user()->avatarUrl())
                    <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}">
                    @else
                    {{ auth()->user()->initials() ?? 'UTN' }}
                    @endif
                </div>
                <svg id="profileChevron" :class="{ 'open': profileOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>


            <div class="profile-menu" :class="{ 'open': profileOpen }">
                <div class="profile-head">
                    <div class="profile-name">{{ auth()->user()->name }}</div>
                    <div class="profile-role">{{ auth()->user()->roles->pluck('name')->join(', ') }}</div>
                </div>
                <div class="profile-items">
                    <a href="{{ route('profile.edit') }}" wire:navigate class="profile-item block">{{ __('Account settings') }}</a>
                    <div class="profile-divider"></div>


                    <form action="{{ route('logout') }}" method="POST" @submit.prevent="fetch($el.action, { method: 'POST', body: new FormData($el) }).then(() => Livewire.navigate('{{ route('home') }}'))">
                        @csrf
                        <button type="submit" class="profile-item logout w-full text-left bg-transparent border-none cursor-pointer" style="font-family: inherit;">
                            {{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
