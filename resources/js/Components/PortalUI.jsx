import { Link } from '@inertiajs/react';

export const icons = {
    dashboard: 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
    users: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87m-2-11.9a4 4 0 010 7.75',
    book: 'M4 19.5A2.5 2.5 0 016.5 17H20V4H6.5A2.5 2.5 0 004 6.5v13zm0 0A2.5 2.5 0 006.5 22H20',
    class: 'M3 4h18v14H3zM8 22h8M12 18v4M7 9h10M7 13h6',
    quiz: 'M9 11l2 2 4-4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z',
    assignment: 'M9 5h6M9 3v4m6-4v4M6 5h12a2 2 0 012 2v13H4V7a2 2 0 012-2zm2 7h8m-8 4h5',
    chart: 'M4 19V9m6 10V5m6 14v-7m4 7H2',
    settings: 'M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zm0-12v2m0 13v2m8.5-8.5h-2m-13 0h-2m15.28-6.28-1.42 1.42M6.64 17.36l-1.42 1.42m13.56 0-1.42-1.42M6.64 6.64 5.22 5.22',
    sync: 'M20 7h-5V2M4 17h5v5m10-9a7 7 0 00-12-5l-3 3m16 2-3 3a7 7 0 01-12-5',
    file: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zm0 0v6h6M8 13h8m-8 4h5',
    shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
    bell: 'M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 13h4',
    search: 'M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z',
};

export function Icon({ name, className = 'h-5 w-5' }) {
    return <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d={icons[name] || icons.dashboard} /></svg>;
}

export function StatCard({ label, value, helper, icon = 'chart', tone = 'blue', href }) {
    const tones = { blue: 'bg-blue-50 text-blue-700', green: 'bg-emerald-50 text-emerald-700', amber: 'bg-amber-50 text-amber-700', violet: 'bg-violet-50 text-violet-700', rose: 'bg-rose-50 text-rose-700', cyan: 'bg-cyan-50 text-cyan-700' };
    const body = <div className="portal-card group h-full overflow-hidden p-5"><div className="flex items-start justify-between"><div><p className="text-sm font-medium text-slate-500">{label}</p><p className="mt-2 text-3xl font-bold tracking-tight text-slate-900">{value ?? 0}</p></div><span className={`rounded-xl p-2.5 transition duration-300 group-hover:rotate-3 group-hover:scale-110 ${tones[tone] || tones.blue}`}><Icon name={icon} /></span></div>{helper && <p className="mt-3 text-xs text-slate-400">{helper}</p>}<div className="mt-4 h-1 w-10 rounded-full bg-blue-500/20 transition-all duration-300 group-hover:w-20 group-hover:bg-blue-500" /></div>;
    return href ? <Link href={href} className="block focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-2xl">{body}</Link> : body;
}

export function SectionCard({ title, subtitle, action, children, className = '' }) {
    return <section className={`portal-card ${className}`}><div className="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4"><div><h2 className="font-semibold text-slate-900">{title}</h2>{subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}</div>{action}</div><div className="p-5">{children}</div></section>;
}

export function StatusBadge({ children, tone = 'slate' }) {
    const tones = { green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', amber: 'bg-amber-50 text-amber-700 ring-amber-600/20', red: 'bg-rose-50 text-rose-700 ring-rose-600/20', blue: 'bg-blue-50 text-blue-700 ring-blue-600/20', slate: 'bg-slate-50 text-slate-600 ring-slate-500/20' };
    return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 ring-inset ${tones[tone]}`}>{children}</span>;
}

export function EmptyState({ title = 'Nothing here yet', description, action }) {
    return <div className="flex min-h-36 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center"><span className="mb-3 rounded-full bg-white p-2 text-slate-400 shadow-sm"><Icon name="file" /></span><p className="font-medium text-slate-700">{title}</p>{description && <p className="mt-1 max-w-sm text-sm text-slate-500">{description}</p>}{action}</div>;
}

export function TableShell({ children }) { return <div className="overflow-x-auto rounded-xl border border-slate-200"><table className="min-w-full divide-y divide-slate-200 text-sm">{children}</table></div>; }
export function LoadingState() { return <div className="space-y-3 animate-pulse">{[1,2,3].map(i => <div key={i} className="h-12 rounded-xl bg-slate-100" />)}</div>; }
