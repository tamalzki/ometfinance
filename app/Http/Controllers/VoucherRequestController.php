<?php

namespace App\Http\Controllers;

use App\Models\VoucherRequest;
use App\Services\VoucherRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherRequestController extends Controller
{
    public function index(Request $request): View
    {
        $type   = $request->query('type');
        $status = $request->query('status', VoucherRequest::STATUS_PENDING);

        if (! in_array($status, [VoucherRequest::STATUS_PENDING, VoucherRequest::STATUS_APPROVED, VoucherRequest::STATUS_REJECTED], true)) {
            $status = VoucherRequest::STATUS_PENDING;
        }

        $query = VoucherRequest::with(['voucher' => fn ($q) => $q->withTrashed(), 'requestedBy', 'reviewedBy'])
            ->where('status', $status)
            ->latest();

        if ($type && in_array($type, [VoucherRequest::TYPE_CREATE, VoucherRequest::TYPE_EDIT, VoucherRequest::TYPE_DELETE], true)) {
            $query->where('type', $type);
        }

        $requests = $query->get();

        $counts = [
            'create' => VoucherRequest::where('status', VoucherRequest::STATUS_PENDING)->where('type', VoucherRequest::TYPE_CREATE)->count(),
            'edit'   => VoucherRequest::where('status', VoucherRequest::STATUS_PENDING)->where('type', VoucherRequest::TYPE_EDIT)->count(),
            'delete' => VoucherRequest::where('status', VoucherRequest::STATUS_PENDING)->where('type', VoucherRequest::TYPE_DELETE)->count(),
        ];

        return view('vouchers.requests.index', [
            'requests'     => $requests,
            'counts'       => $counts,
            'activeType'   => $type,
            'activeStatus' => $status,
        ]);
    }

    public function show(VoucherRequest $voucherRequest): View
    {
        $voucherRequest->load([
            'voucher' => fn ($q) => $q->withTrashed()->with(['project', 'entries.category', 'entries.project', 'attachments', 'preparedBy', 'approvedBy']),
            'requestedBy', 'reviewedBy',
        ]);

        return view('vouchers.requests.show', [
            'voucherRequest'  => $voucherRequest,
            'changedFields'   => $voucherRequest->isEdit() ? $voucherRequest->changedFields() : [],
            'unchangedFields' => $voucherRequest->isEdit() ? $voucherRequest->unchangedFields() : [],
            'entriesDiff'     => $voucherRequest->isEdit() ? $voucherRequest->entriesDiff() : null,
        ]);
    }

    public function approve(VoucherRequest $voucherRequest): RedirectResponse
    {
        if (! $voucherRequest->isPending()) {
            return redirect()->route('voucher-requests.index')->with('success', 'This request was already reviewed.');
        }

        VoucherRequestService::approve($voucherRequest, auth()->user());

        return redirect()->route('voucher-requests.index')
            ->with('success', "{$voucherRequest->typeLabel()} for voucher {$voucherRequest->voucher->voucher_no} approved.");
    }

    public function reject(Request $request, VoucherRequest $voucherRequest): RedirectResponse
    {
        if (! $voucherRequest->isPending()) {
            return redirect()->route('voucher-requests.index')->with('success', 'This request was already reviewed.');
        }

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        VoucherRequestService::reject($voucherRequest, auth()->user(), $data['review_note'] ?? null);

        return redirect()->route('voucher-requests.index')
            ->with('success', "{$voucherRequest->typeLabel()} for voucher {$voucherRequest->voucher->voucher_no} rejected.");
    }
}
