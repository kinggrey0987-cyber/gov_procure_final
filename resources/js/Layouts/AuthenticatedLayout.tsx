import ApplicationLogo from '@/Components/ApplicationLogo';
import BellPanel from '@/Components/Notifications/BellPanel';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

type AuthCan = {
    admin_tech: boolean;
    office: boolean;
    procurement: boolean;
    inventory: boolean;
    executive: boolean;
    notifications: boolean;
};

type AuthUser = {
    name: string;
    email: string;
    role_code?: string;
    office_name?: string | null;
};

type NotificationState = {
    unread_count: number;
    recent: Array<{
        notification_id: number;
        title: string;
        message: string;
        is_read: boolean;
        created_at?: string | null;
        open_url: string;
    }>;
};

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const pageProps = usePage().props as unknown as {
        auth: {
            user: AuthUser;
            can: AuthCan;
        };
        notificationSummary?: NotificationState;
    };
    const { auth } = pageProps;
    const notifications: NotificationState = pageProps.notificationSummary ?? { unread_count: 0, recent: [] };

    const user = auth.user;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const isAdminTechShell =
        auth.can.admin_tech &&
        (route().current('admin-tech.*') ||
            route().current('profile.*') ||
            route().current('notifications.*'));
    const isExecutiveShell =
        auth.can.executive &&
        (route().current('executive.*') ||
            route().current('profile.*') ||
            route().current('notifications.*'));
    const isOfficeShell =
        auth.can.office &&
        (route().current('office.*') ||
            route().current('profile.*') ||
            route().current('notifications.*'));
    const isProcurementShell =
        auth.can.procurement &&
        (route().current('procurement.*') ||
            route().current('profile.*') ||
            route().current('notifications.*'));
    const isInventoryShell =
        auth.can.inventory &&
        (route().current('inventory.*') ||
            route().current('profile.*') ||
            route().current('notifications.*'));

    if (isAdminTechShell) {
        const adminLinks = [
            {
                label: 'Dashboard',
                href: route('admin-tech.dashboard'),
                active: route().current('admin-tech.dashboard'),
                icon: (
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <rect x="3" y="3" width="7" height="7" rx="1" />
                        <rect x="14" y="3" width="7" height="7" rx="1" />
                        <rect x="3" y="14" width="7" height="7" rx="1" />
                        <rect x="14" y="14" width="7" height="7" rx="1" />
                    </svg>
                ),
            },
            {
                label: 'User Management',
                href: route('admin-tech.users.index'),
                active: route().current('admin-tech.users.*'),
                icon: (
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="9" cy="8" r="3" />
                        <path d="M2 20c0-3.2 2.8-5 7-5" />
                        <circle cx="17" cy="9" r="2" />
                        <path d="M14 20c.2-2.2 1.8-3.5 4-3.9" />
                    </svg>
                ),
            },
            {
                label: 'Office Management',
                href: route('admin-tech.offices.index'),
                active: route().current('admin-tech.offices.*'),
                icon: (
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <rect x="4" y="3" width="10" height="18" rx="2" />
                        <path d="M14 8h6v13h-6" />
                        <path d="M8 7h2M8 11h2M8 15h2" />
                    </svg>
                ),
            },
            {
                label: 'System Monitoring',
                href: route('admin-tech.roles.index'),
                active: route().current('admin-tech.roles.*'),
                icon: (
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="3" />
                        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.1A1.7 1.7 0 0 0 10 3.2V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.4 1z" />
                    </svg>
                ),
            },
            {
                label: 'System Backups',
                href: route('admin-tech.system-backups.index'),
                active: route().current('admin-tech.system-backups.*'),
                icon: (
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M4 7h16v10H4z" />
                        <path d="M9 7V5h6v2" />
                        <path d="M8 13h8" />
                    </svg>
                ),
            },
            {
                label: 'Profile',
                href: route('profile.edit'),
                active: route().current('profile.*'),
                icon: (
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="8" r="3.5" />
                        <path d="M5 20c0-3.7 3.1-6 7-6s7 2.3 7 6" />
                    </svg>
                ),
            },
        ];

        const roleLabel = (user.role_code ?? 'ADMIN')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, (char) => char.toUpperCase());

        return (
            <div className="min-h-screen bg-slate-100 md:flex">
                <aside className="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <img src="/logo.jpg" alt="GovSupply Connect" className="h-12 w-12 rounded-full border border-slate-300 bg-white object-cover" />
                            <div>
                                <p className="text-3 font-bold leading-tight text-red-700">GovSupply Connect</p>
                                <p className="text-sm text-slate-500">Municipality of E.B. Magalona</p>
                            </div>
                        </div>
                    </div>

                    <nav className="space-y-2 px-3 py-4">
                        {adminLinks.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                                    item.active
                                        ? 'border-red-300 bg-red-600 text-white shadow-sm'
                                        : 'border-transparent text-slate-700 hover:border-slate-200 hover:bg-slate-50'
                                }`}
                            >
                                <span>{item.icon}</span>
                                <span>{item.label}</span>
                            </Link>
                        ))}
                    </nav>

                    <div className="mt-auto border-t border-slate-200 px-4 py-4">
                        <div className="flex items-center gap-2 text-sm text-slate-600">
                            <img src="/logo.jpg" alt="GovSupply Connect" className="h-8 w-8 rounded-full border border-slate-300 bg-white object-cover" />
                            <div>
                                <p className="font-semibold text-slate-700">GovSupply Connect</p>
                                <p>Version 2.0.1</p>
                            </div>
                        </div>
                    </div>
                </aside>

                <div className="flex-1">
                    <header className="border-b border-slate-200 bg-white">
                        <div className="mx-auto flex min-h-16 w-full max-w-[1300px] items-center justify-between gap-3 px-4 py-3 sm:min-h-20 sm:px-6">
                            <div className="flex items-center gap-3 md:hidden">
                                <img src="/logo.jpg" alt="GovSupply Connect" className="h-9 w-9 rounded-full border border-slate-300 bg-white object-cover sm:h-10 sm:w-10" />
                                <p className="text-sm font-bold leading-tight text-red-700 sm:text-base">GovSupply Connect</p>
                            </div>
                            <div className="ms-auto flex shrink-0 items-center gap-3 sm:gap-5">
                                <BellPanel unreadCount={notifications.unread_count} recent={notifications.recent} />

                                <div className="flex items-center gap-3">
                                    <div className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-bold text-white">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="hidden text-sm sm:block">
                                        <p className="font-semibold text-slate-800">{user.name}</p>
                                        <p className="text-slate-500">{roleLabel}</p>
                                    </div>
                                </div>

                                <div className="relative">
                                    <Dropdown>
                                        <Dropdown.Trigger>
                                            <span className="inline-flex rounded-md">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center rounded-md border border-transparent bg-white px-1 py-2 text-sm text-slate-500 transition hover:text-slate-700 focus:outline-none"
                                                >
                                                    <svg className="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                </button>
                                            </span>
                                        </Dropdown.Trigger>

                                        <Dropdown.Content>
                                            <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                                Log Out
                                            </Dropdown.Link>
                                        </Dropdown.Content>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>
                    </header>

                    <nav className="border-b border-slate-200 bg-white md:hidden">
                        <div className="flex gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none] sm:px-6">
                            {adminLinks.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className={`shrink-0 rounded-full border px-3 py-2 text-xs font-semibold transition ${
                                        item.active
                                            ? 'border-red-600 bg-red-600 text-white shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-red-200 hover:bg-red-50'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    </nav>

                    <div className="mx-auto w-full max-w-[1300px] px-3 py-4 sm:px-6 sm:py-6">
                        {header && (
                            <header className="mb-6">
                                <p className="mt-1 text-sm text-slate-500 sm:text-xl">Municipality of E.B. Magalona - {roleLabel}</p>
                                <div className="mt-2 text-2xl font-bold text-slate-800 sm:mt-3 sm:text-4xl">{header}</div>
                            </header>
                        )}

                        <main>{children}</main>
                    </div>
                </div>
            </div>
        );
    }

    if (isProcurementShell) {
        const procurementLinks = [
            {
                label: 'Dashboard',
                href: route('procurement.dashboard'),
                active: route().current('procurement.dashboard') || route().current('procurement.tracking'),
            },
            {
                label: 'Request Review',
                href: route('procurement.purchase-requests.index'),
                active: route().current('procurement.purchase-requests.*'),
            },
            {
                label: 'Purchase Orders',
                href: route('procurement.purchase-orders.index'),
                active: route().current('procurement.purchase-orders.*') && !route().current('procurement.purchase-orders.records'),
            },
            {
                label: 'Suppliers',
                href: route('procurement.suppliers.index'),
                active: route().current('procurement.suppliers.*'),
            },
            {
                label: 'Procurement Records',
                href: route('procurement.purchase-orders.records'),
                active: route().current('procurement.purchase-orders.records'),
            },
            {
                label: 'Documents',
                href: route('procurement.documents.index'),
                active: route().current('procurement.documents.*'),
            },
            {
                label: 'Profile',
                href: route('profile.edit'),
                active: route().current('profile.*'),
            },
        ];

        const roleLabel = user.role_code === 'PROC_OFFICER'
            ? 'Procurement Unit'
            : (user.role_code ?? 'USER')
                .replaceAll('_', ' ')
                .toLowerCase()
                .replace(/\b\w/g, (char) => char.toUpperCase());

        return (
            <div className="min-h-screen bg-slate-100 md:flex">
                <aside className="hidden w-72 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <img src="/logo.jpg" alt="GovSupply Connect" className="h-12 w-12 rounded-full border border-slate-300 bg-white object-cover" />
                            <div>
                                <p className="text-3 font-bold leading-tight text-red-700">GovSupply Connect</p>
                                <p className="text-sm text-slate-500">Municipality of E.B. Magalona</p>
                            </div>
                        </div>
                    </div>

                    <nav className="space-y-2 px-3 py-4">
                        {procurementLinks.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`block rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                                    item.active
                                        ? 'border-red-600 bg-red-600 text-white shadow-sm'
                                        : 'border-transparent text-slate-700 hover:border-red-100 hover:bg-red-50'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="mt-auto border-t border-slate-200 bg-slate-50 px-4 py-4">
                        <p className="text-sm font-semibold text-slate-800">Procurement Operations Suite</p>
                        <p className="text-xs text-slate-600">Procurement Unit request review, supplier management, and PO execution.</p>
                    </div>
                </aside>

                <div className="flex-1">
                    <header className="border-b border-slate-200 bg-white">
                        <div className="mx-auto flex min-h-16 w-full max-w-[1400px] items-center justify-between gap-3 px-4 py-3 sm:min-h-20 sm:px-6">
                            <div className="flex items-center gap-3">
                                <img src="/logo.jpg" alt="GovSupply Connect" className="h-9 w-9 rounded-full border border-slate-300 bg-white object-cover sm:h-11 sm:w-11" />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold leading-tight text-red-700 sm:text-2xl">GovSupply Connect</p>
                                    <p className="hidden text-sm text-slate-500 sm:block">Municipality of E.B. Magalona</p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-3 sm:gap-5">
                                <BellPanel unreadCount={notifications.unread_count} recent={notifications.recent} />

                                <div className="flex items-center gap-3">
                                    <div className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-bold text-white">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="hidden text-sm sm:block">
                                        <p className="font-semibold text-slate-800">{user.name}</p>
                                        <p className="text-slate-500">{roleLabel}</p>
                                    </div>
                                </div>

                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-1 py-2 text-sm text-slate-500 transition hover:text-slate-700 focus:outline-none"
                                            >
                                                <svg className="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>
                    </header>

                    <nav className="border-b border-slate-200 bg-white md:hidden">
                        <div className="flex gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none] sm:px-6">
                            {procurementLinks.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className={`shrink-0 rounded-full border px-3 py-2 text-xs font-semibold transition ${
                                        item.active
                                            ? 'border-red-600 bg-red-600 text-white shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-red-200 hover:bg-red-50'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    </nav>

                    <div className="mx-auto w-full max-w-[1400px] px-3 py-4 sm:px-6 sm:py-6">
                        {header && (
                            <header className="mb-6 border-b border-slate-200 pb-4">
                                <p className="mt-1 text-sm text-slate-500 sm:text-xl">Municipality of E.B. Magalona - Procurement Unit Staff</p>
                                <div className="mt-2 text-2xl font-bold text-slate-800 sm:mt-3 sm:text-4xl">{header}</div>
                            </header>
                        )}

                        <main>{children}</main>
                    </div>
                </div>
            </div>
        );
    }

    if (isOfficeShell) {
        const officeLinks = [
            {
                label: 'Dashboard',
                href: route('office.dashboard'),
                active: route().current('office.dashboard') || route().current('office.tracking'),
            },
            {
                label: 'PPMP',
                href: route('office.ppmp.index'),
                active: route().current('office.ppmp.*'),
            },
            {
                label: 'Create Supply Request',
                href: route('office.supply-requests.create'),
                active: route().current('office.supply-requests.create'),
            },
            {
                label: 'Request Tracking',
                href: route('office.supply-requests.index'),
                active: route().current('office.supply-requests.index') || route().current('office.supply-requests.show') || route().current('office.supplies-ready.*') || route().current('office.distribution-records.*'),
            },
            {
                label: 'Claims and Adjustments',
                href: route('office.claims.index'),
                active: route().current('office.claims.*'),
            },
            {
                label: 'Notifications',
                href: route('notifications.index'),
                active: route().current('notifications.*'),
            },
            {
                label: 'Profile',
                href: route('profile.edit'),
                active: route().current('profile.*'),
            },
        ];

        const roleLabel = (user.role_code ?? 'OFFICE_USER')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, (char) => char.toUpperCase());
        const officeLabel = user.office_name && user.office_name.trim().length > 0
            ? user.office_name
            : 'Municipal Department';

        return (
            <div className="min-h-screen bg-slate-100 md:flex">
                <aside className="hidden w-72 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <img src="/logo.jpg" alt="GovSupply Connect" className="h-12 w-12 rounded-full border border-slate-300 bg-white object-cover" />
                            <div>
                                <p className="text-3 font-bold leading-tight text-red-700">GovSupply Connect</p>
                                <p className="text-sm text-slate-500">Municipality of E.B. Magalona</p>
                            </div>
                        </div>
                    </div>

                    <nav className="space-y-2 px-3 py-4">
                        {officeLinks.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`block rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                                    item.active
                                        ? 'border-red-300 bg-red-600 text-white shadow-sm'
                                        : 'border-transparent text-slate-700 hover:border-red-100 hover:bg-red-50'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="mt-auto border-t border-slate-200 px-4 py-4">
                        <div className="flex items-center gap-2 text-sm text-slate-600">
                            <img src="/logo.jpg" alt="GovSupply Connect" className="h-8 w-8 rounded-full border border-slate-300 bg-white object-cover" />
                            <div>
                                <p className="font-semibold text-slate-700">GovSupply Connect</p>
                                <p>Version 2.0.1</p>
                            </div>
                        </div>
                    </div>
                </aside>

                <div className="flex-1">
                    <header className="border-b border-slate-200 bg-white">
                        <div className="mx-auto flex min-h-16 w-full max-w-[1400px] items-center justify-between gap-3 px-4 py-3 sm:min-h-20 sm:px-6">
                            <div className="flex items-center gap-3">
                                <img src="/logo.jpg" alt="GovSupply Connect" className="h-9 w-9 rounded-full border border-slate-300 bg-white object-cover sm:h-11 sm:w-11" />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold leading-tight text-red-700 sm:text-2xl">GovSupply Connect</p>
                                    <p className="hidden text-sm text-slate-500 sm:block">Municipality of E.B. Magalona</p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-3 sm:gap-5">
                                <BellPanel unreadCount={notifications.unread_count} recent={notifications.recent} />

                                <div className="flex items-center gap-3">
                                    <div className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-bold text-white">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="hidden text-sm sm:block">
                                        <p className="font-semibold text-slate-800">{user.name}</p>
                                        <p className="text-slate-500">{roleLabel}</p>
                                    </div>
                                </div>

                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-1 py-2 text-sm text-slate-500 transition hover:text-slate-700 focus:outline-none"
                                            >
                                                <svg className="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>
                    </header>

                    <nav className="border-b border-slate-200 bg-white md:hidden">
                        <div className="flex gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none] sm:px-6">
                            {officeLinks.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className={`shrink-0 rounded-full border px-3 py-2 text-xs font-semibold transition ${
                                        item.active
                                            ? 'border-red-600 bg-red-600 text-white shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-red-200 hover:bg-red-50'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    </nav>

                    <div className="mx-auto w-full max-w-[1400px] px-3 py-4 sm:px-6 sm:py-6">
                        {header && (
                            <header className="mb-6 border-b border-slate-200 pb-4">
                                <p className="mt-1 text-sm text-slate-500 sm:text-xl">Municipality of E.B. Magalona - {officeLabel}</p>
                                <div className="mt-2 text-2xl font-bold text-slate-800 sm:mt-3 sm:text-4xl">{header}</div>
                            </header>
                        )}

                        <main>{children}</main>
                    </div>
                </div>
            </div>
        );
    }

    if (isExecutiveShell) {
        const executiveLinks = [
            {
                label: 'Executive Dashboard',
                href: route('executive.dashboard'),
                active: route().current('executive.dashboard'),
            },
            {
                label: 'Procurement Insights',
                href: route('executive.procurement-analytics'),
                active: route().current('executive.procurement-analytics'),
            },
            {
                label: 'Risk & Alerts',
                href: route('executive.anomalies-monitoring'),
                active:
                    route().current('executive.anomalies-monitoring') ||
                    route().current('executive.monitoring.summary') ||
                    route().current('executive.anomalies.*') ||
                    route().current('executive.alerts.*'),
            },
            {
                label: 'Executive Approvals',
                href: route('executive.ppmp-approvals.index'),
                active: route().current('executive.ppmp-approvals.*'),
            },
            {
                label: 'Governance Logs',
                href: route('executive.audit.business'),
                active: route().current('executive.audit.*'),
            },
            {
                label: 'Transparency & Disclosures',
                href: route('executive.transparency-monitoring'),
                active: route().current('executive.transparency-monitoring') || route().current('executive.disclosures.*'),
            },
            {
                label: 'Record Archives',
                href: route('executive.record-archives.index'),
                active: route().current('executive.record-archives.*'),
            },
            {
                label: 'Profile',
                href: route('profile.edit'),
                active: route().current('profile.*'),
            },
        ];

        const roleLabel = (user.role_code ?? 'EXEC_OFFICER')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, (char) => char.toUpperCase());

        return (
            <div className="min-h-screen bg-slate-100 md:flex">
                <aside className="hidden w-72 shrink-0 flex-col border-r border-red-200 bg-white md:flex">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <img src="/logo.jpg" alt="GovSupply Connect" className="h-12 w-12 rounded-full border border-slate-300 bg-white object-cover" />
                            <div>
                                <p className="text-3 font-bold leading-tight text-red-700">GovSupply Connect</p>
                                <p className="text-sm text-slate-500">Municipality of E.B. Magalona</p>
                            </div>
                        </div>
                    </div>

                    <nav className="space-y-2 px-3 py-4">
                        {executiveLinks.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`block rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                                    item.active
                                        ? 'border-red-600 bg-red-600 text-white shadow-sm'
                                        : 'border-transparent text-slate-700 hover:border-red-100 hover:bg-red-50'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="mt-auto border-t border-red-100 bg-red-50/70 px-4 py-4">
                        <p className="text-sm font-semibold text-red-800">Executive Governance Suite</p>
                        <p className="text-xs text-red-700/80">Executive disclosure preparation, review, and publication governance workflow.</p>
                    </div>
                </aside>

                <div className="flex-1">
                    <header className="border-b border-slate-200 bg-white">
                        <div className="mx-auto flex min-h-16 w-full max-w-[1400px] items-center justify-between gap-3 px-4 py-3 sm:min-h-20 sm:px-6">
                            <div className="flex items-center gap-3">
                                <img src="/logo.jpg" alt="GovSupply Connect" className="h-9 w-9 rounded-full border border-slate-300 bg-white object-cover sm:h-11 sm:w-11" />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold leading-tight text-red-700 sm:text-2xl">GovSupply Connect</p>
                                    <p className="hidden text-sm text-slate-500 sm:block">Municipality of E.B. Magalona</p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-3 sm:gap-5">
                                <BellPanel unreadCount={notifications.unread_count} recent={notifications.recent} />

                                <div className="flex items-center gap-3">
                                    <div className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-bold text-white">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="hidden text-sm sm:block">
                                        <p className="font-semibold text-slate-800">{user.name}</p>
                                        <p className="text-slate-500">{roleLabel}</p>
                                    </div>
                                </div>

                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-1 py-2 text-sm text-slate-500 transition hover:text-slate-700 focus:outline-none"
                                            >
                                                <svg className="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>
                    </header>

                    <nav className="border-b border-slate-200 bg-white md:hidden">
                        <div className="flex gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none] sm:px-6">
                            {executiveLinks.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className={`shrink-0 rounded-full border px-3 py-2 text-xs font-semibold transition ${
                                        item.active
                                            ? 'border-red-600 bg-red-600 text-white shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-red-200 hover:bg-red-50'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    </nav>

                    <div className="mx-auto w-full max-w-[1400px] px-3 py-4 sm:px-6 sm:py-6">
                        {header && (
                            <header className="mb-6 border-b border-slate-200 pb-4">
                                <p className="mt-1 text-sm text-slate-500 sm:text-xl">Municipality of E.B. Magalona - Executive Oversight</p>
                                <div className="mt-2 text-2xl font-bold text-slate-800 sm:mt-3 sm:text-4xl">{header}</div>
                            </header>
                        )}

                        <main>{children}</main>
                    </div>
                </div>
            </div>
        );
    }

    if (isInventoryShell) {
        const inventoryLinks = [
            {
                label: 'Dashboard Overview',
                href: route('inventory.dashboard'),
                active: route().current('inventory.dashboard'),
            },
            {
                label: 'Item Categories',
                href: route('inventory.item-categories.index'),
                active: route().current('inventory.item-categories.*'),
            },
            {
                label: 'Item Catalog',
                href: route('inventory.items.index'),
                active: route().current('inventory.items.*'),
            },
            {
                label: 'Item Units',
                href: route('inventory.item-units.index'),
                active: route().current('inventory.item-units.*'),
            },
            {
                label: 'Delivery Monitoring',
                href: route('inventory.deliveries.index'),
                active: route().current('inventory.deliveries.*'),
            },
            {
                label: 'Supply Distribution',
                href: route('inventory.distribution-records.create'),
                active:
                    route().current('inventory.distribution-records.create') ||
                    route().current('inventory.distribution-records.store'),
            },
            {
                label: 'Office Supply Requests',
                href: route('inventory.office-supply-requests.index'),
                active: route().current('inventory.office-supply-requests.*'),
            },
            {
                label: 'Purchase Request',
                href: route('inventory.procurement-requests.index'),
                active: route().current('inventory.procurement-requests.*'),
            },
            {
                label: 'Distribution Records',
                href: route('inventory.distribution-records.index'),
                active:
                    route().current('inventory.distribution-records.index') ||
                    route().current('inventory.distribution-records.show') ||
                    route().current('inventory.distribution-records.edit') ||
                    route().current('inventory.distribution-records.items.manage'),
            },
            {
                label: 'Claims Review',
                href: route('inventory.claims.index'),
                active: route().current('inventory.claims.*'),
            },
            {
                label: 'Inventory Monitoring',
                href: route('inventory.inventory.index'),
                active: route().current('inventory.inventory.*'),
            },
            {
                label: 'Movement Logs',
                href: route('inventory.inventory-movements.index'),
                active: route().current('inventory.inventory-movements.*'),
            },
            {
                label: 'Profile',
                href: route('profile.edit'),
                active: route().current('profile.*'),
            },
        ];

        const roleLabel = (user.role_code ?? 'GSO_OFFICER')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, (char) => char.toUpperCase());

        return (
            <div className="min-h-screen bg-slate-100 md:flex">
                <aside className="hidden w-72 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <img src="/logo.jpg" alt="GovSupply Connect" className="h-12 w-12 rounded-full border border-slate-300 bg-white object-cover" />
                            <div>
                                <p className="text-3 font-bold leading-tight text-red-700">GovSupply Connect</p>
                                <p className="text-sm text-slate-500">Municipality of E.B. Magalona</p>
                            </div>
                        </div>
                    </div>

                    <nav className="space-y-2 px-3 py-4">
                        {inventoryLinks.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`block rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                                    item.active
                                        ? 'border-red-600 bg-red-600 text-white shadow-sm'
                                        : 'border-transparent text-slate-700 hover:border-red-100 hover:bg-red-50'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="mt-auto border-t border-slate-200 bg-slate-50 px-4 py-4">
                        <p className="text-sm font-semibold text-slate-800">Inventory Operations Suite</p>
                        <p className="text-xs text-slate-600">Delivery, stock, and distribution control. Version 2.0.1</p>
                    </div>
                </aside>

                <div className="flex-1">
                    <header className="border-b border-slate-200 bg-white">
                        <div className="mx-auto flex min-h-16 w-full max-w-[1400px] items-center justify-between gap-3 px-4 py-3 sm:min-h-20 sm:px-6">
                            <div className="flex items-center gap-3">
                                <img src="/logo.jpg" alt="GovSupply Connect" className="h-9 w-9 rounded-full border border-slate-300 bg-white object-cover sm:h-11 sm:w-11" />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold leading-tight text-red-700 sm:text-2xl">GovSupply Connect</p>
                                    <p className="hidden text-sm text-slate-500 sm:block">Municipality of E.B. Magalona</p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-3 sm:gap-5">
                                <BellPanel unreadCount={notifications.unread_count} recent={notifications.recent} />

                                <div className="flex items-center gap-3">
                                    <div className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-bold text-white">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="hidden text-sm sm:block">
                                        <p className="font-semibold text-slate-800">{user.name}</p>
                                        <p className="text-slate-500">{roleLabel}</p>
                                    </div>
                                </div>

                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-1 py-2 text-sm text-slate-500 transition hover:text-slate-700 focus:outline-none"
                                            >
                                                <svg className="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>
                    </header>

                    <nav className="border-b border-slate-200 bg-white md:hidden">
                        <div className="flex gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none] sm:px-6">
                            {inventoryLinks.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className={`shrink-0 rounded-full border px-3 py-2 text-xs font-semibold transition ${
                                        item.active
                                            ? 'border-red-600 bg-red-600 text-white shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-red-200 hover:bg-red-50'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    </nav>

                    <div className="mx-auto w-full max-w-[1400px] px-3 py-4 sm:px-6 sm:py-6">
                        {header && (
                            <header className="mb-6 border-b border-slate-200 pb-4">
                                <p className="mt-1 text-sm text-slate-500 sm:text-xl">Municipality of E.B. Magalona - Inventory and Logistics</p>
                                <div className="mt-2 text-2xl font-bold text-slate-800 sm:mt-3 sm:text-4xl">{header}</div>
                            </header>
                        )}

                        <main>{children}</main>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                                    Dashboard
                                </NavLink>
                                {auth.can.admin_tech && (
                                    <NavLink
                                        href={route('admin-tech.dashboard')}
                                        active={route().current('admin-tech.*')}
                                    >
                                        Admin Tech
                                    </NavLink>
                                )}
                                {auth.can.admin_tech && (
                                    <NavLink
                                        href={route('admin-tech.audit.technical')}
                                        active={route().current('admin-tech.audit.*')}
                                    >
                                        Technical Audit
                                    </NavLink>
                                )}
                                {auth.can.office && (
                                    <NavLink href={route('office.dashboard')} active={route().current('office.*')}>
                                        Office
                                    </NavLink>
                                )}
                                {auth.can.procurement && (
                                    <NavLink
                                        href={route('procurement.dashboard')}
                                        active={route().current('procurement.*')}
                                    >
                                        Procurement
                                    </NavLink>
                                )}
                                {auth.can.inventory && (
                                    <NavLink
                                        href={route('inventory.dashboard')}
                                        active={route().current('inventory.*')}
                                    >
                                        Inventory
                                    </NavLink>
                                )}
                                {auth.can.executive && (
                                    <NavLink
                                        href={route('executive.dashboard')}
                                        active={route().current('executive.*')}
                                    >
                                        Executive
                                    </NavLink>
                                )}
                                {auth.can.executive && (
                                    <NavLink
                                        href={route('executive.audit.business')}
                                        active={route().current('executive.audit.*')}
                                    >
                                        Business Audit
                                    </NavLink>
                                )}
                                {auth.can.notifications && (
                                    <NavLink href={route('notifications.index')} active={route().current('notifications.*')}>
                                        Notifications{notifications.unread_count > 0 ? ` (${notifications.unread_count})` : ''}
                                    </NavLink>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown((previousState) => !previousState)
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden'}>
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>
                            Dashboard
                        </ResponsiveNavLink>
                        {auth.can.admin_tech && (
                            <ResponsiveNavLink
                                href={route('admin-tech.dashboard')}
                                active={route().current('admin-tech.*')}
                            >
                                Admin Tech
                            </ResponsiveNavLink>
                        )}
                        {auth.can.admin_tech && (
                            <ResponsiveNavLink
                                href={route('admin-tech.audit.technical')}
                                active={route().current('admin-tech.audit.*')}
                            >
                                Technical Audit
                            </ResponsiveNavLink>
                        )}
                        {auth.can.office && (
                            <ResponsiveNavLink href={route('office.dashboard')} active={route().current('office.*')}>
                                Office
                            </ResponsiveNavLink>
                        )}
                        {auth.can.procurement && (
                            <ResponsiveNavLink
                                href={route('procurement.dashboard')}
                                active={route().current('procurement.*')}
                            >
                                Procurement
                            </ResponsiveNavLink>
                        )}
                        {auth.can.inventory && (
                            <ResponsiveNavLink
                                href={route('inventory.dashboard')}
                                active={route().current('inventory.*')}
                            >
                                Inventory
                            </ResponsiveNavLink>
                        )}
                        {auth.can.executive && (
                            <ResponsiveNavLink
                                href={route('executive.dashboard')}
                                active={route().current('executive.*')}
                            >
                                Executive
                            </ResponsiveNavLink>
                        )}
                        {auth.can.executive && (
                            <ResponsiveNavLink
                                href={route('executive.audit.business')}
                                active={route().current('executive.audit.*')}
                            >
                                Business Audit
                            </ResponsiveNavLink>
                        )}
                        {auth.can.notifications && (
                            <ResponsiveNavLink href={route('notifications.index')} active={route().current('notifications.*')}>
                                Notifications{notifications.unread_count > 0 ? ` (${notifications.unread_count})` : ''}
                            </ResponsiveNavLink>
                        )}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">{user.name}</div>
                            <div className="text-sm font-medium text-gray-500">{user.email}</div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                            <ResponsiveNavLink method="post" href={route('logout')} as="button">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{header}</div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}




