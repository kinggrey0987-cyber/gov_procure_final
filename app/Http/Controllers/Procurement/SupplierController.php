<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\Http\Requests\Procurement\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\Procurement\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $service)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.supplier.view-any'), 403);

        $suppliers = Supplier::query()->latest('supplier_id')->paginate(15);

        return Inertia::render('Procurement/Suppliers/Index', [
            'suppliers' => $suppliers,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.supplier.create'), 403);

        return Inertia::render('Procurement/Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.supplier.create'), 403);

        $supplier = $this->service->create($request->user(), $request->validated());

        return redirect()->route('procurement.suppliers.show', $supplier->supplier_id)
            ->with('success', 'Supplier created successfully.');
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        abort_unless($request->user()?->can('procurement.supplier.view', $supplier), 403);

        return Inertia::render('Procurement/Suppliers/Show', [
            'supplier' => $supplier,
        ]);
    }

    public function edit(Request $request, Supplier $supplier): Response
    {
        abort_unless($request->user()?->can('procurement.supplier.update', $supplier), 403);

        return Inertia::render('Procurement/Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.supplier.update', $supplier), 403);

        $this->service->update($supplier, $request->user(), $request->validated());

        return redirect()->route('procurement.suppliers.show', $supplier->supplier_id)
            ->with('success', 'Supplier updated successfully.');
    }
}
