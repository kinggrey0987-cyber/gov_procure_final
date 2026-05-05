import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Props = {
    title: string;
    description: string;
};

export default function DomainPlaceholder({ title, description }: Props) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">{title}</h2>}
        >
            <Head title={title} />

            <div className="py-10">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <p className="text-gray-700">{description}</p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





