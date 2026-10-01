export function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}

export function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    return `${size.toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}

export function statusBadge(status) {
    const colors = {
        active: 'bg-emerald-100 text-emerald-700',
        inactive: 'bg-slate-100 text-slate-700',
        archived: 'bg-amber-100 text-amber-700',
        open: 'bg-emerald-100 text-emerald-700',
        closed: 'bg-slate-100 text-slate-700',
        completed: 'bg-emerald-100 text-emerald-700',
        failed: 'bg-red-100 text-red-700',
        pending: 'bg-yellow-100 text-yellow-700',
        synced: 'bg-emerald-100 text-emerald-700',
        admin: 'bg-indigo-100 text-indigo-700',
        teacher: 'bg-emerald-100 text-emerald-700',
        student: 'bg-blue-100 text-blue-700',
    };
    return colors[status] || 'bg-slate-100 text-slate-700';
}

export const ROLES = [
    { value: 'admin', label: 'Administrator' },
    { value: 'teacher', label: 'Teacher' },
    { value: 'student', label: 'Student' },
];

export const USER_STATUSES = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'archived', label: 'Archived' },
];
