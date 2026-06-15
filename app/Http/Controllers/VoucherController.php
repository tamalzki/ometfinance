<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Payee;
use App\Models\Project;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Models\VoucherPayment;
use App\Services\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $query = Voucher::with(['project', 'sourceBankAccount.entity', 'payments.bankAccount', 'attachments'])
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if ($status && array_key_exists($status, Voucher::STATUSES)) {
            $query->where('status', $status);
        }
        if ($type && array_key_exists($type, Voucher::TYPES)) {
            $query->where('transaction_type', $type);
        }

        $activeProject = $projectId ? Project::find($projectId) : null;
        if ($activeProject) {
            $query->where('project_id', $activeProject->id);
        }

        $vouchers = $query->get();
        $all      = Voucher::with('payments')->get();

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
            'activeStatus'  => $status,
            'activeType'    => $type,
            'activeProject' => $activeProject,
            'newVoucher'    => $request->boolean('new_voucher'),
        ]);
    }

    /* ── Payables tab (open items only, with aging) ────────────────────── */

    public function payables(Request $request): View
    {
        $bucket = $request->query('bucket');

        $open = Voucher::with(['project', 'sourceBankAccount.entity', 'payments.bankAccount', 'attachments'])
            ->whereIn('status', ['unpaid', 'partial', 'pdc'])
            ->orderBy('due_date')
            ->orderByDesc('voucher_date')
            ->get();

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
        $data = $this->validateVoucher($request);
        $this->validateAttachments($request);

        $data['status'] = 'unpaid';

        $voucher = Voucher::create($data);
        $this->saveAttachmentsFromRequest($request, $voucher);

        return redirect()->route('vouchers.index', $voucher->project_id ? ['project_id' => $voucher->project_id] : [])
            ->with('success', "Voucher {$voucher->voucher_no} for {$voucher->payee_name} created (₱" . number_format((float) $voucher->amount_payable, 2) . ').');
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        $data = $this->validateVoucher($request, $voucher);
        $this->validateAttachments($request);

        // Once money has moved against this voucher, the figures that drove
        // that payment must stay put — changing them would desync the ledger
        // and project postings the payment already created.
        if ($voucher->payments()->exists()) {
            unset($data['amount_payable'], $data['payee_name'], $data['project_id']);
        }

        $voucher->update($data);
        $this->saveAttachmentsFromRequest($request, $voucher);
        VoucherService::recompute($voucher);

        return redirect()->route('vouchers.index', $voucher->project_id ? ['project_id' => $voucher->project_id] : [])
            ->with('success', "Voucher {$voucher->voucher_no} updated.");
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
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

        VoucherService::recordPayment($voucher, $data);

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
            'project_id'             => ['nullable', 'exists:projects,id'],
            'source_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'transaction_type'       => ['nullable', Rule::in(array_keys(Voucher::TYPES))],
            'category_id'            => ['required', 'exists:project_categories,id'],
            'reference'              => ['nullable', 'string', 'max:255'],
            'amount_payable'         => ['required', 'numeric', 'min:0.01'],
            'mode_of_payment'        => ['nullable', Rule::in(array_keys(Voucher::MODES))],
            'particular'             => ['nullable', 'string', 'max:2000'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'remarks'                => ['nullable', 'string', 'max:2000'],
            'source_of_fund'         => ['nullable', 'string', 'max:1000'],
            'or_ref'                 => ['nullable', 'string', 'max:255'],
            'change_amount'          => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function validateAttachments(Request $request): void
    {
        $request->validate([
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],
        ]);
    }

    private function saveAttachmentsFromRequest(Request $request, Voucher $voucher): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

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
