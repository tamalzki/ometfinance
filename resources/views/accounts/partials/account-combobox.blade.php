{{--
  Searchable account picker for transfer forms.
  Expects: $fieldName, $accountsForPicker (collection of id, label, search), $defaultId
--}}
@php
    $firstPicker = $accountsForPicker->first();
    $defaultId = $defaultId ?? ($firstPicker['id'] ?? null);
@endphp
<div
    class="relative"
    :class="{ 'z-[100]': open }"
    x-data="{
        all: @js($accountsForPicker->values()),
        selectedId: @json($defaultId),
        open: false,
        q: '',
        get displayLabel() {
            const a = this.all.find(x => Number(x.id) === Number(this.selectedId));
            return a ? a.label : 'Select account…';
        },
        filtered() {
            const s = (this.q || '').toLowerCase().trim();
            if (!s) return this.all;
            return this.all.filter(a => (a.search || '').includes(s));
        },
        pick(a) {
            this.selectedId = a.id;
            this.open = false;
            this.q = '';
        },
        toggleOpen() {
            if (this.open) {
                this.open = false;
            } else {
                window.dispatchEvent(new CustomEvent('close-account-combos'));
                this.open = true;
                this.$nextTick(() => this.$refs.qf?.focus());
            }
        },
    }"
    @close-account-combos.window="open = false"
>
    <input type="hidden" name="{{ $fieldName }}" :value="selectedId">
    <button
        type="button"
        @click="toggleOpen()"
        class="{{ $buttonClass ?? 'flex w-full items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-left text-[13px] text-gray-800 outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10' }}"
    >
        <span class="truncate" x-text="displayLabel"></span>
        <i data-lucide="chevrons-up-down" class="h-4 w-4 shrink-0 text-gray-400"></i>
    </button>
    <div
        x-show="open"
        x-transition
        @click.outside="open = false"
        style="display: none;"
        class="absolute left-0 right-0 z-[110] mt-1 max-h-56 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
    >
        <div class="sticky top-0 border-b border-slate-100 bg-white p-2">
            <input
                type="text"
                x-ref="qf"
                x-model="q"
                placeholder="Search accounts…"
                class="w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 text-[12px] text-gray-800 outline-none focus:border-[#185FA5] focus:bg-white focus:ring-1 focus:ring-[#185FA5]/20"
                @keydown.escape.prevent="open = false"
            >
        </div>
        <ul class="max-h-44 overflow-y-auto py-1">
            <template x-for="acc in filtered()" :key="acc.id">
                <li>
                    <button
                        type="button"
                        @click="pick(acc)"
                        class="flex w-full px-3 py-2 text-left text-[12px] text-gray-800 hover:bg-slate-50"
                        :class="Number(acc.id) === Number(selectedId) ? 'bg-blue-50 font-medium text-[#185FA5]' : ''"
                    >
                        <span class="truncate" x-text="acc.label"></span>
                    </button>
                </li>
            </template>
        </ul>
        <p x-show="filtered().length === 0" class="px-3 py-4 text-center text-[11px] text-gray-400" style="display:none;">
            No matches
        </p>
    </div>
</div>
