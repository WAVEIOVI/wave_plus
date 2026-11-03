<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use App\Modules\BusinessPartners\Services\BusinessPartnerEmailSearchService;
use Illuminate\Support\Collection;

class RecipientSearchService
{
    public function __construct(
        private readonly BusinessPartnerEmailSearchService $bpEmailSearchService,
        private readonly EmployeeEmailSearchService $employeeEmailSearchService,
    ) {}

    /**
     * Unified recipient search across Business Partners, Employees, and Manual/Custom entry.
     *
     * @param  string|null  $type  Optional entity type filter ('all', 'business_partner', 'client', 'supplier', 'organisation', 'service_provider', 'employee', 'custom', 'manual')
     * @param  string|null  $source  Optional email source filter ('all', 'general', 'contact', 'address', 'employee', 'manual')
     * @return Collection<int, array{
     *   entity_type: string,
     *   entity_id: string|null,
     *   entity_display_name: string,
     *   email: string,
     *   source: string,
     *   contact_id: string|null,
     *   contact_name: string|null,
     *   job_title: string|null,
     *   department: string|null,
     * }>
     */
    public function search(User $user, string $query, ?string $type = null, ?string $source = null, int $limit = 50): Collection
    {
        $searchTerm = trim($query);
        if (mb_strlen($searchTerm) === 1) {
            return collect();
        }

        $results = collect();
        $normalizedType = $type ? mb_strtolower(trim($type)) : 'all';

        $categoryMap = [
            'clients' => 'client',
            'client' => 'client',
            'suppliers' => 'supplier',
            'supplier' => 'supplier',
            'organisations' => 'organisation',
            'organizations' => 'organisation',
            'organisation' => 'organisation',
            'organization' => 'organisation',
            'service_providers' => 'service_provider',
            'service-providers' => 'service_provider',
            'service_provider' => 'service_provider',
            'employees' => 'employee',
            'employee' => 'employee',
            'users' => 'employee',
            'user' => 'employee',
            'hr' => 'employee',
            'others' => 'manual',
            'manual' => 'manual',
            'custom' => 'manual',
            'bp' => 'business_partner',
            'business_partners' => 'business_partner',
            'business_partner' => 'business_partner',
            'all' => 'all',
        ];

        $canonicalType = $categoryMap[$normalizedType] ?? $normalizedType;

        // 1. Business Partners Search
        $shouldSearchBp = in_array($canonicalType, ['all', 'business_partner', 'client', 'supplier', 'organisation', 'service_provider'], true);

        if ($shouldSearchBp) {
            $bpResults = $this->bpEmailSearchService->search($user, $searchTerm, 15);

            if (in_array($canonicalType, ['client', 'supplier', 'organisation', 'service_provider'], true)) {
                $bpResults = $bpResults->where('entity_type', $canonicalType);
            }

            $results = $results->concat($bpResults);
        }

        // 2. Employees & Users Search
        $shouldSearchEmployees = in_array($canonicalType, ['all', 'employee'], true);

        if ($shouldSearchEmployees) {
            $employeeResults = $this->employeeEmailSearchService->search($user, $searchTerm, 20);
            $results = $results->concat($employeeResults);
        }

        // 3. Manual / Custom Email Entry
        // If the query is itself a valid email, offer it as a manual entry candidate if not already present
        if (in_array($canonicalType, ['all', 'manual'], true) && filter_var($searchTerm, FILTER_VALIDATE_EMAIL)) {
            $emailLower = mb_strtolower($searchTerm);
            $alreadyIncluded = $results->contains(fn ($item) => mb_strtolower($item['email']) === $emailLower);

            if (! $alreadyIncluded) {
                $results->push([
                    'entity_type' => 'custom',
                    'entity_id' => null,
                    'entity_display_name' => $searchTerm,
                    'email' => $searchTerm,
                    'source' => 'manual',
                    'contact_id' => null,
                    'contact_name' => null,
                    'job_title' => null,
                    'department' => null,
                    'name' => $searchTerm,
                    'type' => 'Custom',
                ]);
            }
        }

        // Filter by source if specified (e.g., 'general', 'contact', 'address', 'employee', 'manual')
        if ($source && $source !== 'all') {
            $normalizedSource = mb_strtolower(trim($source));
            $results = $results->where('source', $normalizedSource);
        }

        // 4. Validate all emails and deduplicate strictly by entity + source + email
        // Preserves different entities (owners) even if they share the same email address!
        return $results
            ->filter(fn ($item) => filter_var($item['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->unique(fn ($item) => ($item['entity_type'] ?? '').':'.($item['entity_id'] ?? '').':'.mb_strtolower($item['email']).':'.($item['source'] ?? ''))
            ->map(function ($item) {
                $item['id'] = ($item['entity_type'] ?? 'rec').':'.($item['entity_id'] ?? '').':'.($item['contact_id'] ?? '').':'.$item['email'].':'.($item['source'] ?? '');

                return $item;
            })
            ->take($limit)
            ->values();
    }

    /**
     * Search recipients grouped by entity (Business Partners with their contacts & addresses, or Employees).
     *
     * @param  array<string>  $types
     * @param  array<string>|null  $departments
     */
    public function searchGrouped(
        User $user,
        string $query = '',
        array $types = [],
        ?array $departments = null,
        int $limit = 50
    ): Collection {
        $isEmployee = in_array('employee', $types, true) || in_array('employees', $types, true);

        if ($isEmployee) {
            return $this->employeeEmailSearchService->searchEmployees($user, $query, $limit, $departments);
        }

        return $this->bpEmailSearchService->searchGrouped($user, $query, $types, $limit);
    }
}
