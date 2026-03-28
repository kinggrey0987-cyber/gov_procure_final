import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Supplier = {
    supplier_id: number;
    supplier_name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    status: string;
};

type Props = {
    supplier: Supplier;
};

export default function SuppliersShow({ supplier }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Supplier</h2>}>
            <Head title="Supplier" />

            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg border bg-white p-6 text-sm">
                        <p><strong>Name:</strong> {supplier.supplier_name}</p>
                        <p><strong>Contact Person:</strong> {supplier.contact_person ?? '-'}</p>
                        <p><strong>Email:</strong> {supplier.email ?? '-'}</p>
                        <p><strong>Phone:</strong> {supplier.phone ?? '-'}</p>
                        <p><strong>Address:</strong> {supplier.address ?? '-'}</p>
                        <p><strong>Status:</strong> {supplier.status}</p>
                    </div>

                    <Link href={route('procurement.suppliers.edit', supplier.supplier_id)} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                        Edit Supplier
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
