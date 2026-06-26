<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Payee;
use App\Models\Project;
use App\Models\ProjectCollection;
use App\Models\ProjectExpense;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Models\VoucherEntry;
use App\Models\VoucherPayment;
use App\Models\VoucherRequest;
use App\Services\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoucherController extends Controller
{
    /** Aging bucket labels, shared by the index + payables views. */
    public const AGING_LABELS = [
        'current'  => 'Current',
        'd1_30'    => '1–30 days overdue',
        'd31_60'   => '31–60 days overdue',
        'd60_plus' => '60+ days overdue',
        'pdc'      => 'Covered by PDC',
        'no_term'  => 'No due date',
    ];

    /* ── Voucher register ──────────────────────────────────────────────── */

    public function index(Request $request): View
    {
        $status    = $request->query('status');
        $type      = $request->query('type');
        $projectId = $request->query('project_id');
        $source    = $request->query('source');
        $dateFrom  = $request->query('date_from');
        $dateTo    = $request->query('date_to');

        $query = Voucher::with(['project', 'entries.project', 'entries.category.parent', 'sourceBankAccount.entity', 'payments.bankAccount', 'attachments', 'approvalRequests'])
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        // Accounting Staff only ever see what they themselves submitted.
        if (auth()->user()->isAccounting()) {
            $query->whereHas('approvalRequests', fn ($q) => $q
                ->where('type', VoucherRequest::TYPE_CREATE)
                ->where('requested_by', auth()->id()));
        }

        if ($status && array_key_exists($status, Voucher::STATUSES)) {
            $query->where('status', $status);
        }
        if ($type && array_key_exists($type, Voucher::TYPES)) {
            $query->where('transaction_type', $type);
        }
        if ($source && array_key_exists($source, Voucher::SOURCES)) {
            $query->where('source', $source);
        }
        if ($dateFrom) {
            $query->whereDate('voucher_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('voucher_date', '<=', $dateTo);
        }

        $activeProject = $projectId ? Project::find($projectId) : null;
        if ($activeProject) {
            $query->where('project_id', $activeProject->id);
        }

        $vouchers = $query->get();

        $allQuery = Voucher::with('payments')->where('approval_status', 'approved');
        if (auth()->user()->isAccounting()) {
            $allQuery->whereHas('approvalRequests', fn ($q) => $q
                ->where('type', VoucherRequest::TYPE_CREATE)
                ->where('requested_by', auth()->id()));
        }
        $all = $allQuery->get();

        // Encrypted columns — every money aggregate is computed in PHP.
        $summary = [
            'count'       => $vouchers->count(),
            'payable'     => (float) $all->sum(fn ($v) => (float) $v->amount_payable),
            'paid'        => (float) $all->sum(fn ($v) => $v->amountPaid()),
            'outstanding' => (float) $all->filter->isOpen()->sum(fn ($v) => $v->balanceDue()),
            'overdue'     => $all->filter->isOverdue()->count(),
        ];

        return view('vouchers.index', [
            'vouchers'   => $vouchers,
            'summary'    => $summary,
            'projects'   => $this->projectsForPicker(),
            'accounts'   => $this->accountsForPicker(),
            'payees'     => $this->payeesForPicker(),
            'categoriesForPicker' => \App\Models\ProjectCategory::selectOptions(),
            'statuses'   => Voucher::STATUSES,
            'types'      => Voucher::TYPES,
            'modes'      => Voucher::MODES,
            'sources'    => Voucher::SOURCES,
            'activeStatus'  => $status,
            'activeType'    => $type,
            'activeSource'  => $source,
            'activeDateFrom' => $dateFrom,
            'activeDateTo'   => $dateTo,
            'activeProject' => $activeProject,
        ]);
    }

    /* ── Create form (dedicated page) ─────────────────────────────────── */

    public function create(Request $request): View
    {
        $projectId     = $request->query('project_id');
        $activeProject = $projectId ? Project::find($projectId) : null;

        $lockedSource  = auth()->user()->lockedSource();
        $defaultSource = $lockedSource ?? 'mindanao';

        return view('vouchers.create', [
            'projects'            => $this->projectsForPicker(),
            'accounts'            => $this->accountsForPicker(),
            'payees'              => $this->payeesForPicker(),
            'categoriesForPicker' => \App\Models\ProjectCategory::selectOptions(),
            'types'               => Voucher::TYPES,
            'modes'               => Voucher::MODES,
            'sources'             => Voucher::SOURCES,
            'sourceDocuments'     => Voucher::SOURCE_DOCUMENTS,
            'sourceDocumentIcons' => Voucher::SOURCE_DOCUMENT_ICONS,
            'sourceDocumentNumberLabels' => Voucher::SOURCE_DOCUMENT_NUMBER_LABELS,
            'activeProject'       => $activeProject,
            'defaultSource'       => $defaultSource,
            'lockedSource'        => $lockedSource,
            'pendingAttachments'  => $this->resolvePendingAttachments(),
        ]);
    }

    /* ── Edit form (dedicated page) ─────────────────────────────────── */

    public function edit(Voucher $voucher): View
    {
        $voucher->load(['entries.project', 'entries.category', 'project', 'sourceBankAccount']);

        $lockedSource  = auth()->user()->lockedSource();
        $defaultSource = $lockedSource ?? 'mindanao';

        return view('vouchers.edit', [
            'voucher'             => $voucher,
            'projects'            => $this->projectsForPicker(),
            'accounts'            => $this->accountsForPicker(),
            'payees'              => $this->payeesForPicker(),
            'categoriesForPicker' => \App\Models\ProjectCategory::selectOptions(),
            'types'               => Voucher::TYPES,
            'modes'               => Voucher::MODES,
            'sources'             => Voucher::SOURCES,
            'sourceDocuments'     => Voucher::SOURCE_DOCUMENTS,
            'sourceDocumentIcons' => Voucher::SOURCE_DOCUMENT_ICONS,
            'sourceDocumentNumberLabels' => Voucher::SOURCE_DOCUMENT_NUMBER_LABELS,
            'defaultSource'       => $defaultSource,
            'lockedSource'        => $lockedSource,
            'pendingAttachments'  => $this->resolvePendingAttachments(),
        ]);
    }

    /* ── Voucher detail / history view ─────────────────────────────────── */

    public function show(Voucher $voucher): View
    {
        $voucher->load(['project', 'sourceBankAccount.entity', 'category.parent', 'payments.bankAccount', 'attachments', 'entries.project', 'entries.category', 'approvalRequests', 'preparedBy', 'approvedBy']);

        return view('vouchers.show', [
            'voucher'            => $voucher,
            'accounts'           => $this->accountsForPicker(),
            'projects'           => $this->projectsForPicker(),
            'payees'             => $this->payeesForPicker(),
            'categoriesForPicker' => \App\Models\ProjectCategory::selectOptions(),
            'types'              => Voucher::TYPES,
            'modes'              => Voucher::MODES,
            'sources'            => Voucher::SOURCES,
        ]);
    }

    /* ── Payables tab (open items only, with aging) ────────────────────── */

    public function payables(Request $request): View
    {
        $bucket = $request->query('bucket');

        $openQuery = Voucher::with(['project', 'sourceBankAccount.entity', 'payments.bankAccount', 'attachments'])
            ->whereIn('status', ['unpaid', 'partial', 'pdc'])
            ->where('approval_status', 'approved')
            ->orderBy('due_date')
            ->orderByDesc('voucher_date');

        if (auth()->user()->isAccounting()) {
            $openQuery->whereHas('approvalRequests', fn ($q) => $q
                ->where('type', VoucherRequest::TYPE_CREATE)
                ->where('requested_by', auth()->id()));
        }

        $open = $openQuery->get();

        // Group into aging buckets (PHP-side; amounts are encrypted).
        $buckets = collect(array_keys(self::AGING_LABELS))
            ->mapWithKeys(fn ($k) => [$k => ['count' => 0, 'amount' => 0.0]])
            ->toArray();

        foreach ($open as $v) {
            $b = $v->agingBucket();
            $buckets[$b]['count']  += 1;
            $buckets[$b]['amount'] += $v->balanceDue();
        }

        $rows = $bucket && array_key_exists($bucket, self::AGING_LABELS)
            ? $open->filter(fn ($v) => $v->agingBucket() === $bucket)->values()
            : $open;

        $summary = [
            'outstanding' => (float) $open->sum(fn ($v) => $v->balanceDue()),
            'overdue'     => (float) $open->filter->isOverdue()->sum(fn ($v) => $v->balanceDue()),
            'due_7d'      => (float) $open->filter(function ($v) {
                $d = $v->daysUntilDue();
                return $d !== null && $d >= 0 && $d <= 7;
            })->sum(fn ($v) => $v->balanceDue()),
            'count'       => $open->count(),
        ];

        return view('vouchers.payables', [
            'rows'         => $rows,
            'buckets'      => $buckets,
            'agingLabels'  => self::AGING_LABELS,
            'summary'      => $summary,
            'accounts'     => $this->accountsForPicker(),
            'modes'        => Voucher::MODES,
            'activeBucket' => $bucket,
        ]);
    }

    /* ── CRUD ──────────────────────────────────────────────────────────── */

    public function store(Request $request): RedirectResponse
    {
        try {
            $data = $this->validateVoucher($request);
            $this->validateAttachments($request, required: true);
            $entryRows = $this->validateAndBalanceEntries($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->stagePendingAttachments($request);
            throw $e;
        }

        $isAccounting = auth()->user()->isAccounting();

        $paidOnCreate = ! $isAccounting && ($data['payment_status'] ?? 'unpaid') === 'paid';
        unset($data['payment_status']);

        $data['status'] = 'unpaid';
        $data['approval_status'] = $isAccounting ? 'pending' : 'approved';
        $data['prepared_by'] = auth()->id();

        // Admin/CFO creating directly means there's no separate review step —
        // they're approving it themselves the moment they hit save.
        if (! $isAccounting) {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        // Accounting Staff are locked to the office they were invited for —
        // ignore whatever source the form submitted and use theirs instead.
        if ($lockedSource = auth()->user()->lockedSource()) {
            $data['source'] = $lockedSource;
        }

        $voucher = DB::transaction(function () use ($request, $data, $entryRows, $isAccounting) {
            $voucher = Voucher::create($data);
            $this->saveAttachmentsFromRequest($request, $voucher);

            foreach ($entryRows as $i => $row) {
                $voucher->entries()->create([
                    'category_id'  => $row['category_id'],
                    'entry_type'   => $row['entry_type'],
                    'amount'       => (float) $row['amount'],
                    'project_id'   => ($row['project_id'] ?? null) ?: null,
                    'description'  => $row['description'] ?? null,
                    'sort_order'   => $i,
                ]);
            }

            if ($isAccounting) {
                $voucher->approvalRequests()->create([
                    'type'         => VoucherRequest::TYPE_CREATE,
                    'requested_by' => auth()->id(),
                    'reason'       => $request->input('reason'),
                ]);
            }

            return $voucher;
        });

        // "Paid" at creation means the cash already went out — record the
        // full payment now so status, ledger and project outflow stay
        // derived from real VoucherPayment rows (no manual status override).
        // Accounting Staff can't pay out a voucher that isn't approved yet.
        if ($paidOnCreate) {
            VoucherService::recordPayment($voucher, [
                'bank_account_id' => $voucher->source_bank_account_id,
                'paid_on'         => $voucher->voucher_date->toDateString(),
                'amount'          => $voucher->amount_payable,
                'mode'            => $voucher->mode_of_payment,
            ]);
        }

        if ($isAccounting) {
            return redirect()->route('vouchers.show', $voucher)
                ->with('success', "Voucher {$voucher->voucher_no} submitted for CFO approval.");
        }

        return redirect()->route('vouchers.show', $voucher)
            ->with('success', "Voucher {$voucher->voucher_no} for {$voucher->payee_name} created (₱" . number_format((float) $voucher->amount_payable, 2) . ')' . ($paidOnCreate ? ' and marked as paid.' : '.'));
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        try {
            $data = $this->validateVoucher($request, $voucher);
            $this->validateAttachments($request, required: $voucher->attachments()->doesntExist());
            $entryRows = $this->validateAndBalanceEntries($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->stagePendingAttachments($request);
            throw $e;
        }

        if ($lockedSource = auth()->user()->lockedSource()) {
            $data['source'] = $lockedSource;
        }

        // Accounting Staff can't change an already-approved voucher directly —
        // their change goes to the CFO as an edit request instead. A voucher
        // they themselves submitted that's still pending stays directly
        // editable (nothing approved yet to protect).
        if (auth()->user()->isAccounting() && $voucher->approval_status === 'approved') {
            return $this->submitEditRequest($request, $voucher, $data, $entryRows);
        }

        $markPaid = ($data['payment_status'] ?? 'unpaid') === 'paid';
        unset($data['payment_status']);

        if ($voucher->status === 'cancelled') {
            $markPaid = false;
        }

        if ($voucher->status === 'paid' && ! $markPaid) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['payment_status' => 'A fully paid voucher cannot be set back to unpaid here — reverse the payment(s) from the voucher view first.']);
        }

        $sourceBankAccountId = $data['source_bank_account_id'] ?? $voucher->source_bank_account_id;
        if ($markPaid && $voucher->balanceDue() > 0 && ! $sourceBankAccountId) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['source_bank_account_id' => 'Set a source bank account before marking this voucher as paid.']);
        }

        // All validation passed — persist everything atomically so a later
        // failure (e.g. payment recording) can't leave a half-saved voucher.
        DB::transaction(function () use ($request, $voucher, $data, $entryRows) {
            $voucher->update($data);
            $this->saveAttachmentsFromRequest($request, $voucher);

            if (! empty($entryRows)) {
                $voucher->entries()->delete();
                foreach ($entryRows as $i => $row) {
                    $voucher->entries()->create([
                        'category_id' => $row['category_id'],
                        'entry_type'  => $row['entry_type'],
                        'amount'      => (float) $row['amount'],
                        'project_id'  => ($row['project_id'] ?? null) ?: null,
                        'description' => $row['description'] ?? null,
                        'sort_order'  => $i,
                    ]);
                }
            }
        });

        $voucher->refresh();

        $paidOnUpdate = false;
        if ($markPaid && $voucher->balanceDue() > 0) {
            VoucherService::recordPayment($voucher, [
                'bank_account_id' => $voucher->source_bank_account_id,
                'paid_on'         => now()->toDateString(),
                'amount'          => $voucher->balanceDue(),
                'mode'            => $voucher->mode_of_payment,
            ]);
            $paidOnUpdate = true;
            $voucher->refresh();
        }

        VoucherService::recompute($voucher);

        $message = "Voucher {$voucher->voucher_no} updated" . ($paidOnUpdate ? ' and marked as paid.' : '.');

        return redirect()->route('vouchers.show', $voucher)
            ->with('success', $message);
    }

    public function destroy(Request $request, Voucher $voucher): RedirectResponse
    {
        if (auth()->user()->isAccounting() && $voucher->isPendingApproval()) {
            return redirect()->back()->withErrors(['reason' => 'This voucher is still awaiting CFO approval and cannot be deleted yet.']);
        }

        if (auth()->user()->isAccounting() && $voucher->approval_status === 'approved') {
            $request->validate(['reason' => ['required', 'string', 'max:1000']]);

            if ($voucher->pendingRequest()) {
                return redirect()->back()->withErrors(['reason' => 'A request is already pending review for this voucher.']);
            }

            $voucher->approvalRequests()->create([
                'type'         => VoucherRequest::TYPE_DELETE,
                'requested_by' => auth()->id(),
                'reason'       => $request->input('reason'),
            ]);

            return redirect()->route('vouchers.show', $voucher)
                ->with('success', "Delete request for voucher {$voucher->voucher_no} submitted for CFO approval.");
        }

        // A voucher can still have a pending request riding on it (e.g. an
        // accounting-own voucher that's still "for approval", or one an
        // admin/cfo deletes outright while a request is mid-review) —
        // resolve it first so the approval queue never points at a
        // soft-deleted voucher.
        if ($pending = $voucher->pendingRequest()) {
            $pending->update([
                'status'       => VoucherRequest::STATUS_REJECTED,
                'reviewed_by'  => auth()->id(),
                'reviewed_at'  => now(),
                'review_note'  => 'Voucher was deleted before this request could be reviewed.',
            ]);
        }

        $no = $voucher->voucher_no;
        VoucherService::destroyVoucher($voucher);

        return redirect()->route('vouchers.index')
            ->with('success', "Voucher {$no} deleted. Any bank ledger and project rows it posted were reversed.");
    }

    /* ── status transitions ────────────────────────────────────────────── */

    public function cancel(Voucher $voucher): RedirectResponse
    {
        if ($voucher->amountPaid() > 0) {
            return redirect()->back()
                ->withErrors(['cancel' => "Voucher {$voucher->voucher_no} has payments recorded — reverse them before cancelling."]);
        }

        // No payments exist at this point, so outflow/inflow rows should
        // already be empty — this is a defensive cleanup in case any project
        // rows were left behind by an earlier sync.
        ProjectExpense::where('voucher_id', $voucher->id)->delete();
        ProjectCollection::where('voucher_id', $voucher->id)->delete();

        $voucher->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', "Voucher {$voucher->voucher_no} cancelled.");
    }

    public function reactivate(Voucher $voucher): RedirectResponse
    {
        $voucher->update(['status' => 'unpaid']);
        VoucherService::recompute($voucher);

        return redirect()->back()->with('success', "Voucher {$voucher->voucher_no} reactivated — its status now reflects its payments again.");
    }

    /* ── Payments ──────────────────────────────────────────────────────── */

    public function storePayment(Request $request, Voucher $voucher): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'paid_on'         => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'mode'            => ['nullable', Rule::in(array_keys(Voucher::MODES))],
            'check_no'        => ['nullable', 'string', 'max:100'],
            'check_date'      => ['nullable', 'date'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ]);

        $this->validateAttachments($request, required: true);

        // Accounting Staff can't post a payment directly — it goes to the CFO
        // as a payment-verification request instead, same shape as create/
        // edit/delete. Admin/CFO recording it themselves post immediately.
        if (auth()->user()->isAccounting()) {
            if ($voucher->pendingRequest()) {
                return redirect()->back()->withErrors(['amount' => 'A request is already pending review for this voucher.']);
            }

            DB::transaction(function () use ($request, $voucher, $data) {
                $this->saveAttachmentsFromRequest($request, $voucher);

                $voucher->approvalRequests()->create([
                    'type'         => VoucherRequest::TYPE_PAYMENT,
                    'requested_by' => auth()->id(),
                    'payload'      => $data,
                ]);
            });

            return redirect()->back()
                ->with('success', "Payment of ₱" . number_format((float) $data['amount'], 2) . " for {$voucher->voucher_no} submitted for CFO verification.");
        }

        DB::transaction(function () use ($request, $voucher, $data) {
            $this->saveAttachmentsFromRequest($request, $voucher);
            VoucherService::recordPayment($voucher, $data);
        });

        return redirect()->back()
            ->with('success', "Payment of ₱" . number_format((float) $data['amount'], 2) . " recorded for {$voucher->voucher_no}. Source account was deducted.");
    }

    public function destroyPayment(VoucherPayment $payment): RedirectResponse
    {
        VoucherService::deletePayment($payment);

        return redirect()->back()
            ->with('success', 'Payment reversed. The bank ledger and project rows it created were removed.');
    }

    /* ── Attachments ───────────────────────────────────────────────────── */

    public function storeAttachment(Request $request, Voucher $voucher): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('vouchers/' . $voucher->id, 'local');

        $voucher->attachments()->create([
            'uploaded_by'   => $request->user()?->id,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return redirect()->back()
            ->with('success', "Attached \"{$file->getClientOriginalName()}\" to voucher {$voucher->voucher_no}.");
    }

    public function downloadAttachment(VoucherAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(VoucherAttachment $attachment): RedirectResponse
    {
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return redirect()->back()->with('success', 'Attachment removed.');
    }

    /* ── Accounting Entries ─────────────────────────────────────────────── */

    public function storeEntry(Request $request, Voucher $voucher): RedirectResponse
    {
        $data = $request->validate([
            'category_id'  => ['required', 'exists:project_categories,id'],
            'entry_type'   => ['required', 'in:debit,credit'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'project_id'   => ['nullable', 'exists:projects,id'],
            'description'  => ['nullable', 'string', 'max:500'],
        ]);

        $data['project_id'] = ($data['project_id'] ?? null) ?: null;
        $data['sort_order'] = $voucher->entries()->max('sort_order') + 1;

        $voucher->entries()->create($data);

        // Re-sync project outflow if this voucher has payments.
        if ($voucher->amountPaid() > 0) {
            VoucherService::recompute($voucher->fresh());
        }

        return redirect()->back()->with('success', ucfirst($data['entry_type']) . ' entry added.');
    }

    public function destroyEntry(VoucherEntry $entry): RedirectResponse
    {
        $voucher = $entry->voucher;
        $entry->delete();

        if ($voucher && $voucher->amountPaid() > 0) {
            VoucherService::recompute($voucher->fresh());
        }

        return redirect()->back()->with('success', 'Entry removed.');
    }

    /* ── helpers ───────────────────────────────────────────────────────── */

    private function validateVoucher(Request $request, ?Voucher $voucher = null): array
    {
        return $request->validate([
            'voucher_no'             => [
                'required', 'string', 'max:50',
                Rule::unique('vouchers', 'voucher_no')->ignore($voucher?->id),
            ],
            'voucher_date'           => ['required', 'date'],
            'due_date'               => ['nullable', 'date'],
            'release_date'           => ['nullable', 'date'],
            'payee_name'             => ['required', 'string', 'max:255'],
            'payee_address'          => ['nullable', 'string', 'max:1000'],
            'source'                 => ['nullable', Rule::in(array_keys(Voucher::SOURCES))],
            'project_id'             => ['nullable', 'exists:projects,id'],
            'source_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'transaction_type'       => ['nullable', Rule::in(array_keys(Voucher::TYPES))],
            'source_document_type'   => ['nullable', Rule::in(array_keys(Voucher::SOURCE_DOCUMENTS))],
            'category_id'            => ['nullable', 'exists:project_categories,id'],
            'po_number'              => ['nullable', 'required_with:source_document_type', 'string', 'max:1000'],
            'reference'              => ['nullable', 'string', 'max:255'],
            'amount_payable'         => ['required', 'numeric', 'min:0.01'],
            'mode_of_payment'        => ['nullable', Rule::in(array_keys(Voucher::MODES))],
            'particular'             => ['nullable', 'string', 'max:2000'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'remarks'                => ['nullable', 'string', 'max:2000'],
            'source_of_fund'         => ['nullable', 'string', 'max:1000'],
            'or_ref'                 => ['nullable', 'string', 'max:255'],
            'change_amount'          => ['nullable', 'numeric', 'min:0'],
            'payment_status'         => ['nullable', Rule::in(['paid', 'unpaid'])],
        ]);
    }

    /**
     * Shared by store() and update(): parses + validates the accounting
     * entries rows submitted from the voucher form, and checks debit/credit
     * balance. Returns the cleaned rows (possibly empty).
     */
    private function validateAndBalanceEntries(Request $request): array
    {
        $entryRows = collect($request->input('entries', []))
            ->filter(fn ($r) => ! empty($r['amount']) && (float) $r['amount'] > 0)
            ->values()->all();

        if (empty($entryRows)) {
            return $entryRows;
        }

        $request->validate([
            'entries'               => ['array'],
            'entries.*.category_id' => ['required', 'exists:project_categories,id'],
            'entries.*.entry_type'  => ['required', 'in:debit,credit'],
            'entries.*.amount'      => ['required', 'numeric', 'min:0.01'],
            'entries.*.project_id'  => ['nullable', 'exists:projects,id'],
            'entries.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        $totalDebit  = collect($entryRows)->where('entry_type', 'debit')->sum(fn ($r) => (float) $r['amount']);
        $totalCredit = collect($entryRows)->where('entry_type', 'credit')->sum(fn ($r) => (float) $r['amount']);

        if (abs($totalDebit - $totalCredit) > 0.005) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'entries' => 'Total Debit must equal Total Credit. Difference: ₱' . number_format(abs($totalDebit - $totalCredit), 2),
            ]);
        }

        return $entryRows;
    }

    /**
     * Accounting Staff editing an already-approved voucher — record the
     * proposed changes as a pending request instead of touching the live
     * voucher. CFO/admin apply or discard it via VoucherRequestService.
     */
    private function submitEditRequest(Request $request, Voucher $voucher, array $data, array $entryRows): RedirectResponse
    {
        if ($voucher->pendingRequest()) {
            $this->stagePendingAttachments($request);
            return redirect()->back()->withInput()->withErrors(['reason' => 'A request is already pending review for this voucher.']);
        }

        try {
            $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->stagePendingAttachments($request);
            throw $e;
        }
        unset($data['payment_status']);

        DB::transaction(function () use ($request, $voucher, $data, $entryRows) {
            // Attachments aren't part of the gated edit payload — save them
            // straight onto the voucher (like the direct-edit path does) so
            // the CFO can already see them while reviewing the rest of the
            // proposed change, instead of the files being silently dropped.
            $this->saveAttachmentsFromRequest($request, $voucher);

            $voucher->approvalRequests()->create([
                'type'            => VoucherRequest::TYPE_EDIT,
                'requested_by'    => auth()->id(),
                'reason'          => $request->input('reason'),
                'payload'         => $data,
                'entries_payload' => $entryRows ?: null,
            ]);
        });

        return redirect()->route('vouchers.show', $voucher)
            ->with('success', "Edit request for voucher {$voucher->voucher_no} submitted for CFO approval.");
    }

    /**
     * @param bool $required When true, at least one attachment must already
     * be on the voucher or be uploaded in this request — used on create, and
     * on update for any voucher that still has none (legacy records can keep
     * saving other changes without being forced to backfill one immediately).
     */
    private function validateAttachments(Request $request, bool $required = false): void
    {
        $hasKept = ! empty($request->input('kept_attachment_tokens', []));

        $request->validate([
            'attachments'   => [($required && ! $hasKept) ? 'required' : 'nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],
        ], [
            'attachments.required' => 'Attach at least one supporting document (invoice, receipt, etc.) before saving this voucher.',
        ]);
    }

    /**
     * A browser can never re-populate a file input after a validation error
     * on some other field, so any file the user already picked would
     * otherwise vanish on redisplay. We opportunistically stash valid
     * uploads to a pending area and hand back an encrypted token the form
     * can carry across the retry — see resolvePendingAttachments() and the
     * `kept_attachment_tokens` handling in saveAttachmentsFromRequest().
     */
    private function stagePendingAttachments(Request $request): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $tokens = $request->input('kept_attachment_tokens', []);
        $allowedMimes = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

        foreach ($request->file('attachments') as $file) {
            if (! $file || ! $file->isValid() || $file->getSize() > 10240 * 1024) {
                continue;
            }
            if (! in_array(strtolower($file->getClientOriginalExtension()), $allowedMimes, true)) {
                continue;
            }

            $path = $file->store('attachments/pending', 'local');

            $tokens[] = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]));
        }

        $request->merge(['kept_attachment_tokens' => $tokens]);
    }

    /**
     * Decrypts the `kept_attachment_tokens` carried over from a failed
     * submission so the create/edit form can show "already attached" chips
     * instead of an empty file input.
     *
     * @return list<array{token: string, name: string, size: int}>
     */
    private function resolvePendingAttachments(): array
    {
        return collect(old('kept_attachment_tokens', []))
            ->map(function ($token) {
                try {
                    $meta = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($token), true);
                } catch (\Throwable $e) {
                    return null;
                }
                return $meta ? ['token' => $token, 'name' => $meta['name'], 'size' => $meta['size']] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function saveAttachmentsFromRequest(Request $request, Voucher $voucher): void
    {
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }

                $path = $file->store('vouchers/' . $voucher->id, 'local');

                $voucher->attachments()->create([
                    'uploaded_by'   => $request->user()?->id,
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }
        }

        foreach ($request->input('kept_attachment_tokens', []) as $token) {
            $this->attachFromPendingToken($voucher, $token, $request);
        }
    }

    private function attachFromPendingToken(Voucher $voucher, string $token, Request $request): void
    {
        try {
            $meta = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return;
        }

        if (! $meta || ! Storage::disk('local')->exists($meta['path'] ?? '')) {
            return;
        }

        $newPath = 'vouchers/' . $voucher->id . '/' . basename($meta['path']);
        Storage::disk('local')->move($meta['path'], $newPath);

        $voucher->attachments()->create([
            'uploaded_by'   => $request->user()?->id,
            'original_name' => $meta['name'],
            'path'          => $newPath,
            'mime_type'     => $meta['mime'],
            'size'          => $meta['size'],
        ]);
    }

    private function projectsForPicker()
    {
        return Project::orderBy('kind')->orderBy('name')->get(['id', 'name', 'kind', 'code', 'client_name']);
    }

    private function payeesForPicker()
    {
        return Payee::orderBy('name')->pluck('name');
    }

    private function accountsForPicker()
    {
        return BankAccount::with('entity')->orderBy('name')->get();
    }
}
