@props(['selector', 'submitEvent' => 'form:submitting'])

{{--
    Warns before leaving a form with unsaved changes — e.g. clicking a
    sidebar link while mid-edit on Add/Edit Voucher. Dirty-checks via a
    FormData snapshot (not input events) so it also catches changes made
    through custom Alpine dropdowns/comboboxes that set hidden-input values
    without firing native input events.
--}}
<div x-data="unsavedChangesGuard(@js($selector), @js($submitEvent))" x-init="init()">
    <div x-show="showConfirm" x-cloak
         class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40 p-3"
         @keydown.escape.window="stay()">
        <div @click.outside="stay()" class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-50">
                    <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600"></i>
                </span>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Leave without saving?</h3>
                    <p class="mt-1 text-[12.5px] leading-relaxed text-slate-500">
                        You have unsaved changes on this page. If you leave now, this data will not be saved.
                    </p>
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="stay()"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Stay on this page
                </button>
                <button type="button" @click="leaveAnyway()"
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">
                    Leave without saving
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('unsavedChangesGuard', (selector, submitEvent) => ({
        showConfirm: false,
        pendingHref: null,
        submitting: false,
        initialSnapshot: '',
        form: null,

        init() {
            this.form = document.querySelector(selector);
            if (! this.form) return;

            // Defer the baseline snapshot until Alpine has finished applying
            // any :value-bound defaults (e.g. locked source, old() values).
            this.$nextTick(() => { this.initialSnapshot = this.snapshot(); });

            this.form.addEventListener(submitEvent, () => { this.submitting = true; });

            window.addEventListener('beforeunload', (e) => {
                if (! this.submitting && this.isDirty()) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Capture phase so we intercept before any other click handler
            // (e.g. the sidebar's mobile-close toggle) navigates away.
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[href]');
                if (! link || link.target === '_blank' || link.hasAttribute('download')) return;
                const href = link.getAttribute('href');
                if (! href || href.startsWith('#') || href.startsWith('javascript:')) return;
                if (! this.isDirty()) return;
                e.preventDefault();
                this.pendingHref = link.href;
                this.showConfirm = true;
            }, true);
        },

        snapshot() {
            if (! this.form) return '';
            const parts = [];
            new FormData(this.form).forEach((value, key) => {
                parts.push(key + '=' + (value instanceof File ? value.name + ':' + value.size : value));
            });
            return parts.join('&');
        },

        isDirty() {
            return this.snapshot() !== this.initialSnapshot;
        },

        stay() {
            this.showConfirm = false;
            this.pendingHref = null;
        },

        leaveAnyway() {
            this.submitting = true;
            this.showConfirm = false;
            if (this.pendingHref) window.location.href = this.pendingHref;
        },
    }));
});
</script>
