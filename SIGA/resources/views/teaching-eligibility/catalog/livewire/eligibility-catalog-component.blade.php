<div class="catalog-page" x-data="{ expandedCatalog: null }">
    <section class="card workflow-card catalog-filter-card">
        <div class="card-head">
            <div>
                <span class="card-title">{{ __('Filter courses by career') }}</span>
                <p class="card-subtitle">{{ __('Select a career or leave the filter on all careers to review the complete academic offering and its catalog status.') }}</p>
            </div>
        </div>
        <div class="form-field">
            <label for="catalogCareerFilter">{{ __('Career') }}</label>
            <select id="catalogCareerFilter" wire:model.live="selectedCareerId">
                <option value="0">{{ __('All careers') }}</option>
                @foreach ($careers as $career)
                    <option value="{{ $career['id'] }}">{{ $career['label'] }}</option>
                @endforeach
            </select>
        </div>
    </section>

    <x-ui.data-table
        :headers="[
            ['key' => 'course', 'label' => __('Course'), 'sortable' => true],
            ['key' => 'career', 'label' => __('Career'), 'sortable' => true],
            ['key' => 'catalog_status', 'label' => __('Catalog status'), 'sortable' => true],
            ['key' => 'version', 'label' => __('Version'), 'sortable' => true],
            ['key' => 'agreement', 'label' => __('Council agreement and Gazette'), 'sortable' => false],
            ['key' => 'valid_from', 'label' => __('Validity'), 'sortable' => true],
        ]"
        :mode="$tableMode"
        :rows="$rows"
        :searchable="['career', 'course', 'catalog_status', 'agreement', 'gazette']"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        :table-version="$selectedCareerId"
        table-cols="2fr 1.7fr 1.25fr .55fr 1.8fr 1.25fr 1.1fr"
        :can-create="Auth::user()->can('create', \Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog::class)"
        :can-search="Auth::user()->can('search', \Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog::class)"
        :title="__('Courses and catalog versions')">

        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <div>
                    <strong x-text="row.course"></strong>
                    <div class="muted-line" x-show="row.has_catalog" x-text="row.specializations_count + ' {{ __('eligible specializations') }}'"></div>
                </div>
                <span class="catalog-career" x-text="row.career"></span>
                <span class="table-badge-cell"><span class="status-badge" :class="row.has_catalog ? 'system' : 'warning'" x-text="row.catalog_status"></span></span>
                <span class="table-badge-cell">
                    <span x-show="row.has_catalog" class="status-badge custom" x-text="'v' + row.version"></span>
                    <span x-show="!row.has_catalog" class="muted-line">—</span>
                </span>
                <div class="catalog-proof">
                    <template x-if="row.has_catalog"><div><span><strong>{{ __('Agreement') }}:</strong> <span x-text="row.agreement"></span></span><span><strong>{{ __('Gazette') }}:</strong> <span x-text="row.gazette"></span></span></div></template>
                    <span x-show="!row.has_catalog" class="muted-line">—</span>
                </div>
                <div class="catalog-validity">
                    <template x-if="row.has_catalog"><div><span><strong>{{ __('From') }}:</strong> <span x-text="row.valid_from"></span></span><span><strong>{{ __('Until') }}:</strong> <span x-text="row.valid_until"></span></span></div></template>
                    <span x-show="!row.has_catalog" class="muted-line">—</span>
                </div>
                <div class="catalog-actions">
                    <button x-show="row.has_catalog" type="button" class="mini-action" @click="expandedCatalog = expandedCatalog === row.id ? null : row.id">
                        <span x-text="expandedCatalog === row.id ? '{{ __('Hide specializations') }}' : '{{ __('View specializations') }}'"></span>
                    </button>
                    <span x-show="row.has_catalog" class="status-badge system">{{ __('Historical version') }}</span>
                </div>
                <div class="catalog-specializations" x-show="row.has_catalog && expandedCatalog === row.id" x-collapse>
                    <strong>{{ __('Eligible specializations in this version') }}</strong>
                    <div class="credential-summary">
                        <template x-for="specialization in row.specializations" :key="specialization">
                            <span class="credential-chip" x-text="specialization"></span>
                        </template>
                    </div>
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">
            {{ __('No records found') }}
        </div>
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="__('New catalog version')">
        <div class="form-grid two-columns">
            <div class="form-field">
                <label for="catalogCareer">{{ __('Career') }}</label>
                <select id="catalogCareer" wire:model.live="modalCareerId">
                    <option value="0">{{ __('Select a career') }}</option>
                    @foreach ($careers as $career)
                        <option value="{{ $career['id'] }}">{{ $career['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="catalogCourse">{{ __('Course') }}</label>
                <select id="catalogCourse" wire:model.live="form.courseId" @disabled($modalCareerId === 0)>
                    <option value="0">{{ __('Select an option') }}</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course['id'] }}">{{ $course['label'] }}</option>
                    @endforeach
                </select>
                @error('form.courseId') <span class="form-error">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="form-grid two-columns">
            <div class="form-field">
                <label for="catalogAgreement">{{ __('University Council agreement') }}</label>
                <input id="catalogAgreement" type="text" wire:model="form.agreement">
                <span class="field-help">{{ __('Use the official agreement or resolution identifier supplied by the University Council.') }}</span>
                @error('form.agreement') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field">
                <label for="catalogGazette">{{ __('Gazette number') }}</label>
                <input id="catalogGazette" type="text" wire:model="form.gazetteNumber">
                <span class="field-help">{{ __('Use the number of the official Gazette publication associated with that agreement.') }}</span>
                @error('form.gazetteNumber') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field">
                <label for="catalogValidFrom">{{ __('Valid from') }}</label>
                <input id="catalogValidFrom" type="date" wire:model="form.validFrom">
                @error('form.validFrom') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-field">
                <label for="catalogValidUntil">{{ __('Valid until') }}</label>
                <input id="catalogValidUntil" type="date" wire:model="form.validUntil">
                @error('form.validUntil') <span class="form-error">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="form-field">
            <span class="form-label">{{ __('Eligible degrees or specializations') }}</span>
            <div class="specialization-options" role="group" aria-label="{{ __('Eligible degrees or specializations') }}">
                @foreach ($specializationOptions as $specialization)
                    <label class="checkbox-field">
                        <input type="checkbox" wire:model="form.specializations" value="{{ $specialization }}">
                        <span>{{ $specialization }}</span>
                    </label>
                @endforeach
                @if ($form->courseId === 0)
                    <span class="muted-line">{{ __('Select a course to load only the specializations assigned to it in the official Manual.') }}</span>
                @endif
            </div>
            <span class="field-help">{{ __('Select only the specializations listed in the official manual. Each save creates a new historical version.') }}</span>
            @error('form.specializations') <span class="form-error">{{ $message }}</span> @enderror
            @error('form.specializations.*') <span class="form-error">{{ $message }}</span> @enderror
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Create version') }}</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
