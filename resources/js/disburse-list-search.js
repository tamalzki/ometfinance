/** Shared partial-fetch search for Disbursements list pages. */
export function disburseListSearchMixin() {
    let debounceTimer = null;
    let abortController = null;

    return {
        searchLoading: false,

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

            const needle = (q !== undefined ? q : data.get('q') || '').trim();
            if (needle) {
                url.searchParams.set('q', needle);
            } else {
                url.searchParams.delete('q');
            }

            return url;
        },

        async fetchPartial(form, q) {
            const url = this.buildSearchUrl(form, q);
            const current = document.getElementById('disburse-list-fragment');
            if (!current) {
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
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                }
            } finally {
                const el = document.getElementById('disburse-list-fragment');
                if (el) {
                    el.classList.remove('opacity-50', 'pointer-events-none');
                }
                this.searchLoading = false;
            }
        },

        debouncedSearch(form) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => this.fetchPartial(form), 400);
        },

        immediateSearch(form) {
            clearTimeout(debounceTimer);
            this.fetchPartial(form);
        },

        onSearchInput(event) {
            this.debouncedSearch(event.target.form);
        },

        onSearchKeydown(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.immediateSearch(event.target.form);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.target.value = '';
                this.immediateSearch(event.target.form);
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
