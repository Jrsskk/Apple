import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import FlashMessage from '@/Components/FlashMessage';
import { Icon } from '@/Components/PortalUI';

export default function PortalLayout({ title, eyebrow, navItems, portal = 'admin', children }) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const path = window.location.pathname;
    const initials = `${auth?.user?.first_name?.[0] || ''}${auth?.user?.last_name?.[0] || ''}` || 'ES';
    const active = (href) => href === '/dashboard' ? path === href : path.startsWith(href);
    const profileHref = `/${portal}/profile`;
    const filteredNav = useMemo(
        () => navItems.filter(item => item.label.toLowerCase().includes(query.toLowerCase().trim())),
        [navItems, query],
    );
    const today = new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' }).format(new Date());

    useEffect(() => setOpen(false), [path]);
    useEffect(() => {
        const close = event => event.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', close);
        return () => window.removeEventListener('keydown', close);
    }, []);

    return <div className={`portal-shell portal-${portal} min-h-screen`}>
        {open && <button aria-label="Close navigation" className="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm lg:hidden" onClick={() => setOpen(false)} />}
        <aside aria-label="Portal sidebar" className={`portal-sidebar fixed inset-y-0 left-0 z-50 flex w-[86vw] max-w-72 flex-col border-r border-white/10 text-white shadow-2xl transition-transform duration-300 lg:w-72 lg:translate-x-0 lg:shadow-none ${open ? 'translate-x-0' : '-translate-x-full'}`}>
            <div className="flex h-20 items-center gap-3 border-b border-white/10 px-5">
                <span className="portal-logo grid h-10 w-10 place-items-center rounded-xl text-lg font-black shadow-lg">E</span>
                <div><Link href="/dashboard" className="text-lg font-bold tracking-tight">EduSync</Link><p className="text-xs text-blue-200/70">{eyebrow}</p></div>
                <button onClick={() => setOpen(false)} className="ml-auto rounded-lg p-2 text-slate-400 hover:bg-white/10 lg:hidden" aria-label="Close navigation">&times;</button>
            </div>
            <div className="px-4 pt-4">
                <label className="relative block"><span className="sr-only">Filter navigation</span><Icon name="search" className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-500" /><input value={query} onChange={event => setQuery(event.target.value)} placeholder="Find a page…" className="w-full rounded-xl border-white/10 bg-white/5 py-2 pl-9 pr-3 text-sm text-white placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400" /></label>
            </div>
            <nav className="flex-1 space-y-1 overflow-y-auto p-4 [scrollbar-width:thin]" aria-label="Primary navigation">
                <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Workspace</p>
                {filteredNav.map(item => <Link key={item.href} href={item.href} aria-current={active(item.href) ? 'page' : undefined} className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition duration-200 ${active(item.href) ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-300 hover:translate-x-0.5 hover:bg-white/10 hover:text-white'}`}><span className={`grid h-7 w-7 place-items-center rounded-lg ${active(item.href) ? 'bg-slate-100' : 'bg-white/5'}`}><Icon name={item.icon} className="h-[17px] w-[17px] transition group-hover:scale-110" /></span>{item.label}{item.badge != null && <span className="ml-auto rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-bold text-slate-950">{item.badge}</span>}</Link>)}
                {!filteredNav.length && <p className="px-3 py-6 text-center text-sm text-slate-500">No matching pages</p>}
            </nav>
            <div className="border-t border-white/10 p-4"><Link href={profileHref} className="mb-2 flex items-center gap-3 rounded-xl p-2 transition hover:bg-white/10"><span className="portal-avatar grid h-9 w-9 place-items-center rounded-full text-xs font-bold">{initials}</span><span className="min-w-0"><span className="block truncate text-sm font-medium">{auth?.user?.full_name}</span><span className="block text-xs capitalize text-slate-400">{auth?.user?.role?.value || auth?.user?.role}</span></span></Link><Link href="/logout" method="post" as="button" className="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-400 transition hover:bg-white/10 hover:text-white">Sign out</Link></div>
        </aside>
        <div className="min-w-0 lg:pl-72">
            <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/85 px-4 shadow-sm shadow-slate-200/30 backdrop-blur-xl sm:h-20 sm:px-8">
                <div className="flex min-w-0 items-center gap-3"><button type="button" className="rounded-xl border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-100 lg:hidden" onClick={() => setOpen(true)} aria-label="Open navigation"><svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 6h16M4 12h16M4 18h16" /></svg></button><div className="min-w-0"><p className="hidden text-xs font-semibold uppercase tracking-widest text-slate-400 sm:block">{eyebrow}</p><h1 className="truncate text-lg font-bold tracking-tight text-slate-900 sm:text-xl">{title}</h1></div></div>
                <div className="flex items-center gap-3"><div className="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-500 md:flex"><span className="h-2 w-2 animate-soft-pulse rounded-full bg-emerald-500" />Live · {today}</div><span className="hidden text-sm text-slate-500 xl:block">Welcome, <b className="text-slate-800">{auth?.user?.first_name}</b></span><Link href={profileHref} className="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-blue-600 to-cyan-500 text-xs font-bold text-white shadow-sm ring-2 ring-white transition hover:scale-105" aria-label="Open profile">{initials}</Link></div>
            </header>
            <main className="mx-auto max-w-[1600px] animate-fade-up p-4 sm:p-6 lg:p-8"><FlashMessage />{children}</main>
        </div>
    </div>;
}
