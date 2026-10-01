import PortalLayout from '@/Layouts/PortalLayout';
const navItems = [
 { href:'/dashboard', label:'Overview', icon:'dashboard' }, { href:'/admin/users', label:'Users', icon:'users' },
 { href:'/admin/academic-years', label:'Academic Years', icon:'assignment' }, { href:'/admin/subjects', label:'Subjects', icon:'book' },
 { href:'/admin/classes', label:'Classes', icon:'class' }, { href:'/admin/announcements', label:'Announcements', icon:'bell' },
 { href:'/admin/reports', label:'Reports', icon:'chart' }, { href:'/admin/audit-logs', label:'Audit Logs', icon:'shield' },
 { href:'/admin/sync', label:'Sync Monitoring', icon:'sync' }, { href:'/admin/files', label:'File Management', icon:'file' },
 { href:'/admin/backups', label:'Backups', icon:'shield' }, { href:'/admin/settings', label:'System Settings', icon:'settings' },
];
export default function AdminLayout({ title, children }) { return <PortalLayout title={title} eyebrow="School administration" navItems={navItems} portal="admin">{children}</PortalLayout>; }
