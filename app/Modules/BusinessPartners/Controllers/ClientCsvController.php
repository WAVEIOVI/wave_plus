<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Requests\ExportClientsRequest;
use App\Modules\BusinessPartners\Services\PartnerQueryService;
use App\Modules\Shared\Services\DocumentRenderService;
use Carbon\Carbon;

class ClientCsvController extends Controller
{
    use AuthorizesPartnerRequests;

    public function __construct(
        private readonly DocumentRenderService $documentRender,
        private readonly PartnerQueryService $queryService
    ) {}

    /**
     * Stream a CSV export of the selected clients.
     *
     * Route: GET|POST /api/bp/clients/export/csv
     * Authorization: viewAny Client + view each selected Client
     */
    public function exportList(ExportClientsRequest $request)
    {
        $clients = $this->queryService->getSelectedClientsForExport(
            $request->user(),
            $request->validatedIds()
        );

        $headers = [
            'ID Client',
            'Raison Sociale',
            'Nom Commercial',
            'Secteur',
            'Catégorie',
            'Statut',
            'Email',
            'Téléphone',
            'Responsable',
            'Partenaire depuis',
            'Créé le',
        ];

        $rows = $clients->map(fn (Client $client) => [
            $client->client_id,
            $client->getTranslation('legal_name', app()->getLocale(), false) ?: $client->getRawOriginal('legal_name'),
            $client->getTranslation('commercial_name', app()->getLocale(), false) ?: $client->getRawOriginal('commercial_name') ?: '',
            $client->industry ?? '',
            $client->client_category ? str_replace('_', ' ', $client->client_category) : '',
            $client->status ?? '',
            $client->primary_email ?? '',
            $client->primary_phone ?? '',
            $client->accountManager?->full_name ?? '',
            $client->partner_since instanceof \Carbon\Carbon
                ? $client->partner_since->format('Y-m-d')
                : ($client->partner_since ? Carbon::parse($client->partner_since)->format('Y-m-d') : ''),
            $client->created_at instanceof \Carbon\Carbon
                ? $client->created_at->format('Y-m-d')
                : '',
        ]);

        $filename = 'clients-'.now()->format('Y-m-d').'.csv';

        return $this->documentRender->streamCsvResponse($filename, $headers, $rows);
    }
}
