// ─────────────────────────────────────────────────────────────────────────────
// Shared Components — WAVE PLUS
// ─────────────────────────────────────────────────────────────────────────────

// Existing
export { default as AppSnackbar } from './AppSnackbar.vue'
export { default as AppFab } from './AppFab.vue'

// Fab system (FAB components + shared types)
export { default as AppFabSpeedDial } from './fab/AppFabSpeedDial.vue'
export { default as AppQuickActionsSheet } from './fab/AppQuickActionsSheet.vue'
export { default as AppNestedActionsSheet } from './fab/AppNestedActionsSheet.vue'
export * from './fab/fabTypes'

// Nav (bottom navigation & journal section navigation)
export { default as AppBottomNav } from './nav/AppBottomNav.vue'
export { default as WpJournalNav } from './nav/WpJournalNav.vue'
export { default as WpSectionNav } from './nav/WpJournalNav.vue'
export { default as AppBreadcrumb } from './AppBreadcrumb.vue'
export * from './nav/bottomNavTypes'

// Data
export { default as WpDataTable } from './data/WpDataTable.vue'
export { default as WpDataTableCard } from './data/WpDataTableCard.vue'
export { default as WpMobileCard } from './data/WpMobileCard.vue'
export { default as WpResponsiveList } from './data/WpResponsiveList.vue'
export { default as WpPagination } from './data/WpPagination.vue'
export { default as WpRowActions } from './data/WpRowActions.vue'
export { default as WpBulkActions } from './data/WpBulkActions.vue'
export { default as WpStatusChip } from './data/WpStatusChip.vue'
export { default as WpEntityCard } from './data/WpEntityCard.vue'
export { default as WpEntityCardGrid } from './data/WpEntityCardGrid.vue'
export { default as WpEntityCollection } from './data/WpEntityCollection.vue'
export { default as MultiLangField } from './data/MultiLangField.vue'
export { default as WpSimpleTable } from './data/WpSimpleTable.vue'

// Feedback
export { default as WpEmptyState } from './feedback/WpEmptyState.vue'
export { default as WpLoadingState } from './feedback/WpLoadingState.vue'
export { default as WpErrorBanner } from './feedback/WpErrorBanner.vue'

// Filters
export { default as WpSearchField } from './filters/WpSearchField.vue'
export { default as WpFilterBar } from './filters/WpFilterBar.vue'
export { default as WpActiveFilters } from './filters/WpActiveFilters.vue'
export { default as WpFilterDialog } from './filters/WpFilterDialog.vue'

// Layout
export { default as WpPageToolbar } from './layout/WpPageToolbar.vue'
export { default as WpPageHeader } from './layout/WpPageHeader.vue'
export { default as WpKpiCard } from './layout/WpKpiCard.vue'
export { default as WpKpiGroup } from './layout/WpKpiGroup.vue'
export { default as WpEntityHeader } from './layout/WpEntityHeader.vue'
export { default as WpSectionCard } from './layout/WpSectionCard.vue'
export { default as WpInfoItem } from './layout/WpInfoItem.vue'
export { default as AppHybridTabs } from './layout/AppHybridTabs.vue'
export { default as WpActionFooter } from './layout/WpActionFooter.vue'
export { default as WpSectionHeader } from './layout/WpSectionHeader.vue'
export { default as WpEditPageLayout } from './layout/WpEditPageLayout.vue'

// Dialogs
export { default as WpDetailDialog } from './dialogs/WpDetailDialog.vue'
export { default as WpAddressViewDialog } from './dialogs/WpAddressViewDialog.vue'
export { default as WpContactViewDialog } from './dialogs/WpContactViewDialog.vue'
export { default as WpFormDialog } from './dialogs/WpFormDialog.vue'
export { default as WpDeleteDialog } from './dialogs/WpDeleteDialog.vue'
export { default as WpUnsavedChangesDialog } from './dialogs/WpUnsavedChangesDialog.vue'
export { default as WpConfirmDialog } from './dialogs/WpConfirmDialog.vue'
export { default as WpZoomDialog } from './dialogs/WpZoomDialog.vue'
export { default as WpExportDialog } from './dialogs/WpExportDialog.vue'

// Wizard Family
export { default as WpWizardDialog } from './wizard/WpWizardDialog.vue'
export { default as WpWizardStepper } from './wizard/WpWizardStepper.vue'
export { default as WpWizardReview } from './wizard/WpWizardReview.vue'

// Search & Select Family
export { default as WpSearchSelectDialog } from './search/WpSearchSelectDialog.vue'
export { default as WpSearchSelectResults } from './search/WpSearchSelectResults.vue'
export { default as WpSearchSelectFilters } from './search/WpSearchSelectFilters.vue'

// Business Information Cards
export { default as WpAddressSiteCard } from './cards/WpAddressSiteCard.vue'
export { default as WpPartnerCard } from './cards/WpPartnerCard.vue'
export { default as WpPartnerContactsCard } from './cards/WpPartnerContactsCard.vue'
export { default as WpDocumentLinesCard } from './cards/WpDocumentLinesCard.vue'
export { default as WpStatusCard } from './cards/WpStatusCard.vue'

// Form Primitives
export { default as WpFormField } from './forms/WpFormField.vue'
export { default as WpRemoteAutocomplete } from './forms/WpRemoteAutocomplete.vue'

// Feedback Primitives
export { default as WpStateBlock } from './feedback/WpStateBlock.vue'

// Communication
export { default as WpEmailDialog } from './communication/WpEmailDialog.vue'
export { default as WpRecordEmailHistory } from './communication/WpRecordEmailHistory.vue'

// Media foundation (camera / scanner / documents / images / branding)
export { default as WpAttachmentDialog } from './documents/WpAttachmentDialog.vue'
export { default as WpCameraCapture } from './camera/WpCameraCapture.vue'
export { default as WpQrScannerDialog } from './scanner/WpQrScannerDialog.vue'
export { default as WpDocumentDialog } from './documents/WpDocumentDialog.vue'
export { default as WpDocumentScanner } from './documents/WpDocumentScanner.vue'
export { default as WpScanCropEditor } from './documents/WpScanCropEditor.vue'
export { default as WpDocumentList } from './documents/WpDocumentList.vue'
export { default as WpAttachmentList } from './documents/WpDocumentList.vue'
export { default as WpAttachmentField } from './documents/WpAttachmentField.vue'
export { default as WpImageDialog } from './media/WpImageDialog.vue'
export { default as WpAvatarDialog } from './media/WpAvatarDialog.vue'
export { default as WpMediaGallery } from './media/WpMediaGallery.vue'
export { default as WpMediaViewerDialog } from './media/WpMediaViewerDialog.vue'
export { default as WpEntityLogo } from './branding/WpEntityLogo.vue'
export { default as WpLogoDialog } from './branding/WpLogoDialog.vue'
