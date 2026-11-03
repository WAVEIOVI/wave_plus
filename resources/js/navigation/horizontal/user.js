export default [
  {
    title: 'User Management',
    icon: { icon: 'tabler-layout-grid-add' },
    children: [
      {
        title: 'User',
        icon: { icon: 'tabler-users' },
        to: 'user-list',
      },
      {
        title: 'Roles & Permissions',
        icon: { icon: 'tabler-settings' },
        children: [
          { title: 'Roles', to: 'user-roles' },
          { title: 'Permissions', to: 'apps-permissions' },
        ],
      },
    ],
  },
]
