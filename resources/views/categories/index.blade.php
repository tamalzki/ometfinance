<x-app-layout page-title="Project Categories">
<div
    x-data="{
        showForm: {{ $errors->any() ? 'true' : 'false' }},
        editId: null,
        f: { name: '{{ old('name', '') }}', parent_id: '{{ old('parent_id', '') }}' },
        formTitle: 'Add category',
        formAction: '{{ route('categories.store') }}',
        formMethod: 'POST',
        parentName: '',
        openAdd() {
            this.editId = null;
            this.f = { name: '', parent_id: '' };
            this.formTitle = 'Add category';
            this.formAction = '{{ route('categories.store') }}';
            this.formMethod = 'POST';
            this.parentName = '';
            this.showForm = true;
        },
        openAddSub(parent) {
            this.editId = null;
            this.f = { name: '', parent_id: String(parent.id) };
            this.formTitle = 'Add sub-category';
            this.formAction = '{{ route('categories.store') }}';
            this.formMethod = 'POST';
            this.parentName = parent.name;
            this.showForm = true;
        },
        openEdit(cat, parentName) {
            this.editId = cat.id;
            this.f = { name: cat.name, parent_id: cat.parent_id ? String(cat.parent_id) : '' };
            this.formTitle = parentName ? 'Edit sub-category' : 'Edit category';
            this.formAction = '/categories/' + cat.id;
            this.formMethod = 'PUT';
            this.parentName = parentName || '';
            this.showForm = true;
        }
    }"
    class="flex min-h-0 min-w-0 flex-1 flex-col gap-4"
>

    {{-- ── Alerts ──────────────────────────────────────────────────────────── --}}
    @if (session('success'))
    <div class="shrink-0 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
        {{ session('success') }}
    </div>
    @endif
    @if ($errors->any())
    <div class="shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex shrink-0 items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
        <div>
            <p class="text-[13px] font-bold tracking-tight text-omet-navy">Project Categories</p>
            <p class="mt-0.5 text-[11px] text-slate-400">
                Used to classify Daily Transactions / Voucher outflows and group the Cash Outflow report.
            </p>
        </div>
        <button type="button" @click="openAdd()"
                class="inline-flex items-center gap-1.5 rounded-lg bg-omet-blue px-3.5 py-2 text-[12.5px] font-semibold text-white shadow-sm transition hover:bg-omet-lightblue">
            <i data-lucide="plus" class="h-3.5 w-3.5"></i>
            Add category
        </button>
    </div>

    {{-- ── Category list ───────────────────────────────────────────────────── --}}
    @if ($categories->isEmpty())
        <div class="flex shrink-0 flex-col items-center justify-center rounded-xl border border-gray-200 bg-white py-16 text-center shadow-sm">
            <i data-lucide="tags" class="mb-3 h-8 w-8 text-gray-300"></i>
            <p class="text-sm text-gray-500">No categories yet. Click <strong>Add category</strong> to create one.</p>
        </div>
    @else
        <div class="min-h-0 min-w-0 flex-1 overflow-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <ul class="divide-y divide-gray-100">
                @foreach ($categories as $cat)
                    <li class="px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <i data-lucide="folder" class="h-4 w-4 shrink-0 text-omet-blue"></i>
                                <span class="truncate text-sm font-semibold text-gray-900">{{ $cat->name }}</span>
                                @if ($cat->children->isEmpty())
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">Selectable</span>
                                @else
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">Group</span>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <button type="button" title="Add sub-category"
                                        @click="openAddSub(@js(['id' => $cat->id, 'name' => $cat->name]))"
                                        class="inline-flex h-7 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 text-[11px] font-medium text-slate-500 shadow-sm transition hover:bg-slate-100">
                                    <i data-lucide="corner-down-right" class="pointer-events-none h-3.5 w-3.5"></i>
                                    Add sub
                                </button>
                                <button type="button" title="Edit category"
                                        @click="openEdit(@js(['id' => $cat->id, 'name' => $cat->name, 'parent_id' => $cat->parent_id]), null)"
                                        class="inline-flex h-7 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 text-[11px] font-medium text-slate-500 shadow-sm transition hover:bg-slate-100">
                                    <i data-lucide="pencil" class="pointer-events-none h-3.5 w-3.5"></i>
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('categories.destroy', $cat) }}"
                                      onsubmit="return confirm('Delete category &quot;{{ addslashes($cat->name) }}&quot;{{ $cat->children->isNotEmpty() ? ' and its '.$cat->children->count().' sub-categor'.($cat->children->count() === 1 ? 'y' : 'ies') : '' }}? Vouchers and outflows tagged with it will keep their record but lose the category tag.');"
                                      class="inline-flex shrink-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete category"
                                            class="inline-flex h-7 items-center gap-1 rounded-md border border-red-200 bg-red-50 px-2 text-[11px] font-medium text-red-600 shadow-sm transition hover:bg-red-100">
                                        <i data-lucide="trash-2" class="pointer-events-none h-3.5 w-3.5"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($cat->children->isNotEmpty())
                            <ul class="mt-2 space-y-1.5 border-l border-slate-100 pl-5">
                                @foreach ($cat->children as $child)
                                    <li class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <i data-lucide="corner-down-right" class="h-3.5 w-3.5 shrink-0 text-slate-300"></i>
                                            <span class="truncate text-sm text-gray-800">{{ $child->name }}</span>
                                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">Selectable</span>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1.5">
                                            <button type="button" title="Edit sub-category"
                                                    @click="openEdit(@js(['id' => $child->id, 'name' => $child->name, 'parent_id' => $child->parent_id]), @js($cat->name))"
                                                    class="inline-flex h-7 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 text-[11px] font-medium text-slate-500 shadow-sm transition hover:bg-slate-100">
                                                <i data-lucide="pencil" class="pointer-events-none h-3.5 w-3.5"></i>
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('categories.destroy', $child) }}"
                                                  onsubmit="return confirm('Delete sub-category &quot;{{ addslashes($child->name) }}&quot;? Vouchers and outflows tagged with it will keep their record but lose the category tag.');"
                                                  class="inline-flex shrink-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete sub-category"
                                                        class="inline-flex h-7 items-center gap-1 rounded-md border border-red-200 bg-red-50 px-2 text-[11px] font-medium text-red-600 shadow-sm transition hover:bg-red-100">
                                                    <i data-lucide="trash-2" class="pointer-events-none h-3.5 w-3.5"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         ADD / EDIT CATEGORY MODAL
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div
        x-show="showForm"
        x-cloak
        style="display:none"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @keydown.escape.window="showForm = false"
    >
        <div
            @click.outside="showForm = false"
            class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-xl"
        >
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-omet-navy" x-text="formTitle"></h3>
                <button type="button" @click="showForm = false" class="rounded-md p-1 text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form method="POST" :action="formAction" class="space-y-4 px-6 py-5">
                @csrf
                <template x-if="formMethod === 'PUT'">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <template x-if="f.parent_id">
                    <input type="hidden" name="parent_id" :value="f.parent_id">
                </template>

                <template x-if="parentName">
                    <p class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                        Sub-category of <span class="font-semibold text-slate-700" x-text="parentName"></span>
                    </p>
                </template>

                <div>
                    <x-label for="cat_name" :value="__('Category name *')" />
                    <x-input id="cat_name" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" type="text" name="name" x-model="f.name" required autofocus maxlength="100" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showForm = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-omet-blue px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-omet-lightblue">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>{{-- /x-data --}}
</x-app-layout>
