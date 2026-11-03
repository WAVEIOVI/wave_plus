<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Finance\Models\SalesInvoice;
use App\Modules\Hr\Models\Employee;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\Procurement\Models\PurchaseOrder;

class EmailRecordMatcherService
{
    /**
     * Regex patterns for recognized WAVE PLUS business reference numbers.
     */
    protected const REFERENCE_PATTERNS = [
        'quotation' => '/\b(?:QT|QUO|DEV)-[A-Z0-9_-]+/i',
        'sales_order' => '/\b(?:SO|CMD|BC)-[A-Z0-9_-]+/i',
        'purchase_order' => '/\b(?:PO|COMM)-[A-Z0-9_-]+/i',
        'sales_invoice' => '/\b(?:INV|FAC)-[A-Z0-9_-]+/i',
        'mission' => '/\b(?:MSN|MIS)-[A-Z0-9_-]+/i',
    ];

    /**
     * Inspect a conversation and its messages to extract matching records.
     *
     * @return array<int, array{record_type: string, record_id: string|int, record_reference: string, is_confirmed: bool, confidence_score: float, match_reason: string}>
     */
    public function matchRecords(EmailConversation $conversation, ?EmailMessage $message = null): array
    {
        $matches = [];
        $seenKeys = [];

        // 1. Text corpus from conversation subject and message body
        $corpus = $conversation->subject ?? '';
        if ($message) {
            $corpus .= ' '.($message->subject ?? '').' '.strip_tags($message->body_text ?? $message->body_html ?? '');
        } else {
            foreach ($conversation->messages()->limit(5)->get() as $msg) {
                $corpus .= ' '.($msg->subject ?? '').' '.strip_tags($msg->body_text ?? $msg->body_html ?? '');
            }
        }

        // 2. Scan corpus for document references (High confidence verified match)
        $refMatches = $this->matchByReferences($corpus);
        foreach ($refMatches as $m) {
            $key = "{$m['record_type']}:{$m['record_id']}";
            if (! isset($seenKeys[$key])) {
                $seenKeys[$key] = true;
                $matches[] = $m;
            }
        }

        // 3. Match participants by email address
        $emailMatches = $this->matchByEmails($conversation, $message);
        foreach ($emailMatches as $m) {
            $key = "{$m['record_type']}:{$m['record_id']}";
            if (! isset($seenKeys[$key])) {
                $seenKeys[$key] = true;
                $matches[] = $m;
            }
        }

        return $matches;
    }

    /**
     * Match document references in text.
     */
    protected function matchByReferences(string $text): array
    {
        $results = [];

        // Quotations
        if (preg_match_all(self::REFERENCE_PATTERNS['quotation'], $text, $matches)) {
            foreach (array_unique($matches[0]) as $ref) {
                $row = Quotation::query()->where('reference', $ref)->first();
                if ($row) {
                    $results[] = [
                        'record_type' => 'quotation',
                        'record_id' => (string) $row->id,
                        'record_reference' => $row->reference,
                        'is_confirmed' => true,
                        'confidence_score' => 1.00,
                        'match_reason' => "Exact reference {$ref} found in text",
                    ];
                }
            }
        }

        // Sales Orders
        if (preg_match_all(self::REFERENCE_PATTERNS['sales_order'], $text, $matches)) {
            foreach (array_unique($matches[0]) as $ref) {
                $row = SalesOrder::query()->where('reference', $ref)->first();
                if ($row) {
                    $results[] = [
                        'record_type' => 'sales_order',
                        'record_id' => (string) $row->id,
                        'record_reference' => $row->reference,
                        'is_confirmed' => true,
                        'confidence_score' => 1.00,
                        'match_reason' => "Exact reference {$ref} found in text",
                    ];
                }
            }
        }

        // Purchase Orders
        if (preg_match_all(self::REFERENCE_PATTERNS['purchase_order'], $text, $matches)) {
            foreach (array_unique($matches[0]) as $ref) {
                $row = PurchaseOrder::query()->where('reference', $ref)->first();
                if ($row) {
                    $results[] = [
                        'record_type' => 'purchase_order',
                        'record_id' => (string) $row->id,
                        'record_reference' => $row->reference,
                        'is_confirmed' => true,
                        'confidence_score' => 1.00,
                        'match_reason' => "Exact reference {$ref} found in text",
                    ];
                }
            }
        }

        // Invoices
        if (preg_match_all(self::REFERENCE_PATTERNS['sales_invoice'], $text, $matches)) {
            foreach (array_unique($matches[0]) as $ref) {
                $salesInv = SalesInvoice::query()->where('invoice_number', $ref)->first();
                if ($salesInv) {
                    $results[] = [
                        'record_type' => 'sales_invoice',
                        'record_id' => (string) $salesInv->id,
                        'record_reference' => $salesInv->invoice_number,
                        'is_confirmed' => true,
                        'confidence_score' => 1.00,
                        'match_reason' => "Exact invoice {$ref} found in text",
                    ];
                }
            }
        }

        // Missions
        if (preg_match_all(self::REFERENCE_PATTERNS['mission'], $text, $matches)) {
            foreach (array_unique($matches[0]) as $ref) {
                $mission = Mission::query()->where('mission_number', $ref)->first();
                if ($mission) {
                    $results[] = [
                        'record_type' => 'mission',
                        'record_id' => (string) $mission->id,
                        'record_reference' => $mission->mission_number,
                        'is_confirmed' => true,
                        'confidence_score' => 1.00,
                        'match_reason' => "Exact mission number {$ref} found in text",
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Match entities by email addresses found in conversation recipients and participants.
     */
    protected function matchByEmails(EmailConversation $conversation, ?EmailMessage $message = null): array
    {
        $emails = [];

        // Extract participant emails & sender emails
        $messages = $message ? collect([$message]) : $conversation->messages()->with('recipients')->get();
        foreach ($messages as $msg) {
            if ($msg->sender_email) {
                $emails[] = strtolower(trim($msg->sender_email));
            }
            foreach ($msg->recipients as $recipient) {
                if ($recipient->email) {
                    $emails[] = strtolower(trim($recipient->email));
                }
            }
        }

        $emails = array_unique(array_filter($emails));
        if (empty($emails)) {
            return [];
        }

        $results = [];

        foreach ($emails as $email) {
            $candidates = [];

            // 1. Clients
            $clients = Client::query()->where('primary_email', $email)->limit(5)->get();
            foreach ($clients as $client) {
                $ref = $client->legal_name ?? $client->commercial_name ?? $client->client_id;
                $candidates[] = [
                    'record_type' => 'client',
                    'record_id' => (string) $client->id,
                    'record_reference' => is_array($ref) ? (reset($ref) ?: $client->client_id) : (string) $ref,
                    'entity_label' => 'Client',
                    'client_model' => $client,
                ];
            }

            // 2. Suppliers
            $suppliers = Supplier::query()->where('primary_email', $email)->limit(5)->get();
            foreach ($suppliers as $supplier) {
                $ref = $supplier->legal_name ?? $supplier->commercial_name ?? $supplier->supplier_id;
                $candidates[] = [
                    'record_type' => 'supplier',
                    'record_id' => (string) $supplier->id,
                    'record_reference' => is_array($ref) ? (reset($ref) ?: $supplier->supplier_id) : (string) $ref,
                    'entity_label' => 'Supplier',
                ];
            }

            // 3. Organisations
            $organisations = \App\Modules\BusinessPartners\Models\Organisation::query()->where('primary_email', $email)->limit(5)->get();
            foreach ($organisations as $org) {
                $ref = $org->legal_name ?? $org->commercial_name ?? (string) $org->id;
                $candidates[] = [
                    'record_type' => 'organisation',
                    'record_id' => (string) $org->id,
                    'record_reference' => is_array($ref) ? (reset($ref) ?: (string) $org->id) : (string) $ref,
                    'entity_label' => 'Organisation',
                ];
            }

            // 4. Service Providers
            $providers = \App\Modules\BusinessPartners\Models\ServiceProvider::query()->where('primary_email', $email)->limit(5)->get();
            foreach ($providers as $provider) {
                $ref = $provider->legal_name ?? $provider->commercial_name ?? (string) $provider->id;
                $candidates[] = [
                    'record_type' => 'service_provider',
                    'record_id' => (string) $provider->id,
                    'record_reference' => is_array($ref) ? (reset($ref) ?: (string) $provider->id) : (string) $ref,
                    'entity_label' => 'Service Provider',
                ];
            }

            // 5. Contacts (BpContact)
            $contacts = \App\Modules\BusinessPartners\Models\BpContact::query()->with('contactable')->where('email', $email)->limit(5)->get();
            foreach ($contacts as $contact) {
                $cFirst = is_array($contact->first_name) ? (reset($contact->first_name) ?: '') : ($contact->first_name ?? '');
                $cLast = is_array($contact->last_name) ? (reset($contact->last_name) ?: '') : ($contact->last_name ?? '');
                $cName = trim("{$cFirst} {$cLast}");
                if ($cName === '') {
                    $cName = $contact->job_title ?: 'Contact';
                }
                $parentName = '';
                if ($contact->contactable) {
                    $pLegal = $contact->contactable->legal_name ?? $contact->contactable->commercial_name ?? '';
                    $parentName = is_array($pLegal) ? (reset($pLegal) ?: '') : (string) $pLegal;
                }
                $candidates[] = [
                    'record_type' => 'bp_contact',
                    'record_id' => (string) $contact->id,
                    'record_reference' => $parentName ? "{$cName} ({$parentName})" : $cName,
                    'entity_label' => 'Contact',
                ];
            }

            // 6. Employees
            $employees = Employee::query()
                ->where('work_email', $email)
                ->orWhere('personal_email', $email)
                ->limit(5)
                ->get();
            foreach ($employees as $employee) {
                $candidates[] = [
                    'record_type' => 'employee',
                    'record_id' => (string) $employee->id,
                    'record_reference' => trim("{$employee->first_name} {$employee->last_name}"),
                    'entity_label' => 'Employee',
                ];
            }

            // Ambiguity check: unique exact match vs multiple matches
            $isUnique = count($candidates) === 1;

            foreach ($candidates as $cand) {
                $results[] = [
                    'record_type' => $cand['record_type'],
                    'record_id' => $cand['record_id'],
                    'record_reference' => $cand['record_reference'],
                    'is_confirmed' => $isUnique, // Unique match is confirmed; multiple matches require manual selection!
                    'confidence_score' => $isUnique ? 0.95 : 0.50,
                    'match_reason' => $isUnique
                        ? "Unique exact match for email {$email} ({$cand['entity_label']})"
                        : "Ambiguous match: multiple records share {$email} ({$cand['entity_label']}). User confirmation required.",
                ];

                // Client-specific quotations suggestion if unique
                if ($isUnique && isset($cand['client_model'])) {
                    $recentQuotations = Quotation::query()
                        ->where('client_id', $cand['client_model']->id)
                        ->whereIn('status', ['draft', 'sent', 'pending_approval'])
                        ->limit(2)
                        ->get();

                    if ($recentQuotations->count() === 1) {
                        $quo = $recentQuotations->first();
                        $results[] = [
                            'record_type' => 'quotation',
                            'record_id' => (string) $quo->id,
                            'record_reference' => $quo->reference,
                            'is_confirmed' => false, // Ambiguous, requires user confirmation!
                            'confidence_score' => 0.70,
                            'match_reason' => "Suggested: Single active quotation for client {$cand['record_reference']}",
                        ];
                    }
                }
            }
        }

        return $results;
    }
}
