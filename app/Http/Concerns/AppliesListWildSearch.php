<?php

namespace App\Http\Concerns;

use App\Models\Transfer;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

trait AppliesListWildSearch
{
    protected function normalizeSearch(?string $search): string
    {
        return trim((string) $search);
    }

    protected function likePattern(string $search): string
    {
        return '%' . addcslashes($search, '%_\\') . '%';
    }

    /** @param  Builder<\App\Models\Voucher>  $query */
    protected function applyVoucherWildSearch(Builder $query, ?string $search): void
    {
        $search = $this->normalizeSearch($search);
        if ($search === '') {
            return;
        }

        $like = $this->likePattern($search);

        $query->where(function (Builder $q) use ($like, $search) {
            $q->where('voucher_no', 'like', $like)
                ->orWhere('payee_name', 'like', $like)
                ->orWhere('po_number', 'like', $like)
                ->orWhere('reference', 'like', $like)
                ->orWhere('particular', 'like', $like)
                ->orWhere('remarks', 'like', $like)
                ->orWhere('source', 'like', $like)
                ->orWhere('transaction_type', 'like', $like)
                ->orWhere('source_document_type', 'like', $like)
                ->orWhereHas('project', fn (Builder $p) => $p->where('name', 'like', $like))
                ->orWhereHas('entries.project', fn (Builder $p) => $p->where('name', 'like', $like))
                ->orWhereHas('entries.category', fn (Builder $c) => $c->where('name', 'like', $like)
                    ->orWhereHas('parent', fn (Builder $p) => $p->where('name', 'like', $like)))
                ->orWhereHas('sourceBankAccount', fn (Builder $a) => $a->where('name', 'like', $like)
                    ->orWhereHas('entity', fn (Builder $e) => $e->where('name', 'like', $like)));

            foreach (Voucher::TYPES as $key => $label) {
                if (stripos($key, $search) !== false || stripos($label, $search) !== false) {
                    $q->orWhere('transaction_type', $key);
                }
            }

            foreach (Voucher::SOURCES as $key => $label) {
                if (stripos($key, $search) !== false || stripos($label, $search) !== false) {
                    $q->orWhere('source', $key);
                }
            }

            foreach (Voucher::STATUSES as $key => $label) {
                if (stripos($key, $search) !== false || stripos($label, $search) !== false) {
                    $q->orWhere('status', $key);
                }
            }
        });
    }

    /** @param  Builder<\App\Models\Transfer>  $query */
    protected function applyTransferWildSearch(Builder $query, ?string $search): void
    {
        $search = $this->normalizeSearch($search);
        if ($search === '') {
            return;
        }

        $like = $this->likePattern($search);

        $query->where(function (Builder $q) use ($like, $search) {
            $q->where('memo', 'like', $like)
                ->orWhere('reason', 'like', $like)
                ->orWhere('purpose', 'like', $like)
                ->orWhereHas('fromAccount', fn (Builder $a) => $a->where('name', 'like', $like)
                    ->orWhere('bank_name', 'like', $like)
                    ->orWhereHas('entity', fn (Builder $e) => $e->where('name', 'like', $like)))
                ->orWhereHas('toAccount', fn (Builder $a) => $a->where('name', 'like', $like)
                    ->orWhere('bank_name', 'like', $like)
                    ->orWhereHas('entity', fn (Builder $e) => $e->where('name', 'like', $like)))
                ->orWhereHas('fromProject', fn (Builder $p) => $p->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like))
                ->orWhereHas('toProject', fn (Builder $p) => $p->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like));

            foreach (Transfer::PURPOSES as $key => $label) {
                if (stripos($key, $search) !== false || stripos($label, $search) !== false) {
                    $q->orWhere('purpose', $key);
                }
            }
        });
    }

    protected function disburseListPartialResponse(Request $request, string $partialView, array $data): ?Response
    {
        if (! $request->header('X-Disburse-Partial')) {
            return null;
        }

        return response(view($partialView, $data)->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
