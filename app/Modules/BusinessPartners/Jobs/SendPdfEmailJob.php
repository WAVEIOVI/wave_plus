<?php

namespace App\Modules\BusinessPartners\Jobs;

use App\Modules\BusinessPartners\Mail\BusinessPartnerDocumentMail;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Shared\Services\DocumentRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Browsershot\Browsershot;

class SendPdfEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    public string $partnerType; // 'client', 'supplier', 'service_provider', 'organisation'

    public string $context;     // 'list' or 'single'

    public ?string $recordId;

    public array $emailTo;

    public ?array $emailCc;

    public string $subject;

    public string $messageText;

    /**
     * Partner-type configuration map.
     */
    private const PARTNER_CONFIG = [
        'client' => [
            'model' => Client::class,
            'listView' => 'pdf.clients.clients-list',
            'singleView' => 'pdf.clients.client-record',
            'listVar' => 'clients',
            'singleVar' => 'client',
            'idField' => 'client_id',
            'listFile' => 'clients-list.pdf',
        ],
        'supplier' => [
            'model' => Supplier::class,
            'listView' => 'pdf.suppliers.suppliers-list',
            'singleView' => 'pdf.suppliers.supplier-record',
            'listVar' => 'suppliers',
            'singleVar' => 'supplier',
            'idField' => 'supplier_id',
            'listFile' => 'suppliers-list.pdf',
        ],
        'service_provider' => [
            'model' => ServiceProvider::class,
            'listView' => 'pdf.service-providers.service-providers-list',
            'singleView' => 'pdf.service-providers.service-provider-record',
            'listVar' => 'serviceProviders',
            'singleVar' => 'serviceProvider',
            'idField' => 'service_provider_id',
            'listFile' => 'service-providers-list.pdf',
        ],
        'organisation' => [
            'model' => Organisation::class,
            'listView' => 'pdf.organisations.organisations-list',
            'singleView' => 'pdf.organisations.organisation-record',
            'listVar' => 'organisations',
            'singleVar' => 'organisation',
            'idField' => 'organisation_id',
            'listFile' => 'organisations-list.pdf',
        ],
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(string $partnerType, string $context, ?string $recordId, array $emailData)
    {
        $this->partnerType = $partnerType;
        $this->context = $context;
        $this->recordId = $recordId;
        $this->emailTo = $emailData['emailTo'];
        $this->emailCc = $emailData['emailCc'] ?? null;
        $this->subject = $emailData['subject'];
        $this->messageText = $emailData['message'];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->context === 'list') {
                $this->handleListExport();
            } elseif ($this->context === 'single' && $this->recordId) {
                $this->handleSingleExport($this->recordId);
            }
        } catch (\Exception $e) {
            Log::error("SendPdfEmailJob failed for {$this->partnerType}/{$this->context}", [
                'partner_type' => $this->partnerType,
                'record_id' => $this->recordId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw so Laravel retries up to $tries
        }
    }

    protected function handleListExport(): void
    {
        $config = self::PARTNER_CONFIG[$this->partnerType];
        $model = $config['model'];

        $records = $model::with('accountManager')
            ->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get();

        $html = view($config['listView'], [$config['listVar'] => $records])->render();

        $pdfContent = $this->makeBrowsershot($html, 'landscape')->pdf();

        $this->sendEmail($pdfContent, $config['listFile']);
    }

    protected function handleSingleExport(string $id): void
    {
        $config = self::PARTNER_CONFIG[$this->partnerType];
        $model = $config['model'];

        $record = $model::with([
            'accountManager',
            'addresses',
            'contacts',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->findOrFail($id);

        $html = view($config['singleView'], [$config['singleVar'] => $record])->render();

        $pdfContent = $this->makeBrowsershot($html, 'portrait')->pdf();

        $filename = str_replace('_', '-', $this->partnerType).'-'.$record->{$config['idField']}.'.pdf';

        $this->sendEmail($pdfContent, $filename);
    }

    protected function sendEmail(string $pdfContent, string $filename): void
    {
        $mail = Mail::to($this->emailTo);

        if (! empty($this->emailCc)) {
            $mail->cc($this->emailCc);
        }

        $mail->send(
            new BusinessPartnerDocumentMail(
                $this->subject,
                $this->messageText,
                $pdfContent,
                $filename
            )
        );
    }

    /**
     * Create a pre-configured Browsershot instance via the shared DocumentRenderService.
     */
    private function makeBrowsershot(string $html, ?string $orientation = null): Browsershot
    {
        return app(DocumentRenderService::class)->makeBrowsershot($html, $orientation);
    }
}
