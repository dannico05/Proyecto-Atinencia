<div x-data="{
    confirmDelete: { open: false, step: 'confirm', id: null },
    askDelete(id) {
        this.confirmDelete = { open: true, step: 'confirm', id };
    },
    runDelete() {
        $wire.delete(this.confirmDelete.id)
            .then(() => { this.confirmDelete.step = 'success'; })
            .catch(() => { this.confirmDelete.open = false; });
    },
    closeDeleteModal() {
        this.confirmDelete.open = false;
    },
}">
    <x-ui.data-table
        :headers="[
                ['key' => 'module', 'label' => __('Module'), 'sortable' => true],
                ['key' => 'action', 'label' => __('Action'), 'sortable' => true],
                ['key' => 'name', 'label' => __('Name'), 'sortable' => false],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['name', 'action', 'module', 'moduleLabel', 'actionLabel', 'label']"
        :paginator="$permissions ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="1fr 1.3fr 1.6fr 1fr"
        :can-create="Auth::user()->can('create', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class)"
        :can-search="Auth::user()->can('search', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class)"
        :title="__('Permissions management')">

        @if ($tableMode === 'client')
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span class="table-badge-cell"><span class="module-badge" x-text="row.moduleLabel"></span></span>
                <span x-text="row.actionLabel"></span>
                <span style="color:var(--textMuted)" x-text="row.label"></span>
                <div class="actions-cell">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('permissions.edit')"
                        :can-delete="Auth::user()->hasPermissionTo('permissions.delete')"
                        edit-action="$wire.openEditModal(row.id)"
                        delete-id="row.id" />
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        @forelse ($permissions as $permission)
        <div class="data-row" role="row">
            <span class="table-badge-cell"><span class="module-badge">{{ \Src\IdentityAccess\Permission\Presentation\Support\PermissionLabelFormatter::module($permission->module()) }}</span></span>
            <span>{{ \Src\IdentityAccess\Permission\Presentation\Support\PermissionLabelFormatter::action($permission->action()) }}</span>
            <span style="color:var(--textMuted)">{{ \Src\IdentityAccess\Permission\Presentation\Support\PermissionLabelFormatter::forHumans($permission->module(), $permission->action()) }}</span>
            <div class="actions-cell">
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', $permission)"
                    :can-delete="Auth::user()->can('delete', $permission)"
                    edit-action="$wire.openEditModal({{ $permission->id() }})"
                    delete-id="{{ $permission->id() }}" />
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New permission') : __('Edit permission')">
        <div class="form-field">
            <label for="permModule">{{ __('Module') }}</label>
            <select id="permModule" wire:model="form.module" class="{{ $errors->has('form.module') ? 'has-error' : '' }}">
                <option value="">{{ __('Select an option') }}</option>
                @foreach ($moduleOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('form.module') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="permAction">{{ __('Action') }}</label>
            <select id="permAction" wire:model="form.action" class="{{ $errors->has('form.action') ? 'has-error' : '' }}">
                <option value="">{{ __('Select an option') }}</option>
                @foreach ($actionOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('form.action') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-delete-modal :success-text="__('The permission has been deleted.')" />
</div>
