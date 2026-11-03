<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communications\Email\Models\EmailTemplate;
use App\Modules\Communications\Email\Services\EmailLayoutBuilder;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Seed predefined, system-managed email templates across all 11 categories
     * in French, English, and Arabic using professional WAVE PLUS layouts.
     */
    public function run(): void
    {
        $templateDefinitions = [
            // ── 1. General Communication ──────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_GENERAL_COMMUNICATION,
                'variables' => ['recipient.name', 'company.name', 'sender.name'],
                'fr' => [
                    'name' => 'Communication Générale (FR)',
                    'subject' => '{{ company.name }} — Information pour {{ recipient.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Nous vous contactons concernant votre dossier auprès de <strong>{{ company.name }}</strong>.</p><p style="margin: 0 0 12px 0;">Nos équipes restent à votre entière disposition pour vous accompagner et répondre à toutes vos questions.</p><p style="margin: 0;">Nous vous remercions pour votre confiance et votre collaboration continue.</p>',
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Nous vous contactons concernant votre dossier auprès de {{ company.name }}.\n\nNos équipes restent à votre entière disposition pour vous accompagner et répondre à toutes vos questions.\n\nNous vous remercions pour votre confiance et votre collaboration continue.",
                ],
                'en' => [
                    'name' => 'General Communication (EN)',
                    'subject' => '{{ company.name }} — Update for {{ recipient.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">We are reaching out to you regarding your account with <strong>{{ company.name }}</strong>.</p><p style="margin: 0 0 12px 0;">Our team remains at your service to assist you with any inquiries or operational requirements.</p><p style="margin: 0;">Thank you for your valued partnership and ongoing collaboration.</p>',
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "We are reaching out to you regarding your account with {{ company.name }}.\n\nOur team remains at your service to assist you with any inquiries or operational requirements.\n\nThank you for your valued partnership and ongoing collaboration.",
                ],
                'ar' => [
                    'name' => 'مراسلة عامة (AR)',
                    'subject' => '{{ company.name }} — إشعار إلى {{ recipient.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">نتواصل معكم بخصوص ملفكم لدى <strong>{{ company.name }}</strong>.</p><p style="margin: 0 0 12px 0;">فريق عملنا على أتم الاستعداد لمواكبتكم والإجابة عن جميع استفساراتكم واحتياجاتكم التشغيلية.</p><p style="margin: 0;">شاكرين لكم حسن تعاونكم الدائم وثقتكم المتجددة.</p>',
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "نتواصل معكم بخصوص ملفكم لدى {{ company.name }}.\n\nفريق عملنا على أتم الاستعداد لمواكبتكم والإجابة عن جميع استفساراتكم واحتياجاتكم التشغيلية.\n\nشاكرين لكم حسن تعاونكم الدائم وثقتكم المتجددة.",
                ],
            ],

            // ── 2. Quotation Submission ───────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_QUOTATION_SUBMISSION,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.date', 'document.due_date', 'document.total'],
                'fr' => [
                    'name' => 'Transmission de Devis (FR)',
                    'subject' => '{{ company.name }} — Devis Commercial {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Nous avons le plaisir de vous transmettre notre proposition commerciale <strong>{{ document.reference }}</strong> émise le {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Cette offre est valable jusqu\'au <strong>{{ document.due_date }}</strong>. Vous trouverez le devis détaillé en pièce jointe ainsi que les spécifications associées.</p><p style="margin: 0;">Restant à votre entière disposition pour tout complément d\'information ou ajustement technique.</p>',
                    'document_summary' => [
                        'Numéro de devis' => '{{ document.reference }}',
                        'Date d\'émission' => '{{ document.date }}',
                        'Date de validité' => '{{ document.due_date }}',
                        'Montant Total (TTC)' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Nous avons le plaisir de vous transmettre notre proposition commerciale {{ document.reference }} émise le {{ document.date }}.\n\nCette offre est valable jusqu'au {{ document.due_date }}. Vous trouverez le devis détaillé en pièce jointe.",
                ],
                'en' => [
                    'name' => 'Quotation Submission (EN)',
                    'subject' => '{{ company.name }} — Commercial Quotation {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">We are pleased to submit our commercial quotation <strong>{{ document.reference }}</strong> issued on {{ document.date }}.</p><p style="margin: 0 0 12px 0;">This proposal is valid until <strong>{{ document.due_date }}</strong>. The complete itemized quotation document is attached for your review.</p><p style="margin: 0;">Please let us know if you require any clarifications or revisions.</p>',
                    'document_summary' => [
                        'Quotation Ref' => '{{ document.reference }}',
                        'Issue Date' => '{{ document.date }}',
                        'Valid Until' => '{{ document.due_date }}',
                        'Total Amount (TTC)' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "We are pleased to submit our commercial quotation {{ document.reference }} issued on {{ document.date }}.\n\nThis proposal is valid until {{ document.due_date }}. Please find the detailed quotation document attached.",
                ],
                'ar' => [
                    'name' => 'إرسال عرض أسعار (AR)',
                    'subject' => '{{ company.name }} — عرض أسعار تجاري رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">يسرنا أن نوافيكم بعرض الأسعار التجاري رقم <strong>{{ document.reference }}</strong> الصادر بتاريخ {{ document.date }}.</p><p style="margin: 0 0 12px 0;">هذا العرض سارٍ حتى تاريخ <strong>{{ document.due_date }}</strong>. تجدون العرض المفصل مرفقاً بهذه الرسالة للاطلاع والمصادقة.</p><p style="margin: 0;">نحن رهن إشارتكم لأي استفسار أو تعديلات فنية مطلوبة.</p>',
                    'document_summary' => [
                        'مرجع عرض الأسعار' => '{{ document.reference }}',
                        'تاريخ الإصدار' => '{{ document.date }}',
                        'تاريخ انتهاء الصلاحية' => '{{ document.due_date }}',
                        'المبلغ الإجمالي' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "يسرنا أن نوافيكم بعرض الأسعار التجاري رقم {{ document.reference }} الصادر بتاريخ {{ document.date }}.\n\nهذا العرض سارٍ حتى تاريخ {{ document.due_date }}. تجدون الوثيقة المفصلة مرفقة.",
                ],
            ],

            // ── 3. Purchase Order Submission ──────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_PURCHASE_ORDER_SUBMISSION,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.date', 'document.due_date', 'document.total'],
                'fr' => [
                    'name' => 'Bon de Commande Fournisseur (FR)',
                    'subject' => '{{ company.name }} — Bon de Commande d\'Achat {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Veuillez trouver ci-joint notre bon de commande d\'achat <strong>{{ document.reference }}</strong> émis le {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Date de livraison convenue : <strong>{{ document.due_date }}</strong>. Nous vous remercions de bien vouloir nous accuser réception et de nous confirmer les délais d\'exécution.</p>',
                    'document_summary' => [
                        'Bon de commande' => '{{ document.reference }}',
                        'Date de commande' => '{{ document.date }}',
                        'Délai convenu' => '{{ document.due_date }}',
                        'Montant Total (TTC)' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Veuillez trouver ci-joint notre bon de commande d'achat {{ document.reference }} émis le {{ document.date }}.\n\nDate de livraison convenue : {{ document.due_date }}. Merci de bien vouloir nous accuser réception.",
                ],
                'en' => [
                    'name' => 'Purchase Order Submission (EN)',
                    'subject' => '{{ company.name }} — Purchase Order {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Please find attached our official purchase order <strong>{{ document.reference }}</strong> issued on {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Committed delivery date: <strong>{{ document.due_date }}</strong>. Kindly acknowledge receipt and confirm production and delivery schedules.</p>',
                    'document_summary' => [
                        'PO Reference' => '{{ document.reference }}',
                        'Order Date' => '{{ document.date }}',
                        'Expected Delivery' => '{{ document.due_date }}',
                        'Total (TTC)' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Please find attached our official purchase order {{ document.reference }} issued on {{ document.date }}.\n\nCommitted delivery date: {{ document.due_date }}. Kindly acknowledge receipt.",
                ],
                'ar' => [
                    'name' => 'أمر شراء للمورد (AR)',
                    'subject' => '{{ company.name }} — أمر شراء رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">نرفق لكم طيه أمر الشراء الرسمي رقم <strong>{{ document.reference }}</strong> الصادر بتاريخ {{ document.date }}.</p><p style="margin: 0 0 12px 0;">تاريخ التسليم المتفق عليه: <strong>{{ document.due_date }}</strong>. يرجى التكرم بتأكيد الاستلام وجدول التوريد المعتمد.</p>',
                    'document_summary' => [
                        'رقم أمر الشراء' => '{{ document.reference }}',
                        'تاريخ الطلب' => '{{ document.date }}',
                        'تاريخ التسليم المتفق عليه' => '{{ document.due_date }}',
                        'المبلغ الإجمالي' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "نرفق لكم طيه أمر الشراء الرسمي رقم {{ document.reference }} الصادر بتاريخ {{ document.date }}.\n\nتاريخ التسليم المتفق عليه: {{ document.due_date }}. يرجى تأكيد الاستلام.",
                ],
            ],

            // ── 4. Sales Order Communication ──────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_SALES_ORDER_COMMUNICATION,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.date', 'document.total'],
                'fr' => [
                    'name' => 'Confirmation de Commande Client (FR)',
                    'subject' => '{{ company.name }} — Confirmation de Commande {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Nous avons le plaisir de vous confirmer la bonne prise en compte de votre commande client <strong>{{ document.reference }}</strong> enregistrée le {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Nos équipes logistiques et opérationnelles préparent actuellement le traitement de vos prestations avec la plus grande rigueur.</p><p style="margin: 0;">Vous serez informé des étapes d\'avancement et d\'expédition.</p>',
                    'document_summary' => [
                        'Commande Client' => '{{ document.reference }}',
                        'Date d\'enregistrement' => '{{ document.date }}',
                        'Montant Total (TTC)' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Nous confirmons la bonne prise en compte de votre commande client {{ document.reference }} enregistrée le {{ document.date }}.\n\nNos équipes opérationnelles préparent actuellement votre commande avec soin.",
                ],
                'en' => [
                    'name' => 'Sales Order Confirmation (EN)',
                    'subject' => '{{ company.name }} — Order Confirmation {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">We are pleased to confirm that your sales order <strong>{{ document.reference }}</strong> registered on {{ document.date }} has been accepted.</p><p style="margin: 0 0 12px 0;">Our operations and fulfillment teams are actively processing your order according to agreed schedules.</p><p style="margin: 0;">You will receive regular progress and dispatch notifications.</p>',
                    'document_summary' => [
                        'Sales Order Ref' => '{{ document.reference }}',
                        'Registration Date' => '{{ document.date }}',
                        'Total Amount (TTC)' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "We are pleased to confirm that your sales order {{ document.reference }} registered on {{ document.date }} has been accepted.\n\nOur teams are actively processing your order.",
                ],
                'ar' => [
                    'name' => 'تأكيد طلب الزبون (AR)',
                    'subject' => '{{ company.name }} — تأكيد الطلبية رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">يسرنا تأكيد تسجيل طلبيتكم رقم <strong>{{ document.reference }}</strong> بتاريخ {{ document.date }}.</p><p style="margin: 0 0 12px 0;">فرقنا التشغيلية واللوجستية تعمل حالياً على معالجة طلبكم وتجهيزه بأعلى معايير الجودة والالتزام.</p><p style="margin: 0;">سنوافيكم بتفاصيل مراحل التنفيذ والشحن فور جهوزيتها.</p>',
                    'document_summary' => [
                        'رقم الطلبية' => '{{ document.reference }}',
                        'تاريخ التسجيل' => '{{ document.date }}',
                        'المبلغ الإجمالي' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "يسرنا تأكيد تسجيل طلبيتكم رقم {{ document.reference }} بتاريخ {{ document.date }}.\n\nفرقنا التشغيلية واللوجستية تعمل حالياً على معالجة طلبكم بكل عناية.",
                ],
            ],

            // ── 5. Invoice Delivery ───────────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_INVOICE_DELIVERY,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.date', 'document.due_date', 'document.total'],
                'fr' => [
                    'name' => 'Envoi de Facture (FR)',
                    'subject' => '{{ company.name }} — Facture {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Veuillez trouver ci-joint votre facture <strong>{{ document.reference }}</strong> émise le {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Le règlement de cette facture est attendu au plus tard le <strong>{{ document.due_date }}</strong> selon nos conditions convenues.</p><p style="margin: 0;">Nous vous remercions pour votre confiance et restons à votre disposition pour toute question comptable.</p>',
                    'document_summary' => [
                        'Numéro de facture' => '{{ document.reference }}',
                        'Date d\'émission' => '{{ document.date }}',
                        'Date d\'échéance' => '{{ document.due_date }}',
                        'Montant Total (TTC)' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Veuillez trouver ci-joint votre facture {{ document.reference }} émise le {{ document.date }}.\n\nDate d'échéance : {{ document.due_date }}. Montant total : {{ document.total }}.",
                ],
                'en' => [
                    'name' => 'Invoice Delivery (EN)',
                    'subject' => '{{ company.name }} — Invoice {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Please find attached your invoice <strong>{{ document.reference }}</strong> issued on {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Payment is due on or before <strong>{{ document.due_date }}</strong> according to agreed terms.</p><p style="margin: 0;">Thank you for your business. Please reach out to our billing desk for any payment queries.</p>',
                    'document_summary' => [
                        'Invoice Number' => '{{ document.reference }}',
                        'Issue Date' => '{{ document.date }}',
                        'Payment Due Date' => '{{ document.due_date }}',
                        'Total Due (TTC)' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Please find attached your invoice {{ document.reference }} issued on {{ document.date }}.\n\nDue Date : {{ document.due_date }}. Total Due: {{ document.total }}.",
                ],
                'ar' => [
                    'name' => 'إرسال فاتورة (AR)',
                    'subject' => '{{ company.name }} — فاتورة رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">نرفق لكم طيه الفاتورة رقم <strong>{{ document.reference }}</strong> الصادرة بتاريخ {{ document.date }}.</p><p style="margin: 0 0 12px 0;">يرجى سداد المبلغ المستحق في موعد أقصاه <strong>{{ document.due_date }}</strong> وفقاً للشروط المتفق عليها.</p><p style="margin: 0;">نشكركم على ثقتكم الغالية ونبقى على أهبة الاستعداد لأي توضيحات مالية أو محاسبية.</p>',
                    'document_summary' => [
                        'رقم الفاتورة' => '{{ document.reference }}',
                        'تاريخ الإصدار' => '{{ document.date }}',
                        'تاريخ الاستحقاق' => '{{ document.due_date }}',
                        'المبلغ المستحق الإجمالي' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "نرفق لكم طيه الفاتورة رقم {{ document.reference }} الصادرة بتاريخ {{ document.date }}.\n\nتاريخ الاستحقاق : {{ document.due_date }}. المبلغ المستحق : {{ document.total }}.",
                ],
            ],

            // ── 6. Payment Reminder ───────────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_PAYMENT_REMINDER,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.due_date', 'document.total'],
                'fr' => [
                    'name' => 'Rappel de Paiement (FR)',
                    'subject' => '{{ company.name }} — Rappel : Facture {{ document.reference }} en attente',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Sauf erreur ou omission de notre part, nous constatons que la facture <strong>{{ document.reference }}</strong> échue le <strong>{{ document.due_date }}</strong> reste en attente de règlement.</p><p style="margin: 0 0 12px 0;">Nous vous remercions de bien vouloir procéder à sa régularisation dans les meilleurs délais ou de nous transmettre les références de votre virement.</p><p style="margin: 0;">Si votre paiement a déjà été effectué récemment, nous vous prions de ne pas tenir compte de cette relance.</p>',
                    'document_summary' => [
                        'Facture concernée' => '{{ document.reference }}',
                        'Date d\'échéance' => '{{ document.due_date }}',
                        'Montant en attente' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Sauf erreur de notre part, la facture {{ document.reference }} arrivée à échéance le {{ document.due_date }} reste en attente de règlement (Montant : {{ document.total }}).\n\nMerci de bien vouloir régulariser ce paiement dans les meilleurs délais.",
                ],
                'en' => [
                    'name' => 'Payment Reminder (EN)',
                    'subject' => '{{ company.name }} — Payment Reminder: Invoice {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Our accounting records indicate that invoice <strong>{{ document.reference }}</strong> which was due on <strong>{{ document.due_date }}</strong> remains outstanding.</p><p style="margin: 0 0 12px 0;">We kindly ask you to arrange payment at your earliest convenience or share your transfer confirmation reference.</p><p style="margin: 0;">If payment has already been remitted, please disregard this automated notification.</p>',
                    'document_summary' => [
                        'Invoice Reference' => '{{ document.reference }}',
                        'Due Date' => '{{ document.due_date }}',
                        'Outstanding Balance' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Our accounting records indicate that invoice {{ document.reference }} due on {{ document.due_date }} remains unpaid for {{ document.total }}.\n\nKindly arrange settlement at your earliest convenience.",
                ],
                'ar' => [
                    'name' => 'تذكير بالسداد (AR)',
                    'subject' => '{{ company.name }} — تذكير بمستحقات الفاتورة رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">نود تذكيركم بأن الفاتورة رقم <strong>{{ document.reference }}</strong> المستحقة بتاريخ <strong>{{ document.due_date }}</strong> لا تزال معلقة في سجلاتنا المحاسبية.</p><p style="margin: 0 0 12px 0;">نرجو منكم التفضل باتخاذ الإجراءات اللازمة لتحويل المبلغ المستحق في أقرب وقت أو موافاتنا بإشعار التحويل.</p><p style="margin: 0;">في حال تم السداد مؤخراً، يرجى اعتبار هذا الإشعار لاغياً مع خالص شكرنا.</p>',
                    'document_summary' => [
                        'رقم الفاتورة' => '{{ document.reference }}',
                        'تاريخ الاستحقاق' => '{{ document.due_date }}',
                        'المبلغ المعلق' => '{{ document.total }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "نود تذكيركم بأن الفاتورة رقم {{ document.reference }} المستحقة بتاريخ {{ document.due_date }} بقيمة {{ document.total }} لا تزال معلقة.\n\nيرجى التكرم بالسداد في أقرب وقت.",
                ],
            ],

            // ── 7. Supplier Request ───────────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_SUPPLIER_REQUEST,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.date'],
                'fr' => [
                    'name' => 'Demande de Prix Fournisseur (FR)',
                    'subject' => '{{ company.name }} — Demande de Devis {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Dans le cadre de nos approvisionnements, nous vous sollicitons pour une offre de prix concernant la consultation <strong>{{ document.reference }}</strong> émise le {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Merci de bien vouloir nous transmettre vos meilleures conditions tarifaires, vos disponibilités de stock et vos délais d\'expédition.</p>',
                    'document_summary' => [
                        'Consultation' => '{{ document.reference }}',
                        'Date de demande' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Dans le cadre de nos approvisionnements, nous vous sollicitons pour une offre de prix concernant la consultation {{ document.reference }}.\n\nMerci de nous transmettre vos meilleures conditions tarifaires.",
                ],
                'en' => [
                    'name' => 'Supplier Request for Quotation (EN)',
                    'subject' => '{{ company.name }} — RFQ Inquiry {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">As part of our procurement requirements, we would like to request your quotation for inquiry <strong>{{ document.reference }}</strong> issued on {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Please provide your best commercial terms, lead times, and product availability.</p>',
                    'document_summary' => [
                        'Inquiry Ref' => '{{ document.reference }}',
                        'Inquiry Date' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "We would like to request your quotation for inquiry {{ document.reference }} issued on {{ document.date }}.\n\nPlease provide your best commercial terms and lead times.",
                ],
                'ar' => [
                    'name' => 'طلب استدراج عروض أسعار (AR)',
                    'subject' => '{{ company.name }} — طلب عروض أسعار رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">في إطار مشترياتنا واحتياجاتنا التوريدية، نطلب منكم التفضل بتقديم أفضل عرض أسعار للاستشارة رقم <strong>{{ document.reference }}</strong> الصادرة بتاريخ {{ document.date }}.</p><p style="margin: 0 0 12px 0;">يرجى تزويدنا بالأسعار التنافسية وتوفر المخزون ومواعيد التسليم الممكنة.</p>',
                    'document_summary' => [
                        'مرجع الاستشارة' => '{{ document.reference }}',
                        'تاريخ الطلب' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => 'نطلب منكم التفضل بتقديم أفضل عرض أسعار للاستشارة رقم {{ document.reference }} الصادرة بتاريخ {{ document.date }}.',
                ],
            ],

            // ── 8. Delivery Confirmation ──────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_DELIVERY_CONFIRMATION,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.date'],
                'fr' => [
                    'name' => 'Avis de Livraison & Expédition (FR)',
                    'subject' => '{{ company.name }} — Avis d\'Expédition {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Nous vous informons que votre expédition associée au bon de livraison <strong>{{ document.reference }}</strong> est en cours d\'acheminement.</p><p style="margin: 0 0 12px 0;">Date d\'expédition : {{ document.date }}. Le bordereau de livraison et la fiche de contrôle sont joints à ce message.</p>',
                    'document_summary' => [
                        'Bon de livraison' => '{{ document.reference }}',
                        'Date d\'expédition' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Votre expédition associée au bon de livraison {{ document.reference }} est en cours d'acheminement (Date : {{ document.date }}).\n\nLe bordereau de livraison est joint.",
                ],
                'en' => [
                    'name' => 'Delivery Notification (EN)',
                    'subject' => '{{ company.name }} — Dispatch Notice {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">We are pleased to inform you that your delivery under reference <strong>{{ document.reference }}</strong> has been dispatched on {{ document.date }} and is in transit.</p><p style="margin: 0 0 12px 0;">The delivery receipt and inspection manifest are attached for your records.</p>',
                    'document_summary' => [
                        'Delivery Note Ref' => '{{ document.reference }}',
                        'Dispatch Date' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Your delivery under reference {{ document.reference }} was dispatched on {{ document.date }} and is currently in transit.\n\nPlease find delivery note attached.",
                ],
                'ar' => [
                    'name' => 'إشعار إرسال وشحن (AR)',
                    'subject' => '{{ company.name }} — إشعار شحن وتسليم رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">يسرنا إبلاغكم بأن شحنتكم المرتبطة بوصل التسليم رقم <strong>{{ document.reference }}</strong> قد انطلقت بتاريخ {{ document.date }} وهي في طريقها إليكم.</p><p style="margin: 0 0 12px 0;">تجدون وصل التسليم وبيان الشحن مرفقين بهذه الرسالة للاستلام والفحص.</p>',
                    'document_summary' => [
                        'رقم وصل التسليم' => '{{ document.reference }}',
                        'تاريخ الشحن' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "شحنتكم المرتبطة بوصل التسليم رقم {{ document.reference }} قد انطلقت بتاريخ {{ document.date }}.\n\nوصل التسليم مرفق.",
                ],
            ],

            // ── 9. Mission Confirmation ───────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_MISSION_CONFIRMATION,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.date'],
                'fr' => [
                    'name' => 'Confirmation d\'Intervention Terrain (FR)',
                    'subject' => '{{ company.name }} — Confirmation d\'Intervention {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Nous vous confirmons la programmation de l\'équipe technique pour la mission <strong>{{ document.reference }}</strong> prévue le <strong>{{ document.date }}</strong>.</p><p style="margin: 0 0 12px 0;">Nos techniciens habilités prendront attache avec vos responsables de site dès leur arrivée pour l\'exécution des opérations.</p>',
                    'document_summary' => [
                        'Ordre d\'intervention' => '{{ document.reference }}',
                        'Date prévue' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Nous confirmons la programmation de la mission {{ document.reference }} prévue le {{ document.date }}.\n\nNos intervenants prendront contact avec vos équipes dès leur arrivée.",
                ],
                'en' => [
                    'name' => 'Field Mission Confirmation (EN)',
                    'subject' => '{{ company.name }} — Service Mission Confirmation {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">We confirm the scheduling of field service mission <strong>{{ document.reference }}</strong> on <strong>{{ document.date }}</strong>.</p><p style="margin: 0 0 12px 0;">Our certified field specialists will report to your facility supervisor upon arrival.</p>',
                    'document_summary' => [
                        'Mission Reference' => '{{ document.reference }}',
                        'Scheduled Date' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "We confirm the scheduling of service mission {{ document.reference }} on {{ document.date }}.\n\nOur field specialists will report to your facility supervisor upon arrival.",
                ],
                'ar' => [
                    'name' => 'تأكيد مهمة فنية ميدانية (AR)',
                    'subject' => '{{ company.name }} — تأكيد مهمة التدخل الفني رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">نؤكد لكم برمجة الفريق الفني المختص للمهمة الميدانية رقم <strong>{{ document.reference }}</strong> المقررة بتاريخ <strong>{{ document.date }}</strong>.</p><p style="margin: 0 0 12px 0;">سيتواصل خبراؤنا الفنيون المعتمدون مع مسؤولي موقعكم فور وصولهم لبدء تنفيذ الأعمال.</p>',
                    'document_summary' => [
                        'رقم المهمة الميدانية' => '{{ document.reference }}',
                        'التاريخ المبرمج' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "نؤكد لكم برمجة الفريق الفني للمهمة رقم {{ document.reference }} المقررة بتاريخ {{ document.date }}.\n\nسيتواصل خبراؤنا مع مسؤوليكم فور وصولهم.",
                ],
            ],

            // ── 10. Maintenance Notification ──────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_MAINTENANCE_NOTIFICATION,
                'variables' => ['recipient.name', 'company.name', 'document.reference', 'document.date'],
                'fr' => [
                    'name' => 'Notification de Maintenance (FR)',
                    'subject' => '{{ company.name }} — Rapport de Maintenance {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Nous vous informons de la finalisation avec succès de l\'opération de maintenance <strong>{{ document.reference }}</strong> réalisée le {{ document.date }}.</p><p style="margin: 0 0 12px 0;">Tous les tests de conformité ont été validés. Le rapport technique détaillé est joint à ce courriel.</p>',
                    'document_summary' => [
                        'Rapport' => '{{ document.reference }}',
                        'Date de réalisation' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "L'opération de maintenance {{ document.reference }} a été réalisée avec succès le {{ document.date }}.\n\nLe rapport technique détaillé est joint.",
                ],
                'en' => [
                    'name' => 'Maintenance Notification (EN)',
                    'subject' => '{{ company.name }} — Maintenance Service Report {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">We are pleased to inform you that maintenance operation <strong>{{ document.reference }}</strong> conducted on {{ document.date }} has been successfully completed.</p><p style="margin: 0 0 12px 0;">All functional inspections and tests have passed. The complete technical report is attached.</p>',
                    'document_summary' => [
                        'Report Reference' => '{{ document.reference }}',
                        'Completion Date' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Maintenance operation {{ document.reference }} conducted on {{ document.date }} has been successfully completed.\n\nPlease find technical report attached.",
                ],
                'ar' => [
                    'name' => 'تقرير وإشعار صيانة (AR)',
                    'subject' => '{{ company.name }} — تقرير الصيانة الفنية رقم {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">يسرنا إفادتكم بإتمام أعمال الصيانة الفنية رقم <strong>{{ document.reference }}</strong> المنفذة بتاريخ {{ document.date }} بنجاح تام.</p><p style="margin: 0 0 12px 0;">تم اجتياز جميع اختبارات السلامة والكفاءة التشغيلية بنجاح. نرفق لكم طيه التقرير الفني المعتمد.</p>',
                    'document_summary' => [
                        'رقم تقرير الصيانة' => '{{ document.reference }}',
                        'تاريخ التنفيذ' => '{{ document.date }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "تم إنجاز أعمال الصيانة رقم {{ document.reference }} بتاريخ {{ document.date }} بنجاح تام.\n\nالتقرير الفني مرفق.",
                ],
            ],

            // ── 11. System Notification ───────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_SYSTEM_NOTIFICATION,
                'variables' => ['recipient.name', 'company.name', 'document.reference'],
                'fr' => [
                    'name' => 'Notification Système (FR)',
                    'subject' => '{{ company.name }} — Notification Système : {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Un événement opérationnel a été consigné sur votre espace <strong>{{ company.name }}</strong> concernant la référence <strong>{{ document.reference }}</strong>.</p><p style="margin: 0 0 12px 0;">Cet avis est émis automatiquement par la plateforme WAVE PLUS. Aucune action n\'est requise si la situation a déjà été traitée.</p>',
                    'document_summary' => [
                        'Référence Système' => '{{ document.reference }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Un événement opérationnel a été consigné sur votre espace {{ company.name }} concernant la référence {{ document.reference }}.\n\nCeci est un message automatique.",
                ],
                'en' => [
                    'name' => 'System Notification (EN)',
                    'subject' => '{{ company.name }} — System Event: {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">A system event has been recorded on your <strong>{{ company.name }}</strong> workspace regarding reference <strong>{{ document.reference }}</strong>.</p><p style="margin: 0 0 12px 0;">This notification is dispatched automatically by the WAVE PLUS platform. No action is required if already resolved.</p>',
                    'document_summary' => [
                        'System Event Ref' => '{{ document.reference }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "A system event has been recorded on your {{ company.name }} workspace regarding reference {{ document.reference }}.\n\nThis is an automated notification.",
                ],
                'ar' => [
                    'name' => 'إشعار نظام آلي (AR)',
                    'subject' => '{{ company.name }} — تنبيه آلي من النظام : {{ document.reference }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">تم تسجيل حدث تشغيلي جديد في مساحتكم لدى <strong>{{ company.name }}</strong> يتعلق بالمرجع <strong>{{ document.reference }}</strong>.</p><p style="margin: 0 0 12px 0;">هذا الإشعار صادر آلياً من منصة WAVE PLUS. لا يتطلب هذا الإشعار أي إجراء إذا تمت تسوية الأمر مسبقاً.</p>',
                    'document_summary' => [
                        'مرجع النظام' => '{{ document.reference }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "تم تسجيل حدث تشغيلي جديد في مساحتكم لدى {{ company.name }} يتعلق بالمرجع {{ document.reference }}.\n\nإشعار آلي من المنصة.",
                ],
            ],

            // ── 12. Client Profile ─────────────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_CLIENT_PROFILE,
                'variables' => ['recipient.name', 'company.name', 'client.name', 'client.code', 'sender.name'],
                'fr' => [
                    'name' => 'Fiche Partenaire Client (FR)',
                    'subject' => '{{ company.name }} — Dossier Partenaire Client : {{ client.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Veuillez trouver ci-joint la fiche récapitulative de votre compte partenaire <strong>{{ client.name }}</strong> (Réf. {{ client.code }}).</p><p style="margin: 0 0 12px 0;">Ce document récapitule vos informations d\'identification, adresses et contacts enregistrés auprès de <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">Notre équipe reste à votre entière disposition pour tout complément d\'information.</p>',
                    'document_summary' => [
                        'Partenaire' => '{{ client.name }}',
                        'Code Client' => '{{ client.code }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Veuillez trouver ci-joint la fiche récapitulative de votre compte partenaire {{ client.name }} (Réf. {{ client.code }}).\n\nCe document récapitule vos informations enregistrées auprès de {{ company.name }}.",
                ],
                'en' => [
                    'name' => 'Client Partner Profile (EN)',
                    'subject' => '{{ company.name }} — Client Partner Profile : {{ client.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Please find attached the profile summary for your partner account <strong>{{ client.name }}</strong> (Ref. {{ client.code }}).</p><p style="margin: 0 0 12px 0;">This document contains your verified identification details, addresses, and contacts on file with <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">Our team remains at your disposal for any further inquiries.</p>',
                    'document_summary' => [
                        'Partner' => '{{ client.name }}',
                        'Client Code' => '{{ client.code }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Please find attached the profile summary for your partner account {{ client.name }} (Ref. {{ client.code }}).\n\nThis document contains your verified details on file with {{ company.name }}.",
                ],
                'ar' => [
                    'name' => 'ملف الشريك التجاري - عميل (AR)',
                    'subject' => '{{ company.name }} — ملف العميل التجاري : {{ client.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">تجدون طيه الملف التعريفي المعتمد لحسابكم <strong>{{ client.name }}</strong> (المرجع {{ client.code }}).</p><p style="margin: 0 0 12px 0;">يتضمن هذا المستند ملخص البيانات والعناوين وجهات الاتصال المسجلة لدى <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">فريق عملنا في خدمتكم لأي استفسار أو متابعة.</p>',
                    'document_summary' => [
                        'اسم الشريك' => '{{ client.name }}',
                        'رمز العميل' => '{{ client.code }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "تجدون طيه الملف التعريفي المعتمد لحسابكم {{ client.name }} (المرجع {{ client.code }}).\n\nبياناتكم المعتمدة لدى {{ company.name }}.",
                ],
            ],

            // ── 13. Supplier Profile ───────────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_SUPPLIER_PROFILE,
                'variables' => ['recipient.name', 'company.name', 'supplier.name', 'supplier.code', 'sender.name'],
                'fr' => [
                    'name' => 'Fiche Partenaire Fournisseur (FR)',
                    'subject' => '{{ company.name }} — Dossier Partenaire Fournisseur : {{ supplier.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Veuillez trouver ci-joint la fiche partenaire relative à votre compte fournisseur <strong>{{ supplier.name }}</strong> (Réf. {{ supplier.code }}).</p><p style="margin: 0 0 12px 0;">Ce document récapitule vos informations administratives et logistiques enregistrées dans notre système <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">Nous vous remercions pour votre collaboration continue.</p>',
                    'document_summary' => [
                        'Fournisseur' => '{{ supplier.name }}',
                        'Code Fournisseur' => '{{ supplier.code }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Veuillez trouver ci-joint la fiche partenaire de votre compte fournisseur {{ supplier.name }} (Réf. {{ supplier.code }}).\n\nMerci pour votre collaboration avec {{ company.name }}.",
                ],
                'en' => [
                    'name' => 'Supplier Partner Profile (EN)',
                    'subject' => '{{ company.name }} — Supplier Partner Profile : {{ supplier.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Please find attached the partner summary for your supplier account <strong>{{ supplier.name }}</strong> (Ref. {{ supplier.code }}).</p><p style="margin: 0 0 12px 0;">This record details the administrative and logistical information registered with <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">Thank you for your ongoing partnership.</p>',
                    'document_summary' => [
                        'Supplier' => '{{ supplier.name }}',
                        'Supplier Code' => '{{ supplier.code }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Please find attached the partner summary for your supplier account {{ supplier.name }} (Ref. {{ supplier.code }}).\n\nThank you for your ongoing partnership with {{ company.name }}.",
                ],
                'ar' => [
                    'name' => 'ملف الشريك التجاري - مورد (AR)',
                    'subject' => '{{ company.name }} — ملف المورد المعتمد : {{ supplier.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">تجدون طيه المستند التعريفي الخاص بحسابكم كمورد معتمد <strong>{{ supplier.name }}</strong> (المرجع {{ supplier.code }}).</p><p style="margin: 0 0 12px 0;">يتضمن الملف بيانات التسجيل والتعاملات اللوجستية المسجلة لدى <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">شاكرين لكم حسن تعاونكم الدائم.</p>',
                    'document_summary' => [
                        'اسم المورد' => '{{ supplier.name }}',
                        'رمز المورد' => '{{ supplier.code }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "تجدون طيه المستند التعريفي الخاص بحسابكم كمورد معتمد {{ supplier.name }} (المرجع {{ supplier.code }}).\n\nشاكرين لكم تعاونكم مع {{ company.name }}.",
                ],
            ],

            // ── 14. Organisation Profile ───────────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_ORGANISATION_PROFILE,
                'variables' => ['recipient.name', 'company.name', 'organisation.name', 'organisation.code', 'sender.name'],
                'fr' => [
                    'name' => 'Fiche Organisation Partenaire (FR)',
                    'subject' => '{{ company.name }} — Fiche Organisation : {{ organisation.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Veuillez trouver ci-joint la fiche récapitulative de l\'organisation <strong>{{ organisation.name }}</strong> (Réf. {{ organisation.code }}).</p><p style="margin: 0 0 12px 0;">Ce document reprend l\'ensemble des coordonnées et informations de rattachement enregistrées auprès de <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">Nous restons à votre entière disposition pour tout échange.</p>',
                    'document_summary' => [
                        'Organisation' => '{{ organisation.name }}',
                        'Identifiant' => '{{ organisation.code }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Veuillez trouver ci-joint la fiche récapitulative de l'organisation {{ organisation.name }} (Réf. {{ organisation.code }}).\n\nEnregistré auprès de {{ company.name }}.",
                ],
                'en' => [
                    'name' => 'Organization Partner Profile (EN)',
                    'subject' => '{{ company.name }} — Organization Profile : {{ organisation.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Please find attached the official profile summary for organization <strong>{{ organisation.name }}</strong> (Ref. {{ organisation.code }}).</p><p style="margin: 0 0 12px 0;">This document reflects the verified institutional details registered with <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">We remain available for any further coordination.</p>',
                    'document_summary' => [
                        'Organization' => '{{ organisation.name }}',
                        'Reference' => '{{ organisation.code }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Please find attached the official profile summary for organization {{ organisation.name }} (Ref. {{ organisation.code }}).\n\nRegistered with {{ company.name }}.",
                ],
                'ar' => [
                    'name' => 'ملف الشريك - هيئة ومؤسسة (AR)',
                    'subject' => '{{ company.name }} — ملف الهيئة والمؤسسة : {{ organisation.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">تجدون طيه المستند التعريفي الخاص بالمؤسسة <strong>{{ organisation.name }}</strong> (المرجع {{ organisation.code }}).</p><p style="margin: 0 0 12px 0;">يوثق هذا المستند بيانات الاعتماد والجهات الرسمية المسجلة لدى <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">نحن رهن إشارتكم لأي تنسيق مشترك.</p>',
                    'document_summary' => [
                        'المؤسسة / الهيئة' => '{{ organisation.name }}',
                        'رمز التسجيل' => '{{ organisation.code }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "تجدون طيه المستند التعريفي الخاص بالمؤسسة {{ organisation.name }} (المرجع {{ organisation.code }}).\n\nمسجل لدى {{ company.name }}.",
                ],
            ],

            // ── 15. Service Provider Profile ───────────────────────────────
            [
                'category' => EmailTemplate::CATEGORY_SERVICE_PROVIDER_PROFILE,
                'variables' => ['recipient.name', 'company.name', 'service_provider.name', 'service_provider.code', 'sender.name'],
                'fr' => [
                    'name' => 'Fiche Prestataire de Services (FR)',
                    'subject' => '{{ company.name }} — Fiche Prestataire : {{ service_provider.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Bonjour {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Veuillez trouver ci-joint la fiche partenaire de votre compte prestataire <strong>{{ service_provider.name }}</strong> (Réf. {{ service_provider.code }}).</p><p style="margin: 0 0 12px 0;">Ce document récapitule vos compétences, coordonnées et informations contractuelles enregistrées auprès de <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">Nous vous remercions pour votre professionnalisme et votre engagement opérationnel.</p>',
                    'document_summary' => [
                        'Prestataire' => '{{ service_provider.name }}',
                        'Code Prestataire' => '{{ service_provider.code }}',
                    ],
                    'greeting_text' => 'Bonjour {{ recipient.name }},',
                    'content_text' => "Veuillez trouver ci-joint la fiche partenaire de votre compte prestataire {{ service_provider.name }} (Réf. {{ service_provider.code }}).\n\nEnregistré auprès de {{ company.name }}.",
                ],
                'en' => [
                    'name' => 'Service Provider Profile (EN)',
                    'subject' => '{{ company.name }} — Service Provider Profile : {{ service_provider.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">Dear {{ recipient.name }},</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">Please find attached the official summary for your service provider account <strong>{{ service_provider.name }}</strong> (Ref. {{ service_provider.code }}).</p><p style="margin: 0 0 12px 0;">This document reflects the certified operational credentials and contact records on file with <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">Thank you for your dedicated service and collaboration.</p>',
                    'document_summary' => [
                        'Service Provider' => '{{ service_provider.name }}',
                        'Provider Code' => '{{ service_provider.code }}',
                    ],
                    'greeting_text' => 'Dear {{ recipient.name }},',
                    'content_text' => "Please find attached the summary for your service provider account {{ service_provider.name }} (Ref. {{ service_provider.code }}).\n\nRegistered with {{ company.name }}.",
                ],
                'ar' => [
                    'name' => 'ملف الشريك - مقدم خدمات (AR)',
                    'subject' => '{{ company.name }} — ملف مقدم الخدمات المعتمد : {{ service_provider.name }}',
                    'greeting' => '<p style="margin: 0 0 14px 0; font-size: 15px; font-weight: 600; color: #0f172a;">تحية طيبة {{ recipient.name }}،</p>',
                    'content_html' => '<p style="margin: 0 0 12px 0;">تجدون طيه المستند التعريفي المعتمد لحسابكم كمقدم خدمات <strong>{{ service_provider.name }}</strong> (المرجع {{ service_provider.code }}).</p><p style="margin: 0 0 12px 0;">يتضمن الملف بيانات الاعتماد والاتصال والأنشطة المسجلة لدى <strong>{{ company.name }}</strong>.</p><p style="margin: 0;">شاكرين لكم تفانيكم المهني وتنسيقكم المتميز.</p>',
                    'document_summary' => [
                        'مقدم الخدمات' => '{{ service_provider.name }}',
                        'رمز الاعتماد' => '{{ service_provider.code }}',
                    ],
                    'greeting_text' => 'تحية طيبة {{ recipient.name }}،',
                    'content_text' => "تجدون طيه المستند التعريفي المعتمد لحسابكم كمقدم خدمات {{ service_provider.name }} (المرجع {{ service_provider.code }}).\n\nشاكرين لكم تعاونكم مع {{ company.name }}.",
                ],
            ],
        ];

        foreach ($templateDefinitions as $def) {
            $cat = $def['category'];
            $vars = $def['variables'];

            foreach (['fr', 'en', 'ar'] as $lang) {
                $langData = $def[$lang];
                $slug = "{$cat}_{$lang}";

                $bodyHtml = EmailLayoutBuilder::buildHtml([
                    'title' => $langData['name'],
                    'greeting' => $langData['greeting'],
                    'content_html' => $langData['content_html'],
                    'document_summary' => $langData['document_summary'] ?? [],
                    'language' => $lang,
                ]);

                $bodyText = EmailLayoutBuilder::buildText([
                    'greeting' => $langData['greeting_text'],
                    'content_text' => $langData['content_text'],
                    'document_summary' => $langData['document_summary'] ?? [],
                    'language' => $lang,
                ]);

                EmailTemplate::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'category' => $cat,
                        'language' => $lang,
                        'name' => $langData['name'],
                        'subject' => $langData['subject'],
                        'body_html' => $bodyHtml,
                        'body_text' => $bodyText,
                        'variables' => $vars,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
