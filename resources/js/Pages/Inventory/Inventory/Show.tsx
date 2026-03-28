import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Props = {
    inventory: {
        inventory_id: number;
        item_id: number;
        available_stock: string;
        stock_on_hand: string;
        reserved_quantity: string;
        released_quantity: string;
        low_stock_threshold: string;
    };
};

export default function InventoryShow({ inventory }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventory Detail</h2>}>
            <Head title="Inventory Detail" />

            <div className="py-10">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 text-sm">
                        <p><strong>Inventory ID:</strong> {inventory.inventory_id}</p>
                        <p><strong>Item ID:</strong> {inventory.item_id}</p>
                        <p><strong>Available Stock:</strong> {inventory.available_stock}</p>
                        <p><strong>Stock On Hand:</strong> {inventory.stock_on_hand}</p>
                        <p><strong>Reserved Quantity:</strong> {inventory.reserved_quantity}</p>
                        <p><strong>Released Quantity:</strong> {inventory.released_quantity}</p>
                        <p><strong>Low Stock Threshold:</strong> {inventory.low_stock_threshold}</p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
