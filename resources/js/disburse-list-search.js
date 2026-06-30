/** Shared partial-fetch search for Disbursements list pages. */
export function disburseListSearchMixin() {
    let debounceTimer = null;
    let abortController = null;

    return {
        searchLoading: false,

        searchQuery(form, q) {
            if (q !== undefined && q !== null) {
                return String(q).trim();
            }

            const input = form?.querySelector('[name="q"]');

            return (input?.value ?? new FormData(form).get('q') ?? '').trim();
        },

        buildSearchUrl(form, q) {
            const url = new URL(form.action || window.location.href, window.location.origin);
            const data = new FormData(form);

            url.search = '';
            for (const [key, value] of data.entries()) {
                if (key === 'page') {
                    continue;
                }
                if (value !== '') {
                    url.searchParams.set(key, value);
                }
            }

            const needle = this.searchQuery(form, q);
            if (needle) {
                url.searchParams.set('q', needle);
            } else {
                url.searchParams.delete('q');
            }

            return url;
        },

        submitSearchForm(form, q) {
            const input = form?.querySelector('[name="q"]');
            if (input && q !== undefined) {
                input.value = q;
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        },

        async fetchPartial(form, q) {
            const url = this.buildSearchUrl(form, q);
            const current = document.getElementById('disburse-list-fragment');

            if (!current) {
                this.submitSearchForm(form, this.searchQuery(form, q));
                return;
            }

            if (abortController) {
                abortController.abort();
            }
            abortController = new AbortController();

            this.searchLoading = true;
            current.classList.add('opacity-50', 'pointer-events-none');

            try {
                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        'X-Disburse-Partial': '1',
                        Accept: 'text/html',
                    },
                    signal: abortController.signal,
                });

                if (!response.ok) {
                    throw new Error('Search request failed');
                }

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const next = doc.getElementById('disburse-list-fragment');

                if (next) {
                    current.replaceWith(next);
                    history.replaceState(null, '', url.toString());
                    this.syncResultMeta(next);
                    return;
                }

                this.submitSearchForm(form, this.searchQuery(form, q));
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.submitSearchForm(form, this.searchQuery(form, q));
                }
            } finally {
                const el = document.getElementById('disburse-list-fragment');
                if (el) {
                    el.classList.remove('opacity-50', 'pointer-events-none');
                }
                this.searchLoading = false;
            }
        },

        debouncedSearch(form, q) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => this.fetchPartial(form, q), 400);
        },

        immediateSearch(form, q) {
            clearTimeout(debounceTimer);
            this.fetchPartial(form, q);
        },

        onSearchInput(event) {
            this.debouncedSearch(event.target.form, event.target.value);
        },

        onSearchKeydown(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.immediateSearch(event.target.form, event.target.value);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.target.value = '';
                this.immediateSearch(event.target.form, '');
            }
        },

        syncResultMeta(fragment) {
            const count = fragment.dataset.resultCount;
            const mode = fragment.dataset.resultMode;

            document.querySelectorAll('[data-disburse-result-count]').forEach((el) => {
                if (count !== undefined) {
                    el.textContent = count;
                }
            });

            document.querySelectorAll('[data-disburse-result-mode]').forEach((el) => {
                if (mode) {
                    el.textContent = mode;
                }
            });

            document.querySelectorAll('[data-disburse-search-clear]').forEach((el) => {
                const url = new URL(window.location.href);
                el.classList.toggle('hidden', ! url.searchParams.get('q'));
            });
        },
    };
}

/** Full-page GET fallback when app.js on the page predates the partial-fetch mixin. */
export function disburseListSearchFallback() {
    let debounceTimer = null;

    return {
        onSearchInput(event) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => event.target.form.requestSubmit(), 400);
        },
        onSearchKeydown(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                clearTimeout(debounceTimer);
                event.target.form.requestSubmit();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.target.value = '';
                clearTimeout(debounceTimer);
                event.target.form.requestSubmit();
            }
        },
    };
}
