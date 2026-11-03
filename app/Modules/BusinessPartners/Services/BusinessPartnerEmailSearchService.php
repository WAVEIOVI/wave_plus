<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BusinessPartnerEmailSearchService
{
    /**
     * Search across Business Partner entities (Clients, Suppliers, Organisations, Service Providers),
     * their Contacts, and their Addresses.
     *
     * Respects Spatie permissions and role-based access control per entity type.
     *
     * @return Collection<int, array{
     *   entity_type: string,
     *   entity_id: string,
     *   entity_display_name: string,
     *   email: string,
     *   source: 'general'|'contact'|'address',
     *   contact_name: string|null,
     *   name: string,
     *   type: string,
     * }>
     */
    public function search(User $user, string $query, int $limitPerCategory = 15): Collection
    {
        $searchTerm = trim($query);
        if (mb_strlen($searchTerm) === 1) {
            return collect();
        }

        $results = collect();
        $wildcard = $searchTerm !== '' ? "%{$searchTerm}%" : null;

        // Determine which entity categories the user is authorized to view
        $categories = $this->getAllowedPartnerCategories($user);

        // 1. General emails from partner tables
        foreach ($categories as $cat) {
            $table = $cat['table'];
            $type = $cat['type'];
            $entityKey = $cat['key'];
            $morphClass = $cat['model'];

            $builder = DB::table($table)
                ->select(['id', 'primary_email', 'legal_name', 'commercial_name'])
                ->whereNotNull('primary_email')
                ->where('primary_email', '!=', '')
                ->whereNull('deleted_at');

            if ($wildcard) {
                $builder->where(function ($q) use ($wildcard) {
                    $q->where('primary_email', 'like', $wildcard)
                        ->orWhere('legal_name', 'like', $wildcard)
                        ->orWhere('commercial_name', 'like', $wildcard);
                });
            }

            $rows = $builder->limit($limitPerCategory)->get();

            foreach ($rows as $row) {
                $email = trim((string) $row->primary_email);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $displayName = $this->resolveDisplayName($row->legal_name, $row->commercial_name);

                $results->push([
                    'entity_type' => $entityKey,
                    'entity_id' => (string) $row->id,
                    'entity_display_name' => $displayName,
                    'email' => $email,
                    'source' => 'general',
                    'contact_id' => null,
                    'contact_name' => null,
                    'job_title' => null,
                    'department' => null,
                    // Backward-compatibility keys for legacy callers
                    'name' => $displayName,
                    'type' => $type,
                ]);
            }
        }

        // 2. Contact emails from bp_contacts
        if (! empty($categories)) {
            $allowedMorphModels = array_map(fn ($c) => $c['model'], $categories);
            $morphMap = [
                \App\Modules\BusinessPartners\Models\Client::class => 'client',
                \App\Modules\BusinessPartners\Models\Supplier::class => 'supplier',
                \App\Modules\BusinessPartners\Models\Organisation::class => 'organisation',
                \App\Modules\BusinessPartners\Models\ServiceProvider::class => 'service_provider',
            ];

            $contactsQuery = DB::table('bp_contacts')
                ->select(['id', 'contactable_type', 'contactable_id', 'first_name', 'last_name', 'job_title', 'department', 'email'])
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereIn('contactable_type', $allowedMorphModels);

            if ($wildcard) {
                $contactsQuery->where(function ($q) use ($wildcard) {
                    $q->where('email', 'like', $wildcard)
                        ->orWhere('first_name', 'like', $wildcard)
                        ->orWhere('last_name', 'like', $wildcard);
                });
            }

            $contacts = $contactsQuery->limit($limitPerCategory * 2)->get();

            foreach ($contacts as $contact) {
                $email = trim((string) $contact->email);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $firstName = $this->localizedJson($contact->first_name);
                $lastName = $this->localizedJson($contact->last_name);
                $contactName = trim("{$firstName} {$lastName}") ?: null;
                $jobTitle = $this->localizedJson($contact->job_title) ?: null;

                $entityKey = $morphMap[$contact->contactable_type] ?? 'partner';
                $parentName = $this->getParentDisplayName($contact->contactable_type, (string) $contact->contactable_id)
                    ?? ($contactName ?: 'Contact');

                $results->push([
                    'entity_type' => $entityKey,
                    'entity_id' => (string) $contact->contactable_id,
                    'entity_display_name' => $parentName,
                    'email' => $email,
                    'source' => 'contact',
                    'contact_id' => (string) $contact->id,
                    'contact_name' => $contactName,
                    'job_title' => $jobTitle,
                    'department' => $contact->department ?: null,
                    // Backward-compatibility keys
                    'name' => $contactName ?: $parentName,
                    'type' => 'Contact',
                ]);
            }

            // 3. Address emails from bp_addresses
            $addressesQuery = DB::table('bp_addresses')
                ->select(['id', 'addressable_type', 'addressable_id', 'label', 'email'])
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereIn('addressable_type', $allowedMorphModels);

            if ($wildcard) {
                $addressesQuery->where(function ($q) use ($wildcard) {
                    $q->where('email', 'like', $wildcard)
                        ->orWhere('label', 'like', $wildcard);
                });
            }

            $addresses = $addressesQuery->limit($limitPerCategory)->get();

            foreach ($addresses as $address) {
                $email = trim((string) $address->email);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $addressLabel = $this->localizedJson($address->label) ?: 'Address';
                $entityKey = $morphMap[$address->addressable_type] ?? 'partner';
                $parentName = $this->getParentDisplayName($address->addressable_type, (string) $address->addressable_id)
                    ?? $addressLabel;

                $results->push([
                    'entity_type' => $entityKey,
                    'entity_id' => (string) $address->addressable_id,
                    'entity_display_name' => $parentName,
                    'email' => $email,
                    'source' => 'address',
                    'contact_id' => (string) $address->id,
                    'contact_name' => $addressLabel,
                    'job_title' => null,
                    'department' => null,
                    // Backward-compatibility keys
                    'name' => $addressLabel,
                    'type' => 'Address',
                ]);
            }
        }

        // Deduplicate while preserving different owners/sources sharing the same email
        return $results->unique(fn ($item) => $item['entity_type'].':'.$item['entity_id'].':'.$item['email'].':'.$item['source'])->values();
    }

    /**
     * Search Business Partners and return them grouped with their general email,
     * address emails, and contact emails.
     *
     * @param  array<string>  $types  e.g. ['client', 'supplier']
     * @return Collection<int, array{
     *   id: string,
     *   entity_type: string,
     *   entity_type_label: string,
     *   display_name: string,
     *   primary_email: string|null,
     *   contacts: array<int, array{id: string, name: string, email: string, job_title: string|null, department: string|null, is_primary: bool}>,
     *   addresses: array<int, array{id: string, label: string, email: string, street: string|null, city: string|null}>,
     *   total_emails: int,
     * }>
     */
    public function searchGrouped(User $user, string $query = '', array $types = [], int $limit = 30): Collection
    {
        $allowedCategories = $this->getAllowedPartnerCategories($user);
        if (empty($allowedCategories)) {
            return collect();
        }

        $normalizedTypes = array_map(fn ($t) => mb_strtolower(trim((string) $t)), $types);
        $typeFilterMap = [
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
        ];

        $targetKeys = [];
        foreach ($normalizedTypes as $nt) {
            if (isset($typeFilterMap[$nt])) {
                $targetKeys[] = $typeFilterMap[$nt];
            }
        }

        $activeCategories = ! empty($targetKeys)
            ? array_filter($allowedCategories, fn ($c) => in_array($c['key'], $targetKeys, true))
            : $allowedCategories;

        $searchTerm = trim($query);
        $wildcard = $searchTerm !== '' ? "%{$searchTerm}%" : null;
        $groupedResults = collect();

        foreach ($activeCategories as $cat) {
            $modelClass = $cat['model'];
            $entityKey = $cat['key'];
            $typeLabel = $cat['type'];

            $queryBuilder = $modelClass::query()
                ->with([
                    'contacts' => fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''),
                    'addresses' => fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''),
                ])
                ->whereNull('deleted_at');

            if ($wildcard) {
                $queryBuilder->where(function ($q) use ($wildcard) {
                    $q->where('primary_email', 'like', $wildcard)
                        ->orWhere('legal_name', 'like', $wildcard)
                        ->orWhere('commercial_name', 'like', $wildcard)
                        ->orWhereHas('contacts', fn ($cq) => $cq->where('email', 'like', $wildcard)->orWhere('first_name', 'like', $wildcard)->orWhere('last_name', 'like', $wildcard))
                        ->orWhereHas('addresses', fn ($aq) => $aq->where('email', 'like', $wildcard)->orWhere('label', 'like', $wildcard));
                });
            } else {
                $queryBuilder->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('primary_email')->where('primary_email', '!=', '');
                    })
                        ->orWhereHas('contacts', fn ($cq) => $cq->whereNotNull('email')->where('email', '!=', ''))
                        ->orWhereHas('addresses', fn ($aq) => $aq->whereNotNull('email')->where('email', '!=', ''));
                });
            }

            $partners = $queryBuilder->limit($limit)->get();

            foreach ($partners as $partner) {
                $legalRaw = $partner->getRawOriginal('legal_name');
                $commercialRaw = $partner->getRawOriginal('commercial_name');
                $displayName = $this->resolveDisplayName(
                    is_array($legalRaw) ? json_encode($legalRaw) : (string) $legalRaw,
                    is_array($commercialRaw) ? json_encode($commercialRaw) : (string) $commercialRaw
                );

                $primaryEmail = trim((string) $partner->primary_email);
                $hasValidPrimary = filter_var($primaryEmail, FILTER_VALIDATE_EMAIL) !== false;

                $contacts = $partner->contacts
                    ->filter(fn ($c) => filter_var(trim((string) $c->email), FILTER_VALIDATE_EMAIL) !== false)
                    ->map(function ($c) {
                        $firstNameRaw = $c->getRawOriginal('first_name');
                        $lastNameRaw = $c->getRawOriginal('last_name');
                        $jobTitleRaw = $c->getRawOriginal('job_title');

                        $firstName = $this->localizedJson(is_array($firstNameRaw) ? json_encode($firstNameRaw) : (string) $firstNameRaw);
                        $lastName = $this->localizedJson(is_array($lastNameRaw) ? json_encode($lastNameRaw) : (string) $lastNameRaw);
                        $name = trim("{$firstName} {$lastName}") ?: 'Contact';
                        $jobTitle = $this->localizedJson(is_array($jobTitleRaw) ? json_encode($jobTitleRaw) : (string) $jobTitleRaw);

                        return [
                            'id' => (string) $c->id,
                            'name' => $name,
                            'email' => trim((string) $c->email),
                            'job_title' => $jobTitle ?: null,
                            'department' => $c->department ?: null,
                            'is_primary' => (bool) $c->is_primary,
                        ];
                    })
                    ->values()
                    ->all();

                $addresses = $partner->addresses
                    ->filter(fn ($a) => filter_var(trim((string) $a->email), FILTER_VALIDATE_EMAIL) !== false)
                    ->map(function ($a) {
                        $labelRaw = $a->getRawOriginal('label');
                        $streetRaw = $a->getRawOriginal('address_street');
                        $cityRaw = $a->getRawOriginal('city');

                        $label = $this->localizedJson(is_array($labelRaw) ? json_encode($labelRaw) : (string) $labelRaw) ?: 'Address';
                        $street = $this->localizedJson(is_array($streetRaw) ? json_encode($streetRaw) : (string) $streetRaw);
                        $city = $this->localizedJson(is_array($cityRaw) ? json_encode($cityRaw) : (string) $cityRaw);

                        return [
                            'id' => (string) $a->id,
                            'label' => $label,
                            'email' => trim((string) $a->email),
                            'street' => $street ?: null,
                            'city' => $city ?: null,
                        ];
                    })
                    ->values()
                    ->all();

                $totalEmails = ($hasValidPrimary ? 1 : 0) + count($contacts) + count($addresses);
                if ($totalEmails === 0) {
                    continue;
                }

                $groupedResults->push([
                    'id' => (string) $partner->id,
                    'entity_type' => $entityKey,
                    'entity_type_label' => $typeLabel,
                    'display_name' => $displayName,
                    'primary_email' => $hasValidPrimary ? $primaryEmail : null,
                    'contacts' => $contacts,
                    'addresses' => $addresses,
                    'total_emails' => $totalEmails,
                ]);
            }
        }

        return $groupedResults->values();
    }

    /**
     * Determines which Business Partner categories the user is authorized to query.
     */
    public function getAllowedPartnerCategories(User $user): array
    {
        $hasGlobalBp = $user->hasRole(['Super Admin', 'Admin'])
            || $user->can('read-BusinessPartners');

        $categories = [];

        if ($hasGlobalBp || $user->can('read-clients')) {
            $categories[] = [
                'table' => 'clients',
                'type' => 'Client',
                'key' => 'client',
                'model' => \App\Modules\BusinessPartners\Models\Client::class,
            ];
        }

        if ($hasGlobalBp || $user->can('read-suppliers')) {
            $categories[] = [
                'table' => 'suppliers',
                'type' => 'Supplier',
                'key' => 'supplier',
                'model' => \App\Modules\BusinessPartners\Models\Supplier::class,
            ];
        }

        if ($hasGlobalBp || $user->can('read-organisations')) {
            $categories[] = [
                'table' => 'organisations',
                'type' => 'Organisation',
                'key' => 'organisation',
                'model' => \App\Modules\BusinessPartners\Models\Organisation::class,
            ];
        }

        if ($hasGlobalBp || $user->can('read-service-providers')) {
            $categories[] = [
                'table' => 'service_providers',
                'type' => 'Service Provider',
                'key' => 'service_provider',
                'model' => \App\Modules\BusinessPartners\Models\ServiceProvider::class,
            ];
        }

        return $categories;
    }

    private function getParentDisplayName(string $modelClass, string $id): ?string
    {
        static $cache = [];
        $key = "{$modelClass}:{$id}";

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $tableMap = [
            \App\Modules\BusinessPartners\Models\Client::class => 'clients',
            \App\Modules\BusinessPartners\Models\Supplier::class => 'suppliers',
            \App\Modules\BusinessPartners\Models\Organisation::class => 'organisations',
            \App\Modules\BusinessPartners\Models\ServiceProvider::class => 'service_providers',
        ];

        $table = $tableMap[$modelClass] ?? null;
        if (! $table) {
            return $cache[$key] = null;
        }

        $row = DB::table($table)
            ->where('id', $id)
            ->select(['legal_name', 'commercial_name'])
            ->first();

        if (! $row) {
            return $cache[$key] = null;
        }

        return $cache[$key] = $this->resolveDisplayName($row->legal_name, $row->commercial_name);
    }

    private function resolveDisplayName(?string $legalName, ?string $commercialName): string
    {
        $legal = $this->localizedJson($legalName);
        $commercial = $this->localizedJson($commercialName);

        if ($legal !== '' && $commercial !== '' && $legal !== $commercial) {
            return "{$legal} ({$commercial})";
        }

        return $legal ?: ($commercial ?: 'Partner');
    }

    private function localizedJson(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return (string) ($decoded['en'] ?? $decoded['fr'] ?? $decoded['ar'] ?? reset($decoded) ?: '');
        }

        return trim($value, '"');
    }
}
