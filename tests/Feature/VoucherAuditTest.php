<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoucherAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pages_render_without_errors(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = ProjectCategory::first();
        $project = Project::first();

        if (! $category || ! $project) {
            $this->markTestSkipped('Need at least one Project and ProjectCategory in DB to test against.');
        }

        // Voucher with no entries, no payments, no attachments — exercises every empty-state branch.
        $bare = Voucher::create([
            'voucher_no' => 'AUDIT-RENDER-BARE',
            'voucher_date' => now(),
            'payee_name' => 'Render Audit Bare',
            'amount_payable' => 100,
            'status' => 'unpaid',
            'mode_of_payment' => 'cash',
        ]);

        // Voucher with multi-project entries and a full payment — exercises populated branches.
        $full = Voucher::create([
            'voucher_no' => 'AUDIT-RENDER-FULL',
            'voucher_date' => now(),
            'payee_name' => 'Render Audit Full',
            'amount_payable' => 500,
            'status' => 'unpaid',
            'mode_of_payment' => 'cash',
            'source_bank_account_id' => \App\Models\BankAccount::first()?->id,
        ]);
        $full->entries()->create(['entry_type' => 'debit', 'amount' => 500, 'project_id' => $project->id, 'category_id' => $category->id, 'description' => 'Render test']);
        $full->entries()->create(['entry_type' => 'credit', 'amount' => 500, 'project_id' => $project->id, 'category_id' => $category->id]);
        \App\Services\VoucherService::recordPayment($full, ['amount' => 500, 'paid_on' => now()->toDateString()]);

        $this->actingAs($user)->get(route('vouchers.index'))->assertOk();
        $this->actingAs($user)->get(route('vouchers.create'))->assertOk();
        $this->actingAs($user)->get(route('vouchers.payables'))->assertOk();

        $this->actingAs($user)->get(route('vouchers.show', $bare))->assertOk();
        $this->actingAs($user)->get(route('vouchers.edit', $bare))->assertOk();

        $this->actingAs($user)->get(route('vouchers.show', $full))->assertOk();
        $this->actingAs($user)->get(route('vouchers.edit', $full))->assertOk();
    }

    public function test_store_with_entries_and_attachments(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $project = Project::first();
        $category = ProjectCategory::first();

        if (! $category || ! $project) {
            $this->markTestSkipped('Need at least one Project and ProjectCategory in DB to test against.');
        }

        Storage::fake('local');

        $payload = [
            'voucher_no'      => 'AUDIT-HTTP-001',
            'voucher_date'    => now()->format('Y-m-d'),
            'payee_name'      => 'HTTP Audit Payee',
            'amount_payable'  => 1500,
            'mode_of_payment' => 'cash',
            'payment_status'  => 'unpaid',
            'entries' => [
                ['category_id' => $category->id, 'entry_type' => 'debit', 'amount' => 1500, 'project_id' => $project->id, 'description' => 'Audit debit'],
                ['category_id' => $category->id, 'entry_type' => 'credit', 'amount' => 1500, 'project_id' => $project->id, 'description' => 'Audit credit'],
            ],
            'attachments' => [
                UploadedFile::fake()->create('invoice.pdf', 500, 'application/pdf'),
            ],
        ];

        $response = $this->actingAs($user)->post(route('vouchers.store'), $payload);

        $voucher = Voucher::where('voucher_no', 'AUDIT-HTTP-001')->first();

        $this->assertNotNull($voucher, 'Voucher was not created — store() failed silently or redirected with errors: ' . json_encode(session('errors')?->all()));
        $response->assertRedirect(route('vouchers.show', $voucher));

        $this->assertEquals(2, $voucher->entries()->count());
        $this->assertEquals(1, $voucher->attachments()->count());

        $attachment = $voucher->attachments()->first();
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->path), 'Attachment file was not actually written to disk.');

        // Download
        $download = $this->actingAs($user)->get(route('vouchers.attachments.download', $attachment));
        $download->assertOk();

        // Update — replace entries, add a second attachment
        $updatePayload = $payload;
        $updatePayload['entries'] = [
            ['category_id' => $category->id, 'entry_type' => 'debit', 'amount' => 2000, 'project_id' => $project->id, 'description' => 'Updated debit'],
            ['category_id' => $category->id, 'entry_type' => 'credit', 'amount' => 2000, 'project_id' => $project->id, 'description' => 'Updated credit'],
        ];
        $updatePayload['amount_payable'] = 2000;
        $updatePayload['attachments'] = [UploadedFile::fake()->create('second.pdf', 200, 'application/pdf')];

        $updateResponse = $this->actingAs($user)->put(route('vouchers.update', $voucher), $updatePayload);
        $voucher->refresh();

        $updateResponse->assertRedirect(route('vouchers.show', $voucher));
        $this->assertEquals(2, $voucher->entries()->count(), 'Entries should be replaced, not appended.');
        $this->assertEquals(2, $voucher->attachments()->count(), 'New attachment should be added on top of existing one.');

        // Destroy — should clean up attachments + entries + project rows
        $attachmentPath = $voucher->attachments()->first()->path;
        $destroyResponse = $this->actingAs($user)->delete(route('vouchers.destroy', $voucher));
        $destroyResponse->assertRedirect(route('vouchers.index'));

        $this->assertNull(Voucher::find($voucher->id));
        $this->assertEquals(0, VoucherAttachment::where('voucher_id', $voucher->id)->count());
    }

    public function test_store_rejects_oversized_attachment(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = ProjectCategory::first();

        if (! $category) {
            $this->markTestSkipped('No ProjectCategory exists in DB to test against.');
        }

        Storage::fake('local');

        $payload = [
            'voucher_no'      => 'AUDIT-HTTP-002',
            'voucher_date'    => now()->format('Y-m-d'),
            'payee_name'      => 'Oversize Payee',
            'amount_payable'  => 100,
            'mode_of_payment' => 'cash',
            'payment_status'  => 'unpaid',
            'attachments'     => [UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf')], // 11MB > 10MB limit
        ];

        $response = $this->actingAs($user)->post(route('vouchers.store'), $payload);

        $response->assertSessionHasErrors('attachments.0');
        $this->assertNull(Voucher::where('voucher_no', 'AUDIT-HTTP-002')->first());
    }

    public function test_check_voucher_amount_due_is_cash_in_bank_not_total_debit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $materials = ProjectCategory::create(['name' => 'Materials']);
        $directMaterials = ProjectCategory::create(['name' => 'Direct Materials', 'parent_id' => $materials->id]);
        $inputVat = ProjectCategory::create(['name' => 'Input VAT - Materials', 'parent_id' => $materials->id]);
        $wht = ProjectCategory::create(['name' => 'WHT - Materials', 'parent_id' => $materials->id]);
        $cashInBank = ProjectCategory::create(['name' => 'Cash in Bank']);

        $voucher = Voucher::create([
            'voucher_no' => 'AUDIT-CASH-001',
            'voucher_date' => now(),
            'payee_name' => 'CISA Marketing Company, Inc.',
            'amount_payable' => 2735.36,
            'status' => 'unpaid',
            'mode_of_payment' => 'cash',
        ]);
        $voucher->entries()->create(['entry_type' => 'debit', 'amount' => 2464.29, 'category_id' => $directMaterials->id]);
        $voucher->entries()->create(['entry_type' => 'debit', 'amount' => 295.71, 'category_id' => $inputVat->id]);
        $voucher->entries()->create(['entry_type' => 'credit', 'amount' => 24.64, 'category_id' => $wht->id]);
        $voucher->entries()->create(['entry_type' => 'credit', 'amount' => 2735.36, 'category_id' => $cashInBank->id]);

        $html = $this->actingAs($user)->get(route('vouchers.show', $voucher))->assertOk()->getContent();

        $this->assertStringContainsString('Amount Due:</td>', $html);
        $this->assertMatchesRegularExpression(
            '/Amount Due:<\/td>\s*<td[^>]*>₱2,735\.36<\/td>/',
            $html,
            'Amount Due should reflect the Cash in Bank credit line (2,735.36), not the total debit (2,760.00).'
        );
    }

    public function test_update_rejects_unbalanced_entries_without_partial_save(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $project = Project::first();
        $category = ProjectCategory::first();

        if (! $category || ! $project) {
            $this->markTestSkipped('Need at least one Project and ProjectCategory in DB to test against.');
        }

        $voucher = Voucher::create([
            'voucher_no' => 'AUDIT-HTTP-004',
            'voucher_date' => now(),
            'payee_name' => 'Original Payee',
            'amount_payable' => 1000,
            'status' => 'unpaid',
            'mode_of_payment' => 'cash',
        ]);

        $payload = [
            'voucher_no'      => 'AUDIT-HTTP-004',
            'voucher_date'    => now()->format('Y-m-d'),
            'payee_name'      => 'Tampered Payee',
            'amount_payable'  => 1000,
            'mode_of_payment' => 'cash',
            'payment_status'  => 'unpaid',
            'entries' => [
                ['category_id' => $category->id, 'entry_type' => 'debit', 'amount' => 1000],
                ['category_id' => $category->id, 'entry_type' => 'credit', 'amount' => 700],
            ],
        ];

        $response = $this->actingAs($user)->put(route('vouchers.update', $voucher), $payload);

        $response->assertSessionHasErrors('entries');

        $voucher->refresh();
        $this->assertEquals('Original Payee', $voucher->payee_name, 'Voucher fields must not be persisted when entry validation fails.');
        $this->assertEquals(0, $voucher->entries()->count(), 'No entries should be created when validation fails.');
    }

    public function test_store_rejects_unbalanced_entries(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = ProjectCategory::first();

        if (! $category) {
            $this->markTestSkipped('No ProjectCategory exists in DB to test against.');
        }

        $payload = [
            'voucher_no'      => 'AUDIT-HTTP-003',
            'voucher_date'    => now()->format('Y-m-d'),
            'payee_name'      => 'Unbalanced Payee',
            'amount_payable'  => 500,
            'mode_of_payment' => 'cash',
            'payment_status'  => 'unpaid',
            'entries' => [
                ['category_id' => $category->id, 'entry_type' => 'debit', 'amount' => 500],
                ['category_id' => $category->id, 'entry_type' => 'credit', 'amount' => 300],
            ],
        ];

        $response = $this->actingAs($user)->post(route('vouchers.store'), $payload);

        $response->assertSessionHasErrors('entries');
        $this->assertNull(Voucher::where('voucher_no', 'AUDIT-HTTP-003')->first());
    }
}
