import PortalLayout from '@/Layouts/PortalLayout';

const navItems = [
    { href: '/dashboard', label: 'Overview', icon: 'dashboard' },
    { href: '/teacher/classes', label: 'My Classes', icon: 'class' },
    { href: '/teacher/subjects', label: 'Subjects', icon: 'book' },
    { href: '/teacher/quizzes', label: 'Quizzes', icon: 'quiz' },
    { href: '/teacher/assignments', label: 'Assignments', icon: 'assignment' },
    { href: '/teacher/materials', label: 'Learning Materials', icon: 'file' },
    { href: '/teacher/submissions', label: 'Submissions', icon: 'assignment' },
    { href: '/teacher/grades', label: 'Gradebook', icon: 'chart' },
    { href: '/teacher/analytics', label: 'Analytics', icon: 'chart' },
    { href: '/teacher/announcements', label: 'Announcements', icon: 'bell' },
    { href: '/teacher/reports', label: 'Reports', icon: 'file' },
    { href: '/teacher/sync', label: 'Sync Monitor', icon: 'sync' },
];

export default function TeacherLayout({ title, children }) { return <PortalLayout title={title} eyebrow="Teacher workspace" navItems={navItems} portal="teacher">{children}</PortalLayout>; }
