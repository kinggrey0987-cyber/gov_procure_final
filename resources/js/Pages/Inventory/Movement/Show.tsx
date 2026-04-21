import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link } from '@inertiajs/react';

type Props = {
    movement: {
        movement_id: number;
        movement_type: string;
        quantity: number;
        movement_date: string;
        remarks?: string | null;
        reference_label: string;
        performed_by?: {
            name: string;
        } | null;
        inventory?: {
            inventory_id: number;
            item_name: string;
        } | null;
    };
};

export default function MovementShow({ movement }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center gap-3">
                    <Link href={route('inventory.inventory-movements.index')} className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Back to Movement Logs
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Movement Detail</h2>
                </div>
            }
        >
            <Head title="Movement Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-xl border border-slate-200 bg-white p-6 text-sm shadow-sm">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Info label="Movement Type" value={humanizeEnum(movement.movement_type)} />
                            <Info label="Quantity" value={movement.quantity.toFixed(2)} />
                            <Info label="Date" value={movement.movement_date} />
                            <Info label="Performed By" value={movement.performed_by?.name ?? 'System'} />
                            <Info label="Affected Item" value={movement.inventory?.item_name ?? 'Unknown Item'} />
                            <Info label="Inventory Reference" value={movement.inventory ? `#${movement.inventory.inventory_id}` : '-'} />
                            <Info label="Source Reference" value={movement.reference_label} />
                            <Info label="Remarks" value={movement.remarks ?? 'No remarks provided.'} />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm text-slate-800">{value}</p>
        </div>
    );
}
