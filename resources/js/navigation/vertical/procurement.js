/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Procurement Module Navigation
 * ──────────────────────────────────────────────────────────────
 */

export default [
  {
    title: 'Dashboard',
    
    to: 'supply-chain-procurement',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
  {
    title: 'Internal Purchase Request (IPR)',
    to: 'procurement-ipr-list',
    icon: { icon: 'tabler-file-plus' },
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
  {
    title: 'Request for Quotation (RFQ)',
    to: 'procurement-rfq-list',
    icon: { icon: 'tabler-send' },
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
  {
    title: 'Purchase Order (PO)',
    to: 'procurement-po-list',
    icon: { icon: 'tabler-clipboard-check' },
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
  {
    title: 'Goods Receipt Notes (GRN)',
    to: 'procurement-grn-list',
    icon: { icon: 'tabler-package-import' },
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
]
