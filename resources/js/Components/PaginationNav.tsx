import { Link } from '@inertiajs/react';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    links: PaginationLink[];
    preserveState?: boolean;
    preserveScroll?: boolean;
};

export default function PaginationNav({ links, preserveState = true, preserveScroll = true }: Props) {
    const numberedLinks = links.filter((link) => /\d+/.test(stripHtml(link.label)));

    if (numberedLinks.length <= 1) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center gap-2" aria-label="Pagination Navigation">
            {links.map((link, index) => {
                const text = stripHtml(link.label).trim();
                const isArrow = text.includes('Previous') || text.includes('Next') || text.includes('«') || text.includes('»');

                return (
                    <Link
                        key={`${text}-${index}`}
                        href={link.url || '#'}
                        preserveState={preserveState}
                        preserveScroll={preserveScroll}
                        aria-disabled={!link.url}
                        className={[
                            'inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border px-3 py-1.5 text-sm font-semibold transition',
                            link.active
                                ? 'border-red-700 bg-red-700 text-white'
                                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                            isArrow ? 'text-xs' : '',
                        ].join(' ')}
                    >
                        {text}
                    </Link>
                );
            })}
        </nav>
    );
}

function stripHtml(label: string): string {
    return label.replace(/<[^>]*>/g, '');
}

