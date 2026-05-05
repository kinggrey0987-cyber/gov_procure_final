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
    const numberedLinks = links.filter((link) => /\d+/.test(normalizePaginationLabel(link.label)));

    if (numberedLinks.length <= 1) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center gap-2" aria-label="Pagination Navigation">
            {links.map((link, index) => {
                const text = normalizePaginationLabel(link.label);
                const isArrow = text === 'Previous' || text === 'Next';

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

function normalizePaginationLabel(label: string): string {
    const decoded = decodeHtmlEntities(label.replace(/<[^>]*>/g, '')).trim();
    const normalized = decoded
        .replace(/&laquo;|&#171;|&#x00ab;/gi, '«')
        .replace(/&raquo;|&#187;|&#x00bb;/gi, '»')
        .replace(/&amp;/gi, '&')
        .trim();

    if (/previous/i.test(normalized) || normalized.includes('«')) {
        return 'Previous';
    }

    if (/next/i.test(normalized) || normalized.includes('»')) {
        return 'Next';
    }

    return normalized;
}

function decodeHtmlEntities(value: string): string {
    if (typeof document === 'undefined') {
        return value
            .replace(/&laquo;/gi, '«')
            .replace(/&raquo;/gi, '»')
            .replace(/&amp;/gi, '&')
            .replace(/&lt;/gi, '<')
            .replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"')
            .replace(/&#039;/gi, "'");
    }

    const textarea = document.createElement('textarea');
    textarea.innerHTML = value;

    return textarea.value;
}





