<div class="workflow-stack">
    <section class="card workflow-card">
        <div class="card-head">
            <div>
                <span class="card-title">{{ __('Propose a teacher and verify eligibility automatically') }}</span>
                <p class="card-subtitle">{{ __('When the proposal is submitted, the system synchronously selects the catalog version for the target term and calculates the result. The result is never selected manually.') }}</p>
            </div>
        </div>
        <div class="workflow-form">
            <div class="form-field">
                <label for="verificationTeacher">{{ __('Teacher') }}</label>
                <select id="verificationTeacher" wire:model="form.teacherId">
                    <option value="0">{{ __('Select an option') }}</option>
                    @foreach ($options['teachers'] as $teacher)
                        <option value="{{ $teacher['id'] }}">{{ $teacher['label'] }}</option>
                    @endforeach
                </select>
                @error('form.teacherId') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field workflow-grow">
                <label for="verificationGroup">{{ __('Target group') }}</label>
                <select id="verificationGroup" wire:model="form.groupId">
                    <option value="0">{{ __('Select an option') }}</option>
                    @foreach ($options['groups'] as $group)
                        <option value="{{ $group['id'] }}">{{ $group['label'] }}</option>
                    @endforeach
                </select>
                @error('form.groupId') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            @can('create', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class)
                <button type="button" class="btn btn-primary workflow-submit" wire:click="verify" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="verify">{{ __('Propose and verify automatically') }}</span>
                    <span wire:loading wire:target="verify">{{ __('Verifying automatically...') }}</span>
                </button>
            @endcan
        </div>
    </section>

    <section class="card workflow-card">
        <div class="card-head">
            <div>
                <span class="card-title">{{ __('Verification history') }}</span>
                <p class="card-subtitle">{{ __('Every result preserves the catalog version and decision trace used at that moment.') }}</p>
            </div>
            @if (Auth::user()->can('exportPdf', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class) || Auth::user()->can('exportExcel', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class))
                <div class="download-wrap" x-data="{ open: false }" x-on:click.outside="open = false">
                    <button type="button" class="btn btn-primary" @click="open = !open">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>{{ __('Download') }}</span>
                    </button>
                    <div class="download-menu" :class="{ 'open': open }">
                        @can('exportPdf', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class)
                            <button type="button" class="download-item" wire:click="exportPdf" @click="open = false">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:#DC2626"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="9" y2="17"/><line x1="12" y1="13" x2="12" y2="17"/><line x1="15" y1="13" x2="15" y2="17"/></svg>
                                <span>{{ __('Export to PDF') }}</span>
                            </button>
                        @endcan
                        @if (Auth::user()->can('exportPdf', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class) && Auth::user()->can('exportExcel', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class))
                            <div class="download-divider"></div>
                        @endif
                        @can('exportExcel', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class)
                            <button type="button" class="download-item" wire:click="exportExcel" @click="open = false">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:#16A34A"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13l2.5 5L14 13"/></svg>
                                <span>{{ __('Export to Excel') }}</span>
                            </button>
                        @endcan
                    </div>
                </div>
            @endif
        </div>

        <div class="verification-summary" aria-label="{{ __('Pending case summary') }}">
            <div><strong>{{ $summary['manual'] }}</strong><span>{{ __('Pending manual approval') }}</span></div>
            <div><strong>{{ $summary['technical'] }}</strong><span>{{ __('Technical note - ratification pending') }}</span></div>
            <div><strong>{{ $summary['expired'] }}</strong><span>{{ __('Expired technical notes') }}</span></div>
        </div>

        <div class="verification-list">
            @forelse ($history as $check)
                @php
                    $resultLabels = [
                        'eligible' => __('Eligible'),
                        'not_eligible' => __('Not eligible'),
                        'technical_note' => __('Technical note'),
                        'no_catalog' => __('No catalog'),
                    ];
                    $statusLabels = [
                        'confirmed' => __('Confirmed'),
                        'blocked' => __('Blocked'),
                        'pending_manual_approval' => __('Pending manual approval'),
                        'approved' => __('Approved'),
                        'rejected' => __('Rejected'),
                        'technical_note_pending' => __('Technical note - ratification pending'),
                        'technical_note_expired' => __('Technical note expired'),
                        'technical_note_ratified' => __('Technical note ratified'),
                        'technical_note_rejected' => __('Technical note rejected'),
                    ];
                    $noteLabels = [
                        'pending' => __('Ratification pending'),
                        'expired' => __('Expired'),
                        'ratified' => __('Ratified'),
                        'rejected' => __('Rejected'),
                    ];
                @endphp
                <article class="verification-record">
                    <div class="verification-main">
                        <div class="verification-heading">
                            <span class="result-pill result-{{ $check['result'] }}">{{ $resultLabels[$check['result']] ?? $check['result'] }}</span>
                            @if ($check['provisional'] && ! in_array($check['technical_note_status'], ['ratified', 'rejected'], true))
                                <span class="provisional-pill">{{ __('Provisional due to future validity') }}</span>
                            @endif
                        </div>
                        <h3>{{ $check['teacher'] }} <span>({{ $check['national_id'] }})</span></h3>
                        <p>{{ $check['course'] }} · {{ __('Group') }} {{ $check['group'] }} · {{ $check['career'] }} · {{ $check['term'] }}</p>
                        <p class="verification-reason">{{ __($check['reason']) }}</p>
                        @if ($check['catalog_reference'])
                            <p class="catalog-citation"><strong>{{ __('Applied catalog') }}:</strong> {{ $check['catalog_reference'] }}</p>
                        @else
                            <p class="catalog-citation"><strong>{{ __('Applied catalog') }}:</strong> {{ __('No published catalog') }}</p>
                        @endif
                        @if ($check['technical_note_status'])
                            <p class="catalog-citation">
                                <strong>{{ __('Technical note') }}:</strong>
                                {{ $noteLabels[$check['technical_note_status']] ?? $check['technical_note_status'] }}
                                · {{ __('Deadline') }} {{ $check['ratification_deadline'] }}
                            </p>
                            @if (in_array($check['technical_note_status'], ['ratified', 'rejected'], true))
                                <p class="catalog-citation">
                                    <strong>{{ __('Council resolution') }}:</strong>
                                    <span class="status-badge {{ $check['technical_note_status'] === 'ratified' ? 'custom' : 'danger' }}">
                                        {{ $noteLabels[$check['technical_note_status']] }}
                                    </span>
                                    <br><strong>{{ __('Registered by') }}:</strong> {{ $check['technical_note_resolved_by'] }} · {{ $check['technical_note_resolved_at'] }}
                                    <br><strong>{{ $check['technical_note_status'] === 'ratified' ? __('Council agreement or resolution') : __('Rejection reason') }}:</strong>
                                    {{ $check['technical_note_resolution_reason'] }}
                                </p>
                            @endif
                        @endif
                        <details class="credential-results">
                            <summary>{{ __('Result by academic credential') }}</summary>
                            @forelse ($check['credential_results'] as $credential)
                                <div class="credential-result-row">
                                    <span>
                                        <strong>{{ $credential['degree'] }} — {{ $credential['specialization'] }}</strong>
                                        <small>{{ $credential['institution'] }} · {{ $credential['year'] }}</small>
                                        <small>
                                            <strong>{{ __('Catalog reference for this credential') }}:</strong>
                                            {{ $credential['catalog_reference'] ?? __('No published catalog') }}
                                        </small>
                                    </span>
                                    <span class="result-pill result-{{ $credential['result'] }}">{{ $resultLabels[$credential['result']] ?? $credential['result'] }}</span>
                                </div>
                            @empty
                                <p class="muted-line">{{ __('This teacher has no registered credentials.') }}</p>
                            @endforelse
                        </details>
                    </div>
                    <div class="verification-side">
                        <span class="status-badge custom">{{ $statusLabels[$check['assignment_status']] ?? $check['assignment_status'] }}</span>
                        <small>{{ $check['checked_at'] }}</small>

                        @if ($check['result'] === 'not_eligible' && ! $check['technical_note_status'])
                            @can('createTechnicalNote', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class)
                                <button type="button" class="mini-action" wire:click="openTechnicalNoteModal({{ $check['assignment_id'] }})">{{ __('Start technical note') }}</button>
                            @endcan
                        @endif

                        @if ($check['result'] === 'no_catalog' && $check['assignment_status'] === 'pending_manual_approval')
                            @can('approveManual', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class)
                                <button type="button" class="mini-action" wire:click="decideManual({{ $check['assignment_id'] }}, true)">{{ __('Approve') }}</button>
                                <button type="button" class="mini-action danger" wire:click="decideManual({{ $check['assignment_id'] }}, false)">{{ __('Reject') }}</button>
                            @endcan
                        @endif

                        @if ($check['technical_note_path'])
                            <button type="button" class="mini-action" wire:click="downloadTechnicalNote('{{ $check['technical_note_path'] }}')">{{ __('Download signed technical criterion') }}</button>
                        @endif

                        @if ($check['result'] === 'technical_note' && $check['technical_note_status'] === 'pending')
                            @can('resolveTechnicalNote', \Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification::class)
                                <div class="resolution-actions">
                                    <small>{{ __('Register Council decision') }}</small>
                                    <div class="resolution-buttons">
                                        <button type="button" class="mini-action" wire:click="openResolutionModal({{ $check['assignment_id'] }}, 'ratified')">{{ __('Ratify') }}</button>
                                        <button type="button" class="mini-action danger" wire:click="openResolutionModal({{ $check['assignment_id'] }}, 'rejected')">{{ __('Reject') }}</button>
                                    </div>
                                </div>
                            @endcan
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-row">{{ __('No verifications have been recorded yet.') }}</div>
            @endforelse
        </div>
    </section>

    <x-ui.modal :show="$showModal" :title="__('Technical note by proven experience')">
        <div class="form-field">
            <label for="technicalDocument">{{ __('Signed technical criterion or institutional letter (PDF)') }}</label>
            <div class="file-picker">
                <input id="technicalDocument" class="file-input-hidden" type="file" wire:model="technicalNoteForm.document" accept="application/pdf,.pdf">
                <label for="technicalDocument" class="btn btn-secondary file-picker-button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>{{ __('Select file') }}</span>
                </label>
                @if ($technicalNoteForm->document)
                    <span class="file-selected-name" aria-live="polite">
                        <strong>{{ __('Selected file') }}:</strong>
                        {{ $technicalNoteForm->document->getClientOriginalName() }}
                    </span>
                @endif
            </div>
            <span class="field-help">{{ __('Accepted format: PDF. Maximum size: 10 MB.') }}</span>
            <span class="field-help" wire:loading wire:target="technicalNoteForm.document">{{ __('Uploading...') }}</span>
            @error('technicalNoteForm.document') <span class="form-error">{{ $message }}</span> @enderror
        </div>
        <div class="form-field">
            <label for="technicalDeadline">{{ __('Ratification deadline') }}</label>
            <input id="technicalDeadline" type="date" wire:model="technicalNoteForm.ratificationDeadline">
            <span class="field-help">{{ __('Enter the official institutional ratification deadline. After that date, a note without a registered resolution expires automatically.') }}</span>
            @error('technicalNoteForm.ratificationDeadline') <span class="form-error">{{ $message }}</span> @enderror
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="saveTechnicalNote">{{ __('Save provisional assignment') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal :show="$showResolutionModal" :title="$resolutionForm->outcome === 'ratified' ? __('Ratify Technical Note') : __('Reject Technical Note')">
        <div class="notice-box">
            {{ $resolutionForm->outcome === 'ratified'
                ? __('Record the official agreement or resolution that ratifies the Technical Note.')
                : __('Record why the Council rejected the Technical Note.') }}
        </div>
        <div class="form-field">
            <label>{{ __('Decision') }}</label>
            <div>
                <span class="status-badge {{ $resolutionForm->outcome === 'ratified' ? 'custom' : 'danger' }}">
                    {{ $resolutionForm->outcome === 'ratified' ? __('Ratified') : __('Rejected') }}
                </span>
            </div>
        </div>
        <div class="form-field">
            <label for="technicalResolutionReason">
                {{ $resolutionForm->outcome === 'ratified' ? __('Council agreement or resolution') : __('Rejection reason') }}
            </label>
            <textarea id="technicalResolutionReason" wire:model="resolutionForm.reason" rows="4" maxlength="500" placeholder="{{ $resolutionForm->outcome === 'ratified'
                ? __('Example: Council agreement CU-123-2026')
                : __('Explain the reason for the Council rejection') }}"></textarea>
            @error('resolutionForm.reason') <span class="form-error">{{ $message }}</span> @enderror
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeResolutionModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn {{ $resolutionForm->outcome === 'ratified' ? 'btn-primary' : 'btn-danger' }}" wire:click="saveResolution">
                {{ $resolutionForm->outcome === 'ratified' ? __('Confirm ratification') : __('Confirm rejection') }}
            </button>
        </x-slot:footer>
    </x-ui.modal>
</div>
