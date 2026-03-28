import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Props = {
    movement: {
        movement_id: number;
        movement_type: string;
        reference_type: string;
        reference_id: number;
        quantity: string;
        movement_date: string;
        remarks?: string | null;
    };
};

export default function MovementShow({ movement }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Movement Detail</h2>}>
            <Head title="Movement Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 text-sm">
                        <p><strong>ID:</strong> {movement.movement_id}</p>
                        <p><strong>Type:</strong> {movement.movement_type}</p>
                        <p><strong>Reference:</strong> {movement.reference_type} #{movement.reference_id}</p>
                        <p><strong>Quantity:</strong> {movement.quantity}</p>
                        <p><strong>Date:</strong> {movement.movement_date}</p>
                        <p><strong>Remarks:</strong> {movement.remarks ?? '-'}</p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
