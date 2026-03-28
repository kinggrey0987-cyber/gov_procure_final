<?php

namespace App\Http\Controllers\AdminTech;

use App\Http\Controllers\Controller;
use App\Models\Office;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OfficeManagementController extends Controller
{
    public function index(): Response
    {
        $offices = Office::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('AdminTech/Offices/Index', [
            'offices' => $offices,
        ]);
    }

    public function edit(Office $office): Response
    {
        return Inertia::render('AdminTech/Offices/Edit', [
            'office' => $office,
        ]);
    }

    public function update(Request $request, Office $office): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('offices', 'name')->ignore($office->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('offices', 'code')->ignore($office->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $office->update($validated);

        return to_route('admin-tech.offices.index');
    }

    public function destroy(Office $office): RedirectResponse
    {
        if ($office->users()->exists()) {
            return back()->withErrors([
                'delete' => 'This office cannot be deleted while users are still assigned to it.',
            ]);
        }

        try {
            $office->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'delete' => 'This office cannot be deleted because it is linked to existing records.',
            ]);
        }

        return to_route('admin-tech.offices.index');
    }
}
