<div x-data="{
    confirmDelete: { open: false, step: 'confirm', id: null },
    askDelete(id) { this.confirmDelete = { open: true, step: 'confirm', id }; },
    runDelete() {
        $wire.delete(this.confirmDelete.id)
            .then((deleted) => {
                if (deleted) this.confirmDelete.step = 'success';
                else this.confirmDelete.open = false;
            })
            .catch(() => { this.confirmDelete.open = false; });
    },
    closeDeleteModal() { this.confirmDelete.open = false; },
}">
    @if ($deleteError !== '')
        <div class="notice-box notice-warning" role="alert">{{ $deleteError }}</div>
    @endif
    <x-ui.data-table
        :headers="[
            ['key' => 'nationalId', 'label' => __('National ID'), 'sortable' => true],
            ['key' => 'fullName', 'label' => __('Full name'), 'sortable' => true],
            ['key' => 'credentialsCount', 'label' => __('Credentials'), 'sortable' => true],
            ['key' => 'active', 'label' => __('Status'), 'sortable' => true],
        ]"
        :mode="$tableMode"
        :rows="$rows"
        :searchable="['nationalId', 'nationalIdDigits', 'fullName']"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="1fr 1.7fr 2.4fr .8fr 1.4fr"
        :can-create="Auth::user()->can('create', \Src\TeachingEligibility\Teacher\Domain\Entities\Teacher::class)"
        :can-search="Auth::user()->can('search', \Src\TeachingEligibility\Teacher\Domain\Entities\Teacher::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\TeachingEligibility\Teacher\Domain\Entities\Teacher::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\TeachingEligibility\Teacher\Domain\Entities\Teacher::class)"
        :title="__('Teachers and academic credentials')">

        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span x-text="row.nationalId"></span>
                <span class="name-text" x-text="row.fullName"></span>
                <div class="credential-summary">
                    <span x-show="row.credentials.length === 0">{{ __('No credentials registered') }}</span>
                    <template x-for="credential in row.credentials" :key="credential.id">
                        <span class="credential-chip" x-text="credential.degreeLevel + ': ' + credential.specialization"></span>
                    </template>
                </div>
                <span class="table-badge-cell">
                    <span class="status-badge custom" x-show="row.active && row.profileComplete">{{ __('Active') }}</span>
                    <span class="status-badge warning" x-show="row.active && !row.profileComplete">{{ __('Incomplete profile') }}</span>
                    <span class="status-badge system" x-show="!row.active">{{ __('Inactive') }}</span>
                </span>
                <div class="actions-cell eligibility-actions">
                    @if (Auth::user()->can('update', \Src\TeachingEligibility\Teacher\Domain\Entities\Teacher::class))
                        <button type="button" class="mini-action" @click="$wire.openCredentialModal(row.id, null)" title="{{ __('Add credential') }}">+ {{ __('Credential') }}</button>
                        <button type="button" class="mini-action" @click="$wire.openEditModal(row.id)">{{ __('Edit') }}</button>
                    @endif
                    @if (Auth::user()->can('delete', \Src\TeachingEligibility\Teacher\Domain\Entities\Teacher::class))
                        <button type="button" class="mini-action danger" @click="askDelete(row.id)">{{ __('Delete') }}</button>
                    @endif
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New teacher') : __('Edit teacher')">
        <div class="form-grid two-columns">
            <div class="form-field">
                <label for="teacherNationalId">{{ __('National ID') }}</label>
                <input id="teacherNationalId" type="text" wire:model.live="form.nationalId" inputmode="numeric" maxlength="9" pattern="[1-9][0-9]{8}" placeholder="123456789" autocomplete="off" x-on:input="$event.target.value = $event.target.value.replace(/\D/g, '').slice(0, 9)">
                <span class="field-help">{{ __('Enter 9 digits without hyphens. The system adds the hyphens when saving.') }}</span>
                @error('form.nationalId') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field">
                <label for="teacherFirstName">{{ __('First name') }}</label>
                <input id="teacherFirstName" type="text" wire:model="form.firstName" maxlength="80" autocomplete="given-name">
                @error('form.firstName') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field">
                <label for="teacherLastName">{{ __('First surname') }}</label>
                <input id="teacherLastName" type="text" wire:model="form.lastName" maxlength="80" autocomplete="family-name">
                @error('form.lastName') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field">
                <label for="teacherSecondLastName">{{ __('Second surname') }}</label>
                <input id="teacherSecondLastName" type="text" wire:model="form.secondLastName" maxlength="80">
                @error('form.secondLastName') <span class="form-error">{{ $message }}</span> @enderror
            </div>
        </div>
        <label class="checkbox-field">
            <input type="checkbox" wire:model="form.active">
            <span>{{ __('Active teacher') }}</span>
        </label>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal :show="$showCredentialModal" :title="$editingCredentialId === null ? __('New credential') : __('Edit credential')">
        <div class="form-grid two-columns">
            <div class="form-field">
                <label for="degreeLevel">{{ __('Academic degree') }}</label>
                <select id="degreeLevel" wire:model="credentialForm.degreeLevel">
                    <option value="">{{ __('Select an option') }}</option>
                    @foreach (['Bachillerato', 'Licenciatura', 'Maestría', 'Doctorado'] as $degree)
                        <option value="{{ $degree }}">{{ $degree }}</option>
                    @endforeach
                </select>
                @error('credentialForm.degreeLevel') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field">
                <label for="graduationYear">{{ __('Graduation year') }}</label>
                <input id="graduationYear" type="number" wire:model="credentialForm.graduationYear" min="1950" max="{{ now()->year }}">
                @error('credentialForm.graduationYear') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field full-column">
                <label for="institution">{{ __('Institution') }}</label>
                <input id="institution" type="text" wire:model="credentialForm.institution" list="institutionOptions" maxlength="180">
                <datalist id="institutionOptions">
                    @foreach (['Universidad Técnica Nacional', 'Universidad de Costa Rica', 'Instituto Tecnológico de Costa Rica', 'Universidad Nacional', 'Universidad Estatal a Distancia'] as $institution)
                        <option value="{{ $institution }}"></option>
                    @endforeach
                </datalist>
                @error('credentialForm.institution') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field full-column">
                <label for="specialization">{{ __('Degree specialization') }}</label>
                <select id="specialization" wire:model="credentialForm.specialization">
                    <option value="">{{ __('Select an option') }}</option>
                    @foreach ($specializations as $specialization)
                        <option value="{{ $specialization }}">{{ $specialization }}</option>
                    @endforeach
                </select>
                <span class="field-help">{{ __('The list uses the specializations configured for eligibility evaluation.') }}</span>
                @error('credentialForm.specialization') <span class="form-error">{{ $message }}</span> @enderror
            </div>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="saveCredential">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-delete-modal :success-text="__('The teacher has been deleted.')" />
</div>
