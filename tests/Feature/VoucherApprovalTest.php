<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoucherApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private function basePayload(string $no): array
    {
        return [
            'voucher_no'      => $no,
            'voucher_date'    => now()->format('Y-m-d'),
            'payee_name'      => 'Approval Test Payee',
            'amount_payable'  => 1000,
            'mode_of_payment' => 'cash',
            'payment_status'  => 'unpaid',
        ];
    }

    /** @return array{attachments: array<UploadedFile>} */
    private function withAttachment(): array
    {
        Storage::fake('local');

        return ['attachments' => [UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')]];
    }

    public function test_accounting_store_creates_pending_voucher_with_create_request(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);

        $response = $this->actingAs($staff)->post(route('vouchers.store'), $this->basePayload('APR-001') + $this->withAttachment() + ['reason' => 'New supplier invoice']);

        $voucher = Voucher::where('voucher_no', 'APR-001')->first();
        $this->assertNotNull($voucher);
        $response->assertRedirect(route('vouchers.show', $voucher));

        $this->assertEquals('pending', $voucher->approval_status);

        $request = VoucherRequest::where('voucher_id', $voucher->id)->first();
        $this->assertNotNull($request);
        $this->assertEquals(VoucherRequest::TYPE_CREATE, $request->type);
        $this->assertEquals(VoucherRequest::STATUS_PENDING, $request->status);
        $this->assertEquals($staff->id, $request->requested_by);
    }

    public function test_admin_store_is_unaffected_by_approval_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('vouchers.store'), $this->basePayload('APR-002') + $this->withAttachment());

        $voucher = Voucher::where('voucher_no', 'APR-002')->first();
        $this->assertNotNull($voucher);
        $this->assertEquals('approved', $voucher->approval_status);
        $this->assertEquals(0, VoucherRequest::where('voucher_id', $voucher->id)->count());
    }

    public function test_accounting_update_on_approved_voucher_creates_edit_request_and_leaves_voucher_untouched(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);

        $voucher = Voucher::create($this->basePayload('APR-003') + ['approval_status' => 'approved']);

        $payload = $this->basePayload('APR-003') + $this->withAttachment();
        $payload['payee_name'] = 'Changed Payee';
        $payload['reason'] = 'Corrected payee name';

        $response = $this->actingAs($staff)->put(route('vouchers.update', $voucher), $payload);
        $response->assertRedirect(route('vouchers.show', $voucher));

        $voucher->refresh();
        $this->assertEquals('Approval Test Payee', $voucher->payee_name, 'Live voucher must stay untouched until CFO approves.');

        $request = VoucherRequest::where('voucher_id', $voucher->id)->first();
        $this->assertNotNull($request);
        $this->assertEquals(VoucherRequest::TYPE_EDIT, $request->type);
        $this->assertEquals('Changed Payee', $request->payload['payee_name']);
    }

    public function test_accounting_update_on_own_pending_voucher_applies_directly(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);

        $voucher = Voucher::create($this->basePayload('APR-004') + ['approval_status' => 'pending']);

        $payload = $this->basePayload('APR-004') + $this->withAttachment();
        $payload['payee_name'] = 'Edited Before Approval';

        $this->actingAs($staff)->put(route('vouchers.update', $voucher), $payload);

        $voucher->refresh();
        $this->assertEquals('Edited Before Approval', $voucher->payee_name, 'Own still-pending voucher should be directly editable.');
        $this->assertEquals(0, VoucherRequest::where('voucher_id', $voucher->id)->count());
    }

    public function test_accounting_destroy_on_approved_voucher_creates_delete_request(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);

        $voucher = Voucher::create($this->basePayload('APR-005') + ['approval_status' => 'approved']);

        $response = $this->actingAs($staff)->delete(route('vouchers.destroy', $voucher), ['reason' => 'Duplicate entry']);
        $response->assertRedirect(route('vouchers.show', $voucher));

        $this->assertNotNull(Voucher::find($voucher->id), 'Voucher must not be deleted until approved.');

        $request = VoucherRequest::where('voucher_id', $voucher->id)->first();
        $this->assertEquals(VoucherRequest::TYPE_DELETE, $request->type);
        $this->assertEquals('Duplicate entry', $request->reason);
    }

    public function test_accounting_voucher_source_is_locked_to_their_office(): void
    {
        $bgcStaff = User::factory()->create(['role' => 'accounting', 'source' => 'bgc']);

        $payload = $this->basePayload('APR-040') + $this->withAttachment();
        $payload['source'] = 'mindanao'; // attempt to tamper — should be ignored

        $this->actingAs($bgcStaff)->post(route('vouchers.store'), $payload);

        $voucher = Voucher::where('voucher_no', 'APR-040')->first();
        $this->assertEquals('bgc', $voucher->source);
    }

    public function test_accounting_edit_request_payload_keeps_locked_source(): void
    {
        $bgcStaff = User::factory()->create(['role' => 'accounting', 'source' => 'bgc']);

        $voucher = Voucher::create($this->basePayload('APR-041') + ['approval_status' => 'approved', 'source' => 'bgc']);

        $payload = $this->basePayload('APR-041') + $this->withAttachment();
        $payload['source'] = 'mindanao';
        $payload['reason'] = 'tamper attempt';

        $this->actingAs($bgcStaff)->put(route('vouchers.update', $voucher), $payload);

        $request = VoucherRequest::where('voucher_id', $voucher->id)->first();
        $this->assertEquals('bgc', $request->payload['source']);
    }

    public function test_accounting_cannot_delete_voucher_still_awaiting_approval(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);

        $voucher = Voucher::create($this->basePayload('APR-006b') + ['approval_status' => 'pending']);
        $voucher->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $staff->id]);

        $response = $this->actingAs($staff)->delete(route('vouchers.destroy', $voucher));

        $response->assertSessionHasErrors('reason');
        $this->assertNotNull(Voucher::find($voucher->id));
    }

    public function test_cfo_approve_on_create_request_flips_approval_status(): void
    {
        $cfo = User::factory()->create(['role' => 'cfo']);
        $voucher = Voucher::create($this->basePayload('APR-006') + ['approval_status' => 'pending']);
        $request = $voucher->approvalRequests()->create([
            'type' => VoucherRequest::TYPE_CREATE,
            'requested_by' => User::factory()->create(['role' => 'accounting'])->id,
        ]);

        $response = $this->actingAs($cfo)->post(route('voucher-requests.approve', $request));
        $response->assertRedirect(route('voucher-requests.index'));

        $voucher->refresh();
        $request->refresh();
        $this->assertEquals('approved', $voucher->approval_status);
        $this->assertEquals(VoucherRequest::STATUS_APPROVED, $request->status);
        $this->assertEquals($cfo->id, $request->reviewed_by);
    }

    public function test_cfo_approve_on_edit_request_applies_payload_to_voucher(): void
    {
        $cfo = User::factory()->create(['role' => 'cfo']);
        $voucher = Voucher::create($this->basePayload('APR-007') + ['approval_status' => 'approved']);
        $staff = User::factory()->create(['role' => 'accounting']);

        $request = $voucher->approvalRequests()->create([
            'type'    => VoucherRequest::TYPE_EDIT,
            'requested_by' => $staff->id,
            'payload' => ['payee_name' => 'Approved New Payee', 'amount_payable' => 1500],
        ]);

        $this->actingAs($cfo)->post(route('voucher-requests.approve', $request));

        $voucher->refresh();
        $this->assertEquals('Approved New Payee', $voucher->payee_name);
        $this->assertEquals(1500, (float) $voucher->amount_payable);
    }

    public function test_cfo_reject_on_delete_request_leaves_voucher_active(): void
    {
        $cfo = User::factory()->create(['role' => 'cfo']);
        $voucher = Voucher::create($this->basePayload('APR-008') + ['approval_status' => 'approved']);
        $staff = User::factory()->create(['role' => 'accounting']);

        $request = $voucher->approvalRequests()->create([
            'type' => VoucherRequest::TYPE_DELETE,
            'requested_by' => $staff->id,
            'reason' => 'Mistakenly requested',
        ]);

        $response = $this->actingAs($cfo)->post(route('voucher-requests.reject', $request), ['review_note' => 'Still needed, keep it.']);
        $response->assertRedirect(route('voucher-requests.index'));

        $this->assertNotNull(Voucher::find($voucher->id));
        $request->refresh();
        $this->assertEquals(VoucherRequest::STATUS_REJECTED, $request->status);
        $this->assertEquals('Still needed, keep it.', $request->review_note);
    }

    public function test_accounting_index_only_shows_own_submitted_vouchers(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);
        $other = User::factory()->create(['role' => 'accounting']);

        $mine = Voucher::create($this->basePayload('APR-020') + ['approval_status' => 'pending']);
        $mine->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $staff->id]);

        $notMine = Voucher::create($this->basePayload('APR-021') + ['approval_status' => 'pending']);
        $notMine->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $other->id]);

        $response = $this->actingAs($staff)->get(route('vouchers.index'));

        $response->assertSee('APR-020');
        $response->assertDontSee('APR-021');
    }

    public function test_voucher_show_displays_rejection_reason_to_requester(): void
    {
        $cfo   = User::factory()->create(['role' => 'cfo']);
        $staff = User::factory()->create(['role' => 'accounting']);

        $voucher = Voucher::create($this->basePayload('APR-022') + ['approval_status' => 'pending']);
        $request = $voucher->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $staff->id]);

        $this->actingAs($cfo)->post(route('voucher-requests.reject', $request), ['review_note' => 'Missing supporting receipt.']);

        $response = $this->actingAs($staff)->get(route('vouchers.show', $voucher));
        $response->assertSee('Rejected by', false);
        $response->assertSee('Missing supporting receipt.');
    }

    public function test_accounting_dashboard_renders_with_personal_stats(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);
        $voucher = Voucher::create($this->basePayload('APR-023') + ['approval_status' => 'pending']);
        $voucher->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $staff->id]);

        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertSee('My vouchers');
    }

    public function test_deleting_voucher_with_pending_request_resolves_it_instead_of_orphaning(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'accounting']);

        $voucher = Voucher::create($this->basePayload('APR-030') + ['approval_status' => 'pending']);
        $request = $voucher->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $staff->id]);

        $this->actingAs($admin)->delete(route('vouchers.destroy', $voucher), ['reason' => 'no longer needed']);

        $request->refresh();
        $this->assertEquals(VoucherRequest::STATUS_REJECTED, $request->status);
        $this->assertNotNull($request->review_note);

        // The approval queue must not crash trying to render an orphaned pending request.
        $cfo = User::factory()->create(['role' => 'cfo']);
        $this->actingAs($cfo)->get(route('voucher-requests.index'))->assertOk();
    }

    public function test_accounting_role_cannot_access_approval_queue(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);

        $this->actingAs($staff)->get(route('voucher-requests.index'))->assertForbidden();
    }

    public function test_admin_and_cfo_can_access_approval_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cfo   = User::factory()->create(['role' => 'cfo']);

        $this->actingAs($admin)->get(route('voucher-requests.index'))->assertOk();
        $this->actingAs($cfo)->get(route('voucher-requests.index'))->assertOk();
    }

    public function test_review_screen_renders_for_each_request_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::first();
        $category = ProjectCategory::first();

        if (! $category) {
            $this->markTestSkipped('No ProjectCategory exists in DB to test against.');
        }

        $createVoucher = Voucher::create($this->basePayload('APR-009') + ['approval_status' => 'pending']);
        $createRequest = $createVoucher->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $admin->id]);

        $editVoucher = Voucher::create($this->basePayload('APR-010') + ['approval_status' => 'approved']);
        $editVoucher->entries()->create(['entry_type' => 'debit', 'amount' => 1000, 'category_id' => $category->id, 'project_id' => $project?->id]);
        $editRequest = $editVoucher->approvalRequests()->create([
            'type' => VoucherRequest::TYPE_EDIT,
            'requested_by' => $admin->id,
            'reason' => 'Test edit',
            'payload' => ['amount_payable' => 1200],
            'entries_payload' => [['category_id' => $category->id, 'entry_type' => 'debit', 'amount' => 1200, 'project_id' => $project?->id, 'description' => null]],
        ]);

        $deleteVoucher = Voucher::create($this->basePayload('APR-011') + ['approval_status' => 'approved']);
        $deleteRequest = $deleteVoucher->approvalRequests()->create(['type' => VoucherRequest::TYPE_DELETE, 'requested_by' => $admin->id, 'reason' => 'Test delete']);

        $this->actingAs($admin)->get(route('voucher-requests.show', $createRequest))->assertOk();
        $this->actingAs($admin)->get(route('voucher-requests.show', $editRequest))->assertOk();
        $this->actingAs($admin)->get(route('voucher-requests.show', $deleteRequest))->assertOk();
        $this->actingAs($admin)->get(route('voucher-requests.index'))->assertOk();
    }

    public function test_store_without_attachment_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('vouchers.store'), $this->basePayload('APR-050'));

        $response->assertSessionHasErrors('attachments');
        $this->assertNull(Voucher::where('voucher_no', 'APR-050')->first());
    }

    public function test_update_requires_attachment_when_voucher_has_none(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = Voucher::create($this->basePayload('APR-051') + ['approval_status' => 'approved']);

        $response = $this->actingAs($admin)->put(route('vouchers.update', $voucher), $this->basePayload('APR-051'));

        $response->assertSessionHasErrors('attachments');
    }

    public function test_update_does_not_require_attachment_when_voucher_already_has_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = Voucher::create($this->basePayload('APR-052') + ['approval_status' => 'approved']);
        $voucher->attachments()->create([
            'uploaded_by'   => $admin->id,
            'original_name' => 'existing.pdf',
            'path'          => 'vouchers/existing.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 100,
        ]);

        $payload = $this->basePayload('APR-052');
        $payload['payee_name'] = 'Updated Payee';

        $response = $this->actingAs($admin)->put(route('vouchers.update', $voucher), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Updated Payee', $voucher->refresh()->payee_name);
    }

    public function test_cfo_can_see_attachment_when_reviewing_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'accounting']);

        $voucher = Voucher::create($this->basePayload('APR-053') + ['approval_status' => 'pending']);
        $voucher->attachments()->create([
            'uploaded_by'   => $staff->id,
            'original_name' => 'supplier-invoice.pdf',
            'path'          => 'vouchers/supplier-invoice.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 200,
        ]);
        $request = $voucher->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $staff->id]);

        $response = $this->actingAs($admin)->get(route('voucher-requests.show', $request));

        $response->assertOk();
        $response->assertSee('supplier-invoice.pdf');
    }

    public function test_admin_store_self_approves_with_prepared_and_approved_by_set(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('vouchers.store'), $this->basePayload('APR-060') + $this->withAttachment());

        $voucher = Voucher::where('voucher_no', 'APR-060')->first();
        $this->assertEquals($admin->id, $voucher->prepared_by);
        $this->assertEquals($admin->id, $voucher->approved_by);
        $this->assertNotNull($voucher->approved_at);
    }

    public function test_accounting_store_is_prepared_but_not_yet_approved(): void
    {
        $staff = User::factory()->create(['role' => 'accounting']);

        $this->actingAs($staff)->post(route('vouchers.store'), $this->basePayload('APR-061') + $this->withAttachment());

        $voucher = Voucher::where('voucher_no', 'APR-061')->first();
        $this->assertEquals($staff->id, $voucher->prepared_by);
        $this->assertNull($voucher->approved_by);
        $this->assertNull($voucher->approved_at);
    }

    public function test_cfo_approving_create_request_stamps_approved_by(): void
    {
        $cfo   = User::factory()->create(['role' => 'cfo']);
        $staff = User::factory()->create(['role' => 'accounting']);

        $voucher = Voucher::create($this->basePayload('APR-062') + ['approval_status' => 'pending', 'prepared_by' => $staff->id]);
        $request = $voucher->approvalRequests()->create(['type' => VoucherRequest::TYPE_CREATE, 'requested_by' => $staff->id]);

        $this->actingAs($cfo)->post(route('voucher-requests.approve', $request));

        $voucher->refresh();
        $this->assertEquals($cfo->id, $voucher->approved_by);
        $this->assertNotNull($voucher->approved_at);
        $this->assertEquals('Chief Finance Officer', $voucher->approverPositionLabel());
    }

    public function test_custom_position_overrides_role_default_label(): void
    {
        $head = User::factory()->create(['role' => 'accounting', 'source' => 'mindanao', 'position' => 'Accounting Head']);
        $plainStaff = User::factory()->create(['role' => 'accounting', 'source' => 'mindanao']);

        $this->assertEquals('Accounting Head', $head->positionLabel());
        $this->assertEquals('Accounting', $plainStaff->positionLabel());

        // Same functional scope as any accounting user — locked source, restricted access.
        $this->assertEquals('mindanao', $head->lockedSource());
        $this->assertTrue($head->isAccounting());
    }
}
