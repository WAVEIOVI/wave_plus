/**
 * ──────────────────────────────────────────────────────────────
 *  VUEXY ACL — People & Skills Module Navigation (Vertical)
 * ──────────────────────────────────────────────────────────────
 */

export default [
  {
    title: 'Dashboard',
    to: 'people-skills',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
  },
  {
    title: 'Organization',
    icon: { icon: 'tabler-building-community' },
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
    children: [
      {
        title: 'Employees Directory',
        to: 'people-skills-employees-list',
        icon: { icon: 'tabler-address-book' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
      {
        title: 'Departments & Positions',
        to: 'people-skills-departments-list',
        icon: { icon: 'tabler-building-community' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
      {
        title: 'Work Teams',
        to: 'people-skills-teams-list',
        icon: { icon: 'tabler-users-group' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
    ],
  },
  {
    title: 'Planning',
    icon: { icon: 'tabler-calendar-stats' },
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
    children: [
      {
        title: 'Workforce Planner',
        to: 'people-skills-workforce-list',
        icon: { icon: 'tabler-calendar-stats' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
    ],
  },
  {
    title: 'Time & Attendance',
    icon: { icon: 'tabler-clock' },
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
    children: [
      {
        title: 'Time & Attendance',
        to: 'people-skills-attendance-list',
        icon: { icon: 'tabler-clock' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
      {
        title: 'Time Off & Leave',
        to: 'people-skills-time-off-list',
        icon: { icon: 'tabler-calendar-event' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
    ],
  },
  
  {
    title: 'Fleet',
    icon: { icon: 'tabler-truck' },
    module: 'people-skills',
    action: 'read',
    subject: 'Fleet',
    children: [
      {
        title: 'Vehicles',
        to: 'people-skills-vehicles-list',
        icon: { icon: 'tabler-truck' },
        module: 'people-skills',
        action: 'read',
        subject: 'Fleet',
      },
      {
        title: 'Approved Drivers',
        to: 'people-skills-approved-drivers-list',
        icon: { icon: 'tabler-license' },
        module: 'people-skills',
        action: 'read',
        subject: 'Fleet',
      },
    ],
  },
  {
    title: 'Talent',
    icon: { icon: 'tabler-user-search' },
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
    children: [
      {
        title: 'Recruitment & Hiring',
        to: 'people-skills-recruitment-list',
        icon: { icon: 'tabler-user-search' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
      {
        title: 'Performance Reviews',
        to: 'people-skills-performance-appraisals',
        icon: { icon: 'tabler-chart-bar' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
      {
        title: 'Training & Programs',
        to: 'people-skills-training-list',
        icon: { icon: 'tabler-school' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
      {
        title: 'Skills & Certifications',
        to: 'people-skills-skills-list',
        icon: { icon: 'tabler-certificate' },
        module: 'people-skills',
        action: 'read',
        subject: 'PeopleSkills',
      },
    ],
  },
]
