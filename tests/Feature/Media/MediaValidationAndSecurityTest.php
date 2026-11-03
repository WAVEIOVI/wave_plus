<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Certification;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeCertification;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleDocument;
use App\Modules\Procurement\Models\GoodsReceiptNote;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Shared\Helpers\MediaFormatter;
use App\Modules\Shared\Services\BrandingSyncService;
use App\Modules\Shared\Services\MediaValidationService;
use App\Modules\Shared\Services\SvgSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaValidationAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        Role::findOrCreate('Super Admin', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_svg_sanitizer_removes_dangerous_scripts_and_handlers(): void
    {
        $maliciousSvg = <<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 100">
    <script>alert('xss');</script>
    <rect width="100" height="100" fill="red" onload="alert('owned')" onclick="bad()" />
    <foreignObject width="100" height="100">
        <iframe src="https://evil.com"></iframe>
    </foreignObject>
    <a xlink:href="javascript:alert(1)">
        <text x="10" y="20">Click me</text>
    </a>
</svg>
SVG;

        $sanitized = SvgSanitizer::sanitize($maliciousSvg);

        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('alert(', $sanitized);
        $this->assertStringNotContainsString('onload', $sanitized);
        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertStringNotContainsString('<foreignObject', $sanitized);
        $this->assertStringNotContainsString('<iframe', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);

        // Valid SVG structure is preserved
        $this->assertStringContainsString('<svg', $sanitized);
        $this->assertStringContainsString('<rect', $sanitized);
        $this->assertStringContainsString('fill="red"', $sanitized);
    }

    public function test_svg_sanitizer_blocks_xxe_entity_injection(): void
    {
        $xxeSvg = <<<'SVG'
<?xml version="1.0"?>
<!DOCTYPE svg [
    <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<svg xmlns="http://www.w3.org/2000/svg">
    <text>&xxe;</text>
</svg>
SVG;

        $this->expectException(\InvalidArgumentException::class);
        SvgSanitizer::sanitize($xxeSvg);
    }

    public function test_media_validation_rejects_extension_spoofing(): void
    {
        Sanctum::actingAs($this->admin);

        // PHP file disguised as a JPEG
        $spoofedFile = UploadedFile::fake()->createWithContent('exploit.jpg', '<?php echo "evil"; ?>');

        $this->expectException(ValidationException::class);
        MediaValidationService::validateAndProcess($spoofedFile, MediaValidationService::CATEGORY_IMAGE, 'file');
    }

    public function test_media_validation_rejects_unsupported_extensions(): void
    {
        Sanctum::actingAs($this->admin);

        $exeFile = UploadedFile::fake()->create('malware.exe', 100);

        $this->expectException(ValidationException::class);
        MediaValidationService::validateAndProcess($exeFile, MediaValidationService::CATEGORY_DOCUMENT, 'file');
    }

    public function test_media_validation_accepts_valid_image_and_extracts_dimensions(): void
    {
        Sanctum::actingAs($this->admin);

        $validImage = UploadedFile::fake()->image('banner.png', 800, 600);

        $meta = MediaValidationService::validateAndProcess($validImage, MediaValidationService::CATEGORY_IMAGE, 'file');

        $this->assertSame('banner.png', $meta['original_name']);
        $this->assertSame(800, $meta['width']);
        $this->assertSame(600, $meta['height']);
        $this->assertSame($this->admin->id, $meta['uploaded_by_id']);
        $this->assertSame($this->admin->name, $meta['uploaded_by']);
    }

    public function test_media_validation_enforces_size_limit(): void
    {
        Sanctum::actingAs($this->admin);

        // 11MB file exceeds 10MB image limit
        $oversizedImage = UploadedFile::fake()->create('huge.jpg', 11 * 1024, 'image/jpeg');

        $this->expectException(ValidationException::class);
        MediaValidationService::validateAndProcess($oversizedImage, MediaValidationService::CATEGORY_IMAGE, 'file');
    }

    public function test_fleet_vehicle_document_upload_storage_and_download_flow(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'fleet_code' => 'TEST-001',
            'plate_number' => '999-TN-9999',
            'label' => 'Test Vehicle',
            'make' => 'Peugeot',
            'model' => 'Partner',
            'year' => 2024,
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
        ]);

        $pdfFile = UploadedFile::fake()->createWithContent(
            'insurance_policy.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        $response = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/documents", [
            'document_type' => 'insurance',
            'title' => 'Comprehensive Policy 2026',
            'document_number' => 'POL-2026-99',
            'issuer' => 'Star Assurances',
            'expiry_date' => '2027-12-31',
            'status' => 'active',
            'file' => $pdfFile,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_type', 'insurance')
            ->assertJsonPath('data.title', 'Comprehensive Policy 2026')
            ->assertJsonPath('data.has_file', true)
            ->assertJsonPath('data.file_name', 'insurance_policy.pdf');

        $documentId = $response->json('data.id');
        $doc = VehicleDocument::findOrFail($documentId);

        // Verify attached to Spatie MediaLibrary on local private disk
        $media = $doc->getFirstMedia('document');
        $this->assertNotNull($media);
        $this->assertSame('local', $media->disk);
        $this->assertSame('insurance_policy.pdf', $media->file_name);

        // Test authorized download
        $downloadResponse = $this->get("/api/hr/fleet/documents/{$documentId}/download");
        $downloadResponse->assertOk();

        // Test unauthorized access returns 403
        Sanctum::actingAs($this->unauthorizedUser);
        $this->getJson("/api/hr/fleet/documents/{$documentId}/download")
            ->assertForbidden();
    }

    public function test_branding_sync_service_clears_media_on_initials(): void
    {
        Sanctum::actingAs($this->admin);

        // Create partner to test branding sync
        $partner = \App\Modules\BusinessPartners\Models\Client::create([
            'legal_name' => 'Acme Corporation',
            'commercial_name' => 'Acme',
            'tax_id' => '1234567/A/M/000',
        ]);

        // Upload logo
        $logoFile = UploadedFile::fake()->image('acme_logo.png', 200, 200);
        $uploadReq = \Illuminate\Http\Request::create('/api/bp/clients/'.$partner->id.'/logo', 'POST', [
            'logo_source' => 'upload',
        ], [], ['logo' => $logoFile]);

        BrandingSyncService::syncLogo($partner, $uploadReq);

        $this->assertTrue($partner->hasMedia('logo'));

        // Switch to initials
        $initialsReq = \Illuminate\Http\Request::create('/api/bp/clients/'.$partner->id.'/logo', 'POST', [
            'logo_source' => 'initials',
        ]);

        BrandingSyncService::syncLogo($partner, $initialsReq);

        $partner->refresh();
        $this->assertFalse($partner->hasMedia('logo'));
        $this->assertSame('initials', $partner->logo_source);
    }

    public function test_brand_logo_resolve_endpoint_supports_force_refresh(): void
    {
        Sanctum::actingAs($this->admin);

        config([
            'services.brandfetch.api_key' => 'test-key',
            'services.brandfetch.client_id' => 'test-client',
        ]);

        Http::fake([
            'https://api.brandfetch.io/v2/brands/example.com' => Http::response([
                'name' => 'Example Inc',
                'domain' => 'example.com',
                'logos' => [
                    [
                        'type' => 'icon',
                        'formats' => [
                            ['src' => 'https://cdn.brandfetch.io/idExample/w/400/h/400?c=1axTempToken', 'format' => 'png'],
                        ],
                    ],
                ],
            ]),
        ]);

        // Normal resolve
        $res1 = $this->getJson('/api/brand-logo/resolve?domain=example.com');
        $res1->assertOk()
            ->assertJsonPath('data.name', 'Example Inc')
            ->assertJsonPath('data.url', 'https://cdn.brandfetch.io/idExample/w/400/h/400?c=test-client');

        // Force refresh cache bypass
        $res2 = $this->getJson('/api/brand-logo/resolve?domain=example.com&force=1');
        $res2->assertOk()
            ->assertJsonPath('data.name', 'Example Inc');
    }

    public function test_media_validation_accepts_design_files_and_rejects_oversized(): void
    {
        Sanctum::actingAs($this->admin);

        // Valid design file (PDF blueprint / design)
        $validDesign = UploadedFile::fake()->createWithContent(
            'embroidery_spec.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );
        $meta = MediaValidationService::validateAndProcess($validDesign, MediaValidationService::CATEGORY_DESIGN, 'file');
        $this->assertSame('embroidery_spec.pdf', $meta['original_name']);

        // Oversized design (> 50MB)
        $oversizedDesign = UploadedFile::fake()->create('huge_cad.ai', 51 * 1024);
        $this->expectException(ValidationException::class);
        MediaValidationService::validateAndProcess($oversizedDesign, MediaValidationService::CATEGORY_DESIGN, 'file');
    }

    public function test_media_validation_accepts_video_files_and_enforces_video_limit(): void
    {
        Sanctum::actingAs($this->admin);

        // Valid video with MP4 binary signature
        $mp4Content = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";
        $validVideo = UploadedFile::fake()->createWithContent('inspection.mp4', $mp4Content);
        $meta = MediaValidationService::validateAndProcess($validVideo, MediaValidationService::CATEGORY_VIDEO, 'file');
        $this->assertSame('inspection.mp4', $meta['original_name']);

        // Oversized video (> 100MB)
        $oversizedVideo = UploadedFile::fake()->create('huge_raw.mov', 101 * 1024, 'video/quicktime');
        $this->expectException(ValidationException::class);
        MediaValidationService::validateAndProcess($oversizedVideo, MediaValidationService::CATEGORY_VIDEO, 'file');
    }

    public function test_media_validation_accepts_csv_in_document_category(): void
    {
        Sanctum::actingAs($this->admin);

        $csvFile = UploadedFile::fake()->createWithContent('import.csv', "col1,col2\nval1,val2\n");
        $meta = MediaValidationService::validateAndProcess($csvFile, MediaValidationService::CATEGORY_DOCUMENT, 'file');
        $this->assertSame('import.csv', $meta['original_name']);
    }

    public function test_disk_for_collection_policy_returns_correct_storage_visibility(): void
    {
        // Public assets
        $this->assertSame('public', MediaValidationService::diskForCollection('photos'));
        $this->assertSame('public', MediaValidationService::diskForCollection('logos'));
        $this->assertSame('public', MediaValidationService::diskForCollection('avatar'));
        $this->assertSame('public', MediaValidationService::diskForCollection('images'));
        $this->assertFalse(MediaValidationService::isPrivateCollection('photos'));

        // Public catalog specifications and documents
        $this->assertSame('public', MediaValidationService::diskForCollection('documents', 'App\Modules\Catalog\Models\CatalogItem'));
        $this->assertSame('public', MediaValidationService::diskForCollection('certificates', 'App\Modules\Catalog\Models\CatalogItem'));
        $this->assertFalse(MediaValidationService::isPrivateCollection('documents', 'App\Modules\Catalog\Models\CatalogItem'));

        // Private sensitive assets (including singular document and plural variations)
        $this->assertSame('local', MediaValidationService::diskForCollection('document'));
        $this->assertSame('local', MediaValidationService::diskForCollection('documents'));
        $this->assertSame('local', MediaValidationService::diskForCollection('design_files'));
        $this->assertSame('local', MediaValidationService::diskForCollection('resume'));
        $this->assertSame('local', MediaValidationService::diskForCollection('resumes'));
        $this->assertSame('local', MediaValidationService::diskForCollection('delivery_note'));
        $this->assertSame('local', MediaValidationService::diskForCollection('site_plans'));
        $this->assertSame('local', MediaValidationService::diskForCollection('certificate_files'));
        $this->assertSame('local', MediaValidationService::diskForCollection('contract_document'));

        $this->assertTrue(MediaValidationService::isPrivateCollection('document'));
        $this->assertTrue(MediaValidationService::isPrivateCollection('site_plans'));
        $this->assertTrue(MediaValidationService::isPrivateCollection('delivery_note'));
    }

    public function test_private_media_migration_safety_verification_and_rollback(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        // Create sample media records in DB
        $ulid1 = (string) \Illuminate\Support\Str::ulid();
        $ulid2 = (string) \Illuminate\Support\Str::ulid();
        $ulidCatalog = (string) \Illuminate\Support\Str::ulid();

        // 1. Singular document collection on public disk (should migrate)
        \Illuminate\Support\Facades\DB::table('media')->insert([
            'id' => $ulid1,
            'model_type' => 'App\Modules\BusinessPartners\Models\BpDocument',
            'model_id' => (string) \Illuminate\Support\Str::ulid(),
            'collection_name' => 'document',
            'name' => 'agreement',
            'file_name' => 'agreement.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 1024,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '{"thumb":true}',
            'responsive_images' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put("{$ulid1}/agreement.pdf", 'fake-pdf-content-1024');
        Storage::disk('public')->put("{$ulid1}/conversions/thumb.png", 'fake-thumb-content');

        // 2. Design files collection on public disk (should migrate)
        \Illuminate\Support\Facades\DB::table('media')->insert([
            'id' => $ulid2,
            'model_type' => 'App\Modules\ClientAssets\Models\WorkwearModel',
            'model_id' => (string) \Illuminate\Support\Str::ulid(),
            'collection_name' => 'design_files',
            'name' => 'vector_logo',
            'file_name' => 'vector_logo.ai',
            'mime_type' => 'application/postscript',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 2048,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put("{$ulid2}/vector_logo.ai", 'fake-ai-content-2048');

        // 3. CatalogItem documents on public disk (must NOT migrate, remains public)
        \Illuminate\Support\Facades\DB::table('media')->insert([
            'id' => $ulidCatalog,
            'model_type' => 'App\Modules\Catalog\Models\CatalogItem',
            'model_id' => (string) \Illuminate\Support\Str::ulid(),
            'collection_name' => 'documents',
            'name' => 'spec_sheet',
            'file_name' => 'spec_sheet.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 512,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put("{$ulidCatalog}/spec_sheet.pdf", 'fake-spec-content');

        $migration = require database_path('migrations/2026_10_20_000002_harden_private_media_storage.php');

        // --- TEST UP() MIGRATION ---
        $migration->up();

        // Verify record 1 (singular document)
        $m1 = \Illuminate\Support\Facades\DB::table('media')->where('id', $ulid1)->first();
        $this->assertSame('local', $m1->disk);
        $this->assertSame('local', $m1->conversions_disk);
        $this->assertTrue(Storage::disk('local')->exists("{$ulid1}/agreement.pdf"));
        $this->assertFalse(Storage::disk('public')->exists("{$ulid1}/agreement.pdf"));
        $this->assertTrue(Storage::disk('local')->exists("{$ulid1}/conversions/thumb.png"));
        $this->assertFalse(Storage::disk('public')->exists("{$ulid1}/conversions/thumb.png"));

        // Verify record 2 (design_files)
        $m2 = \Illuminate\Support\Facades\DB::table('media')->where('id', $ulid2)->first();
        $this->assertSame('local', $m2->disk);
        $this->assertTrue(Storage::disk('local')->exists("{$ulid2}/vector_logo.ai"));
        $this->assertFalse(Storage::disk('public')->exists("{$ulid2}/vector_logo.ai"));

        // Verify record 3 (CatalogItem spec) remains public
        $mCat = \Illuminate\Support\Facades\DB::table('media')->where('id', $ulidCatalog)->first();
        $this->assertSame('public', $mCat->disk);
        $this->assertTrue(Storage::disk('public')->exists("{$ulidCatalog}/spec_sheet.pdf"));

        // --- TEST DOWN() ROLLBACK ---
        $migration->down();

        $m1Down = \Illuminate\Support\Facades\DB::table('media')->where('id', $ulid1)->first();
        $this->assertSame('public', $m1Down->disk);
        $this->assertSame('public', $m1Down->conversions_disk);
        $this->assertTrue(Storage::disk('public')->exists("{$ulid1}/agreement.pdf"));
        $this->assertFalse(Storage::disk('local')->exists("{$ulid1}/agreement.pdf"));
        $this->assertTrue(Storage::disk('public')->exists("{$ulid1}/conversions/thumb.png"));
        $this->assertFalse(Storage::disk('local')->exists("{$ulid1}/conversions/thumb.png"));

        $m2Down = \Illuminate\Support\Facades\DB::table('media')->where('id', $ulid2)->first();
        $this->assertSame('public', $m2Down->disk);
        $this->assertTrue(Storage::disk('public')->exists("{$ulid2}/vector_logo.ai"));
        $this->assertFalse(Storage::disk('local')->exists("{$ulid2}/vector_logo.ai"));
    }

    public function test_workwear_model_design_file_upload_and_authorized_download(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'legal_name' => 'Industrial Safety S.A.',
            'tax_id' => '9988776/B/N/000',
        ]);

        $model = WorkwearModel::create([
            'client_id' => $client->id,
            'name' => 'High-Vis Winter Parka',
            'status' => 'draft',
        ]);

        $designPdf = UploadedFile::fake()->createWithContent(
            'parka_blueprint.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        $uploadResponse = $this->postJson("/api/client-assets/workwear-models/{$model->id}/media", [
            'file' => $designPdf,
            'collection' => 'design_files',
            'title' => 'Technical Blueprint 2026',
        ]);

        $uploadResponse->assertCreated()
            ->assertJsonPath('data.name', 'High-Vis Winter Parka');

        $model->refresh();
        $media = $model->getFirstMedia('design_files');
        $this->assertNotNull($media);
        $this->assertSame('local', $media->disk);
        $this->assertSame('parka_blueprint.pdf', $media->file_name);

        // Download endpoint check
        $downloadResponse = $this->get("/api/client-assets/workwear-models/{$model->id}/media/{$media->id}/download");
        $downloadResponse->assertOk();

        // Unauthorized user check
        Sanctum::actingAs($this->unauthorizedUser);
        $this->getJson("/api/client-assets/workwear-models/{$model->id}/media/{$media->id}/download")
            ->assertForbidden();
    }

    public function test_candidate_resume_upload_and_authorized_download(): void
    {
        Sanctum::actingAs($this->admin);

        $resumePdf = UploadedFile::fake()->createWithContent(
            'candidate_cv.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        $response = $this->postJson('/api/hr/recruitment/candidates', [
            'first_name' => 'Karim',
            'last_name' => 'Trabelsi',
            'email' => 'karim.t@example.com',
            'resume' => $resumePdf,
        ]);

        $response->assertCreated();
        $candidateId = $response->json('data.id') ?? Candidate::where('email', 'karim.t@example.com')->firstOrFail()->id;
        $candidate = Candidate::findOrFail($candidateId);

        $media = $candidate->getFirstMedia('resume');
        $this->assertNotNull($media);
        $this->assertSame('local', $media->disk);

        // Authorized download
        $downloadRes = $this->get("/api/hr/recruitment/candidates/{$candidate->id}/resume/download");
        $downloadRes->assertOk();

        // Unauthorized download
        Sanctum::actingAs($this->unauthorizedUser);
        $this->getJson("/api/hr/recruitment/candidates/{$candidate->id}/resume/download")
            ->assertForbidden();
    }

    public function test_employee_certification_upload_and_authorized_download(): void
    {
        Sanctum::actingAs($this->admin);

        $employee = Employee::create([
            'employee_id' => 'EMP-TEST-99',
            'first_name' => 'Sonia',
            'last_name' => 'Ben Salem',
            'email' => 'sonia.b@example.com',
            'status' => 'active',
            'hire_date' => now()->toDateString(),
        ]);

        $cert = Certification::create([
            'name' => 'First Aid & CPR',
            'code' => 'CERT-FA-01',
            'category' => 'safety',
            'status' => 'active',
        ]);

        $certPdf = UploadedFile::fake()->createWithContent(
            'cpr_cert.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        $response = $this->postJson("/api/hr/employees/{$employee->id}/certifications", [
            'certification_id' => $cert->id,
            'issue_date' => now()->toDateString(),
            'certificate_number' => 'CPR-2026-001',
            'file' => $certPdf,
        ]);

        $response->assertCreated();
        $empCertId = $response->json('data.id');
        $empCert = EmployeeCertification::findOrFail($empCertId);

        $media = $empCert->getFirstMedia('certificate_files');
        $this->assertNotNull($media);
        $this->assertSame('local', $media->disk);

        // Authorized download
        $this->get("/api/hr/employee-certifications/{$empCert->id}/download")
            ->assertOk();

        // Unauthorized download
        Sanctum::actingAs($this->unauthorizedUser);
        $this->getJson("/api/hr/employee-certifications/{$empCert->id}/download")
            ->assertForbidden();
    }

    public function test_goods_receipt_note_delivery_note_upload_and_authorized_download(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::create([
            'legal_name' => 'Safety Supplies Ltd',
            'tax_id' => '5544332/A/P/000',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'creation_path' => 'manual',
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'status' => 'approved',
        ]);

        $grn = GoodsReceiptNote::create([
            'supplier_id' => $supplier->id,
            'po_id' => $po->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateTimeString(),
            'status' => 'draft',
        ]);

        $deliveryNotePdf = UploadedFile::fake()->createWithContent(
            'supplier_dn.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        $grn->addMedia($deliveryNotePdf)
            ->usingFileName('supplier_dn.pdf')
            ->toMediaCollection('delivery_note');

        $media = $grn->fresh()->getFirstMedia('delivery_note');
        $this->assertNotNull($media);
        $this->assertSame('local', $media->disk);

        // Authorized download
        $this->get("/api/procurement/grns/{$grn->id}/download-delivery-note")
            ->assertOk();

        // Unauthorized download
        Sanctum::actingAs($this->unauthorizedUser);
        $this->getJson("/api/procurement/grns/{$grn->id}/download-delivery-note")
            ->assertForbidden();
    }

    public function test_client_site_documents_and_site_plans_stored_on_private_disk(): void
    {
        $client = Client::create([
            'legal_name' => 'Factory Alpha',
            'tax_id' => '1122334/C/M/000',
        ]);

        $site = ClientSite::create([
            'client_id' => $client->id,
            'name' => 'Main Warehouse',
            'code' => 'STE-ALPHA-01',
            'status' => 'active',
        ]);

        $planPdf = UploadedFile::fake()->createWithContent(
            'evacuation_plan.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        $site->addMedia($planPdf)
            ->usingFileName('evacuation_plan.pdf')
            ->toMediaCollection('site_plans');

        $media = $site->fresh()->getFirstMedia('site_plans');
        $this->assertNotNull($media);
        $this->assertSame('local', $media->disk);

        // Verify MediaFormatter marks private and generates download_url
        $formatted = MediaFormatter::format($media);
        $this->assertTrue($formatted['is_private']);
        $this->assertNotNull($formatted['download_url']);
        $this->assertStringContainsString("/api/client-assets/sites/{$site->id}/media/{$media->id}/download", $formatted['download_url']);
        $this->assertSame($formatted['download_url'], $formatted['url']);
    }

    public function test_branding_sync_service_stabilize_provider_url_variations(): void
    {
        config(['services.brandfetch.client_id' => 'my-permanent-client-id']);

        // Case 1: Brandfetch URL with temporary session c= token
        $url1 = 'https://cdn.brandfetch.io/idExample/w/400/h/400?c=1axTempToken';
        $stabilized1 = BrandingSyncService::stabilizeProviderUrl($url1);
        $this->assertSame('https://cdn.brandfetch.io/idExample/w/400/h/400?c=my-permanent-client-id', $stabilized1);

        // Case 2: Brandfetch URL without existing c= parameter
        $url2 = 'https://cdn.brandfetch.io/idExample/w/400/h/400?theme=dark';
        $stabilized2 = BrandingSyncService::stabilizeProviderUrl($url2);
        $this->assertSame('https://cdn.brandfetch.io/idExample/w/400/h/400?theme=dark&c=my-permanent-client-id', $stabilized2);

        // Case 3: Logo.dev URL with token
        $url3 = 'https://img.logo.dev/example.com?token=pk_test_123';
        $stabilized3 = BrandingSyncService::stabilizeProviderUrl($url3);
        $this->assertSame($url3, $stabilized3);
    }

    public function test_media_validation_service_build_custom_properties_standardizes_metadata(): void
    {
        $fileMeta = [
            'original_name' => 'inspection_report_2026.pdf',
            'uploaded_by' => 'Jane Admin',
            'uploaded_by_id' => 42,
            'width' => null,
            'height' => null,
        ];

        $inputMetadata = [
            'title' => 'Quarterly Inspection',
            'category' => 'compliance',
            'reference' => 'INSP-2026-001',
            'notes' => 'Passed full technical inspection without findings.',
            'date' => '2026-10-15',
            'expiry_date' => '2027-10-15',
            'status' => 'active',
            'source' => 'camera_scan',
            'extra_custom_field' => 'custom_val',
        ];

        $properties = MediaValidationService::buildCustomProperties($fileMeta, $inputMetadata);

        $this->assertSame('Quarterly Inspection', $properties['title']);
        $this->assertSame('Quarterly Inspection', $properties['name']);
        $this->assertSame('compliance', $properties['category']);
        $this->assertSame('compliance', $properties['type']);
        $this->assertSame('INSP-2026-001', $properties['reference']);
        $this->assertSame('INSP-2026-001', $properties['reference_number']);
        $this->assertSame('Passed full technical inspection without findings.', $properties['notes']);
        $this->assertSame('Passed full technical inspection without findings.', $properties['description']);
        $this->assertSame('2026-10-15', $properties['date']);
        $this->assertSame('2026-10-15', $properties['issue_date']);
        $this->assertSame('2027-10-15', $properties['expiry_date']);
        $this->assertSame('2027-10-15', $properties['expiration_date']);
        $this->assertSame('active', $properties['status']);
        $this->assertSame('camera_scan', $properties['source']);
        $this->assertSame('custom_val', $properties['extra_custom_field']);
    }

    public function test_media_formatter_surfaces_standard_metadata_properties(): void
    {
        Sanctum::actingAs($this->admin);

        $site = ClientSite::create([
            'client_id' => Client::create(['legal_name' => 'Test Corp', 'status' => 'active'])->id,
            'name' => 'Inspection Depot',
            'code' => 'STE-INSP-01',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create('attachment_doc.pdf', 100, 'application/pdf');

        $customProps = MediaValidationService::buildCustomProperties([
            'original_name' => 'attachment_doc.pdf',
            'uploaded_by' => 'Jane Admin',
            'uploaded_by_id' => 1,
        ], [
            'title' => 'Depot Blueprint Permit',
            'category' => 'site_permit',
            'reference' => 'PERMIT-2026-99',
            'notes' => 'Permit approved by civil protection.',
            'date' => '2026-01-01',
            'expiry_date' => '2028-01-01',
            'status' => 'active',
        ]);

        $media = $site->addMedia($file)
            ->usingFileName('attachment_doc.pdf')
            ->withCustomProperties($customProps)
            ->toMediaCollection('documents');

        $formatted = MediaFormatter::format($media);

        $this->assertSame('Depot Blueprint Permit', $formatted['title']);
        $this->assertSame('site_permit', $formatted['category']);
        $this->assertSame('PERMIT-2026-99', $formatted['reference']);
        $this->assertSame('Permit approved by civil protection.', $formatted['notes']);
        $this->assertSame('2026-01-01', $formatted['date']);
        $this->assertSame('2028-01-01', $formatted['expiry_date']);
        $this->assertSame('active', $formatted['status']);
        $this->assertSame('attachment_doc.pdf', $formatted['file_name']);
        $this->assertNotEmpty($formatted['download_url']);
    }

    public function test_document_category_accepts_camera_captured_photos(): void
    {
        Sanctum::actingAs($this->admin);

        $photoFile = UploadedFile::fake()->image('camera-capture-1700000000.jpg', 1280, 960);

        $meta = MediaValidationService::validateAndProcess(
            $photoFile,
            MediaValidationService::CATEGORY_DOCUMENT,
            'file'
        );

        $this->assertSame('camera-capture-1700000000.jpg', $meta['original_name']);
        $this->assertSame('image/jpeg', $meta['mime_type']);
        $this->assertSame(1280, $meta['width']);
        $this->assertSame(960, $meta['height']);
    }
}
