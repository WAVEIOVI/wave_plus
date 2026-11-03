<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Requests\ExportClientsRequest;
use App\Modules\BusinessPartners\Services\PartnerQueryService;
use App\Modules\Shared\Services\DocumentRenderService;

class ClientPdfController extends Controller
{
    use AuthorizesPartnerRequests;

    public function __construct(
        private readonly DocumentRenderService $documentRender,
        private readonly PartnerQueryService $queryService
    ) {}

    public function exportList(ExportClientsRequest $request)
    {
        $clients = $this->queryService->getSelectedClientsForExport(
            $request->user(),
            $request->validatedIds()
        );

        $html = view('pdf.clients.clients-list', compact('clients'))->render();

        return $this->documentRender->renderPdfResponse(
            $html,
            'clients-list.pdf',
            landscape: true
        );
    }

    public function exportSingle(string $id)
    {
        $client = Client::with([
            'accountManager',
            'addresses',
            'contacts',
            'bankAccounts',
            'vatCertificates',
        ])->findOrFail($id);

        $this->authorize('view', $client);

        $html = view('pdf.clients.client-record', compact('client'))->render();

        return $this->documentRender->renderPdfResponse(
            $html,
            'client-'.$client->client_id.'.pdf',
            landscape: false
        );
    }
}
