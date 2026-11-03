/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — Inventory & Warehouse Module Navigation
 * ──────────────────────────────────────────────────────────────
 */

export default [
  { heading: 'Inventory' },
  {
    title: 'Dashboard',
    to: 'inventory-warehouse',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
  {
    title: 'Stock Items',
    to: 'inventory-stock-items-list',
    icon: { icon: 'tabler-packages' },
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
  {
    title: 'Stock Movements',
    to: 'inventory-movements-list',
    icon: { icon: 'tabler-transfer' },
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
  {
    title: 'Stock Lots',
    to: 'inventory-lots-list',
    icon: { icon: 'tabler-box-multiple' },
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
  { heading: 'Warehouse' },
  {
    title: 'Warehouse',
    to: 'inventory-warehouse-sites-list',
    icon: { icon: 'tabler-building-warehouse' },
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
]
