import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function ViewerDashboard() {
    const [search, setSearch] = useState('');

    const links = useMemo(
        () => [
            { label: 'Public Disclosures', href: route('public.disclosures.index') },
            { label: 'Dashboard', href: route('dashboard') },
        ],
        [],
    );

    const filteredLinks = useMemo(() => {
        const keyword = search.trim().toLowerCase();
        if (!keyword) {
            return links;
        }

        return links.filter((link) => link.label.toLowerCase().includes(keyword));
    }, [links, search]);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl leading-tight font-semibold text-gray-800">Viewer Dashboard</h2>}>
            <Head title="Viewer Dashboard" />

            <div className="space-y-4 py-12">
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <label htmlFor="viewer-dashboard-filter" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                        Filter dashboard links
                    </label>
                    <input
                        id="viewer-dashboard-filter"
                        type="text"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search links"
                        className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                    />
                    <div className="mt-3 flex flex-wrap gap-2">
                        {filteredLinks.map((link) => (
                            <Link key={link.href} href={link.href} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                {link.label}
                            </Link>
                        ))}
                    </div>
                </div>
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        Transparency and read-only monitoring views will be shown here.
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





