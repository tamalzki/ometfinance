@props([
    'name',
    'id' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => '— select —',
    'searchPlaceholder' => 'Search…',
    'emptyText' => 'No matches',
    'clearable' => false,
])

@php
    $id = $id ?? $name;
    $current = old($name, $selected);
@endphp

<div
    x-data="{
        open: false,
        query: '',
        value: @js($current !== null ? (string) $current : ''),
        options: @js($options),
        get groups() {
            const q = this.query.trim().toLowerCase();
            const order = [];
            const map = {};
            this.options.forEach(o => {
                if (q && ! o.search.includes(q)) return;
                const key = o.group ?? '';
                if (! map[key]) { map[key] = { label: o.group ?? '', items: [] }; order.push(map[key]); }
                map[key].items.push(o);
            });
            return order;
        },
        get hasResults() {
            return this.groups.some(g => g.items.length > 0);
        },
        get selectedLabel() {
            const opt = this.options.find(o => String(o.value) === String(this.value));
            return opt ? opt.label : '';
        },
        pick(opt) {
            this.value = String(opt.value);
            this.open = false;
        },
        clear() {
            this.value = '';
            this.open = false;
        },
        toggle() {
            this.open = ! this.open;
            if (this.open) {
                this.query = '';
                this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
            }
        }
    }"
    @click.outside="open = false"
    @keydown.escape="open = false"
    class="relative"
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    <button type="button" id="{{ $id }}" @click="toggle()"
        role="combobox" aria-haspopup="listbox" :aria-expanded="open" aria-controls="{{ $id }}_listbox"
        class="mt-1 flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:border-omet-blue focus:outline-none focus:ring-1 focus:ring-omet-blue">
        <span class="min-w-0 flex-1 truncate" :class="value ? 'text-gray-900' : 'text-gray-400'" x-text="value ? selectedLabel : @js($placeholder)"></span>
        <span class="flex shrink-0 items-center gap-1">
            @if ($clearable)
            <span role="button" tabindex="0" x-show="value" x-cloak
                @click.stop="clear()" @keydown.enter.stop.prevent="clear()" @keydown.space.stop.prevent="clear()"
                class="cursor-pointer rounded p-0.5 text-gray-400 hover:text-gray-600" aria-label="Clear selection">
                <i data-lucide="x" class="h-3.5 w-3.5"></i>
            </span>
            @endif
            <span class="transition-transform duration-150" :class="open ? 'rotate-180' : ''">
                <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
            </span>
        </span>
    </button>

    <div x-show="open" x-cloak @click.stop
         class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
        <div class="border-b border-gray-100 p-2">
            <input x-ref="search" x-model="query" type="text" autocomplete="off"
                placeholder="{{ $searchPlaceholder }}" aria-autocomplete="list"
                class="block w-full rounded-md border-gray-200 text-sm focus:border-omet-blue focus:ring-omet-blue"
                @keydown.enter.prevent="hasResults && pick(groups.find(g => g.items.length).items[0])">
        </div>
        <ul role="listbox" id="{{ $id }}_listbox" class="max-h-56 overflow-y-auto py-1 text-sm">
            <template x-for="group in groups" :key="group.label || '_'">
                <div class="contents">
                    <p x-show="group.label" x-text="group.label" class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wide text-gray-400"></p>
                    <template x-for="opt in group.items" :key="opt.value">
                        <div role="option" :aria-selected="String(value) === String(opt.value)"
                            @click="pick(opt)"
                            :class="String(value) === String(opt.value) ? 'bg-omet-blue/10 font-medium text-omet-navy' : 'text-gray-700 hover:bg-gray-50'"
                            class="cursor-pointer truncate px-3 py-2" x-text="opt.label"></div>
                    </template>
                </div>
            </template>
            <li x-show="!hasResults" class="px-3 py-2 text-gray-400">{{ $emptyText }}</li>
        </ul>
    </div>
</div>
