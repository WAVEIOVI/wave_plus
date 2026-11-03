<?php

declare(strict_types=1);

use App\Modules\Communications\Email\Automation\Controllers\EmailAutomationController;
use App\Modules\Communications\Email\Controllers\EmailAttachmentController;
use App\Modules\Communications\Email\Controllers\EmailConversationController;
use App\Modules\Communications\Email\Controllers\EmailDocumentController;
use App\Modules\Communications\Email\Controllers\EmailMailboxController;
use App\Modules\Communications\Email\Controllers\EmailMessageController;
use App\Modules\Communications\Email\Controllers\EmailRecordLinkController;
use App\Modules\Communications\Email\Controllers\EmailSenderProfileController;
use App\Modules\Communications\Email\Controllers\EmailTemplateController;
use App\Modules\Communications\Email\Controllers\RecipientSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Communications Module API Routes
|--------------------------------------------------------------------------
|
| Prefixed with /api/communications and protected by auth:sanctum.
|
*/

Route::prefix('communications')
    ->middleware(['auth:sanctum'])
    ->group(function () {

        // ── Sender Profiles ────────────────────────────────────────────
        Route::get('email/sender-profiles', [EmailSenderProfileController::class, 'index']);

        // ── Shared Mailboxes (Inboxes) ──────────────────────────────────
        Route::get('email/mailboxes', [EmailMailboxController::class, 'index']);
        Route::post('email/mailboxes/{mailbox}/sync', [EmailMailboxController::class, 'sync']);

        // ── Email Automation Engine ─────────────────────────────────────
        Route::get('email/automation/rules', [EmailAutomationController::class, 'rules']);
        Route::put('email/automation/rules/{rule}', [EmailAutomationController::class, 'updateRule']);
        Route::get('email/automation/logs', [EmailAutomationController::class, 'logs']);
        Route::post('email/automation/logs/{log}/retry', [EmailAutomationController::class, 'retryLog']);
        Route::get('email/automation/approvals', [EmailAutomationController::class, 'approvalRequests']);
        Route::post('email/automation/approvals/{approval}/approve', [EmailAutomationController::class, 'approve']);
        Route::post('email/automation/approvals/{approval}/reject', [EmailAutomationController::class, 'reject']);
        Route::get('email/automation/tasks', [EmailAutomationController::class, 'tasks']);
        Route::post('email/automation/tasks/{task}/complete', [EmailAutomationController::class, 'completeTask']);
        Route::get('email/automation/notifications', [EmailAutomationController::class, 'notifications']);
        Route::post('email/automation/notifications/{notification}/read', [EmailAutomationController::class, 'markNotificationRead']);

        // ── Threaded Conversations ──────────────────────────────────────
        Route::get('email/conversations', [EmailConversationController::class, 'index']);
        Route::get('email/conversations/{conversation}', [EmailConversationController::class, 'show']);
        Route::post('email/conversations/{conversation}/assign', [EmailConversationController::class, 'assign']);
        Route::post('email/conversations/{conversation}/status', [EmailConversationController::class, 'updateStatus']);
        Route::post('email/conversations/{conversation}/notes', [EmailConversationController::class, 'addNote']);

        // ── Business Record Associations ────────────────────────────────
        Route::get('email/conversations/{conversation}/links', [EmailRecordLinkController::class, 'index']);
        Route::post('email/conversations/{conversation}/links', [EmailRecordLinkController::class, 'store']);
        Route::delete('email/conversations/{conversation}/links', [EmailRecordLinkController::class, 'destroy']);
        Route::post('email/conversations/{conversation}/auto-match', [EmailRecordLinkController::class, 'autoMatch']);
        Route::post('email/record-links/{link}/confirm', [EmailRecordLinkController::class, 'confirm']);
        Route::post('email/record-links/{link}/dismiss', [EmailRecordLinkController::class, 'dismiss']);
        Route::get('email/records/{recordType}/{recordId}/conversations', [EmailRecordLinkController::class, 'getRecordConversations']);

        // ── Recipient Search ───────────────────────────────────────────
        Route::get('email/recipients/departments', [RecipientSearchController::class, 'departments']);
        Route::get('email/recipients/search', [RecipientSearchController::class, 'search']);

        // ── Existing Documents (attachable) ────────────────────────────
        Route::get('email/documents/search', [EmailDocumentController::class, 'search']);

        // ── Signatures & Templates ─────────────────────────────────────
        Route::get('email/signatures', [EmailTemplateController::class, 'signatures']);
        Route::get('email/templates', [EmailTemplateController::class, 'index']);
        Route::get('email/templates/context-records', [EmailTemplateController::class, 'contextRecords']);
        Route::get('email/templates/variables', [EmailTemplateController::class, 'variables']);
        Route::get('email/templates/{template}', [EmailTemplateController::class, 'show']);
        Route::post('email/templates/{template}/render', [EmailTemplateController::class, 'render']);

        // ── Email Messages ─────────────────────────────────────────────
        Route::get('email/messages', [EmailMessageController::class, 'index']);
        Route::post('email/messages', [EmailMessageController::class, 'store']);
        Route::post('email/messages/batch', [EmailMessageController::class, 'batch']);
        Route::get('email/messages/{message}', [EmailMessageController::class, 'show']);
        Route::put('email/messages/{message}', [EmailMessageController::class, 'update']);
        Route::delete('email/messages/{message}', [EmailMessageController::class, 'destroy']);
        Route::post('email/messages/{message}/send', [EmailMessageController::class, 'send']);
        Route::post('email/messages/{message}/retry', [EmailMessageController::class, 'retry']);
        Route::get('email/attachments/{attachment}/download', [EmailAttachmentController::class, 'download']);

    });
