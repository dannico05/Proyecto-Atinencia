<aside class="sidebar" x-persist="sidebar" :class="{ 'mobile-open': mobileOpen, 'collapsed': collapsed }" id="sidebar">
    <div class="logo-row" id="logoRow">
        <div class="logo-wrap" id="logoWrap"><img src="{{ asset('images/logo-utn.AVIF') }}" alt="Universidad Técnica Nacional" class="logo-img"></div>
        <div class="logo-text" id="logoText" data-labels>
            <span class="logo-title">{{ __('UTN System') }}</span>
            <span class="logo-sub">{{ __('Teaching eligibility') }}</span>
        </div>
    </div>

    <nav class="nav-scroll" aria-label="{{ __('Main navigation') }}">
        <div class="nav-group">
            <span class="nav-label" data-labels>{{ __('MAIN') }}</span>
            <a href="{{ route('dashboard') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                <span class="nav-text" data-labels>{{ __('Main Panel') }}</span>
            </a>
        </div>

        <div class="nav-group">
            <span class="nav-label" data-labels>{{ __('TEACHING ELIGIBILITY') }}</span>
            @can('viewAny', \Src\TeachingEligibility\Teacher\Domain\Entities\Teacher::class)
                <a href="{{ route('teaching-eligibility.teachers.index') }}" wire:navigate wire:current="active" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    <span class="nav-text" data-labels>{{ __('Teachers and credentials') }}</span>
                </a>
            @endcan
            @can('viewAny', \Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog::class)
                <a href="{{ route('teaching-eligibility.catalogs.index') }}" wire:navigate wire:current="active" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8"/></svg>
                    <span class="nav-text" data-labels>{{ __('Eligibility catalog') }}</span>
                </a>
            @endcan
            @can('viewAny', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class)
                <a href="{{ route('teaching-eligibility.verifications.index') }}" wire:navigate wire:current="active" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span class="nav-text" data-labels>{{ __('Eligibility verification') }}</span>
                </a>
            @endcan
        </div>

        @if (Auth::user()->can('viewAny', \Src\IdentityAccess\Role\Domain\Entities\Role::class) || Auth::user()->can('viewAny', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class))
            <div class="nav-group">
                <span class="nav-label" data-labels>{{ __('SYSTEM ADMINISTRATION') }}</span>
                @can('viewAny', \Src\IdentityAccess\Role\Domain\Entities\Role::class)
                    <a href="{{ route('identityaccess.role.index') }}" wire:navigate wire:current="active" class="nav-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                        <span class="nav-text" data-labels>{{ __('Roles') }}</span>
                    </a>
                @endcan
                @can('viewAny', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class)
                    <a href="{{ route('identityaccess.permission.index') }}" wire:navigate wire:current="active" class="nav-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span class="nav-text" data-labels>{{ __('Permissions') }}</span>
                    </a>
                @endcan
            </div>
        @endif
    </nav>
</aside>
