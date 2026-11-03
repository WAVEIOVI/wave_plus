<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communications\Email\Automation\Enums\AutomationActionType;
use App\Modules\Communications\Email\Automation\Enums\AutomationExecutionMode;
use App\Modules\Communications\Email\Automation\Enums\AutomationTrigger;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationRule;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use Illuminate\Database\Seeder;

class EmailAutomationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $salesProfile = EmailSenderProfile::where('email', 'sales@waveplus.xyz')->first();
        $purchasingProfile = EmailSenderProfile::where('email', 'purchasing@waveplus.xyz')->first();
        $financeProfile = EmailSenderProfile::where('email', 'finance@waveplus.xyz')->first();
        $directionProfile = EmailSenderProfile::where('email', 'direction@waveplus.xyz')->first();

        $rules = [
            [
                'name' => 'Auto-Send Quotation to Client',
                'slug' => 'auto-send-quotation-to-client',
                'description' => 'Automatically dispatch quotation email to client when quotation is sent.',
                'trigger' => AutomationTrigger::QuotationSent->value,
                'is_active' => true,
                'execution_mode' => AutomationExecutionMode::Automatic->value,
                'template_slug' => 'quotation-submission-fr',
                'sender_profile_id' => $salesProfile?->id,
                'recipient_type' => 'record_partner',
                'conditions' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'sent'],
                ],
                'actions' => [
                    ['type' => AutomationActionType::SendEmail->value],
                ],
            ],
            [
                'name' => 'Follow up Pending Quotations',
                'slug' => 'follow-up-pending-quotations',
                'description' => 'Create a follow-up task when a quotation remains pending customer review.',
                'trigger' => AutomationTrigger::QuotationPending->value,
                'is_active' => true,
                'execution_mode' => AutomationExecutionMode::Manual->value,
                'template_slug' => 'general-communication-fr',
                'sender_profile_id' => $salesProfile?->id,
                'recipient_type' => 'record_partner',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => ['sent', 'pending_approval']],
                ],
                'actions' => [
                    [
                        'type' => AutomationActionType::CreateFollowUpTask->value,
                        'params' => [
                            'title' => 'Follow up on pending quotation',
                            'due_days' => 3,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Purchase Order Supplier Notification',
                'slug' => 'purchase-order-supplier-notification',
                'description' => 'Prepare PO email draft or send to supplier upon submission.',
                'trigger' => AutomationTrigger::PurchaseOrderSubmitted->value,
                'is_active' => true,
                'execution_mode' => AutomationExecutionMode::ApprovalRequired->value,
                'template_slug' => 'purchase-order-submission-fr',
                'sender_profile_id' => $purchasingProfile?->id,
                'recipient_type' => 'record_partner',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => ['approved', 'sent']],
                ],
                'actions' => [
                    ['type' => AutomationActionType::CreateApprovalRequest->value],
                ],
            ],
            [
                'name' => 'Overdue Invoice Reminder',
                'slug' => 'overdue-invoice-reminder',
                'description' => 'Send payment reminder email when invoice due date has passed.',
                'trigger' => AutomationTrigger::InvoiceOverdue->value,
                'is_active' => true,
                'execution_mode' => AutomationExecutionMode::Automatic->value,
                'template_slug' => 'payment-reminder-fr',
                'sender_profile_id' => $financeProfile?->id,
                'recipient_type' => 'record_partner',
                'conditions' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'posted'],
                    ['field' => 'payment_status', 'operator' => '!=', 'value' => 'paid'],
                ],
                'actions' => [
                    ['type' => AutomationActionType::SendEmail->value],
                ],
            ],
            [
                'name' => 'Mission Scheduled Confirmation',
                'slug' => 'mission-scheduled-confirmation',
                'description' => 'Send confirmation email and notify team when an operational mission is scheduled.',
                'trigger' => AutomationTrigger::MissionScheduled->value,
                'is_active' => true,
                'execution_mode' => AutomationExecutionMode::Automatic->value,
                'template_slug' => 'mission-confirmation-fr',
                'sender_profile_id' => $directionProfile?->id,
                'recipient_type' => 'record_partner',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => ['scheduled', 'in_progress']],
                ],
                'actions' => [
                    ['type' => AutomationActionType::SendEmail->value],
                    ['type' => AutomationActionType::SendInternalNotification->value],
                ],
            ],
            [
                'name' => 'Equipment Maintenance Due Alert',
                'slug' => 'equipment-maintenance-due-alert',
                'description' => 'Create a maintenance task and notification when equipment inspection date is due.',
                'trigger' => AutomationTrigger::EquipmentMaintenanceDue->value,
                'is_active' => true,
                'execution_mode' => AutomationExecutionMode::Manual->value,
                'template_slug' => 'maintenance-notification-fr',
                'sender_profile_id' => $directionProfile?->id,
                'recipient_type' => 'record_partner',
                'conditions' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ],
                'actions' => [
                    [
                        'type' => AutomationActionType::CreateFollowUpTask->value,
                        'params' => [
                            'title' => 'Perform periodic equipment inspection',
                            'due_days' => 5,
                        ],
                    ],
                    [
                        'type' => AutomationActionType::SendInternalNotification->value,
                        'params' => [
                            'title' => 'Equipment Maintenance Due',
                            'message' => 'Equipment inspection is due within 7 days.',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($rules as $data) {
            EmailAutomationRule::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
