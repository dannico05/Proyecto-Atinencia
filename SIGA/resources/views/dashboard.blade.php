<x-layouts.dashboard :title="__('Main Panel')" :subtitle="__('Teaching eligibility overview for the San Carlos Campus')">
    @php
        $resultLabels = [
            'eligible' => __('Eligible'),
            'not_eligible' => __('Not eligible'),
            'technical_note' => __('Technical note'),
            'no_catalog' => __('No catalog'),
        ];
    @endphp

    <div class="dashboard-grid">
        <section class="dashboard-metrics" aria-label="{{ __('System summary') }}">
            @foreach ([
                ['label' => __('Active teachers'), 'value' => $metrics['teachers']],
                ['label' => __('Complete teacher profiles'), 'value' => $metrics['teachersWithCredentials']],
                ['label' => __('Catalog versions'), 'value' => $metrics['catalogVersions']],
                ['label' => __('Completed verifications'), 'value' => $metrics['verifications']],
            ] as $metric)
                <article class="card dashboard-summary-card">
                    <span>{{ $metric['label'] }}</span>
                    <strong>{{ $metric['value'] }}</strong>
                    @if (isset($metric['description']))
                        <small>{{ $metric['description'] }}</small>
                    @endif
                </article>
            @endforeach
        </section>

        <section class="card workflow-card dashboard-wide">
            <div class="card-head">
                <div>
                    <span class="card-title">{{ __('Cases requiring attention') }}</span>
                    <p class="card-subtitle">{{ __('Operational status of proposed teaching assignments') }}</p>
                </div>
            </div>
            <div class="attention-list">
                <div class="attention-item"><strong>{{ $alerts['blocked'] }}</strong><span>{{ __('Blocked as not eligible') }}</span></div>
                <div class="attention-item"><strong>{{ $alerts['manual'] }}</strong><span>{{ __('Pending manual approval') }}</span></div>
                <div class="attention-item"><strong>{{ $alerts['technical'] }}</strong><span>{{ __('Pending Council ratification') }}</span></div>
                <div class="attention-item"><strong>{{ $alerts['expired'] }}</strong><span>{{ __('Expired technical notes') }}</span></div>
            </div>
        </section>

        <section class="card workflow-card dashboard-wide">
            <div class="card-head">
                <div>
                    <span class="card-title">{{ __('Recent verifications') }}</span>
                    <p class="card-subtitle">{{ __('Latest decisions recorded by the eligibility engine') }}</p>
                </div>
            </div>
            <div class="recent-list">
                @forelse ($recentChecks as $check)
                    <div class="recent-item">
                        <div class="recent-copy">
                            <strong>{{ $check->assignment->teacher->first_name }} {{ $check->assignment->teacher->last_name }}</strong>
                            <span>{{ $check->assignment->group->course->name }}</span>
                        </div>
                        <span class="result-pill result-{{ $check->result }}">{{ $resultLabels[$check->result] ?? $check->result }}</span>
                    </div>
                @empty
                    <div class="empty-row">{{ __('No verifications have been recorded yet.') }}</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.dashboard>
