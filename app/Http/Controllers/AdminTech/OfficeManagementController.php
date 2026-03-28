<?php

namespace App\Http\Controllers\AdminTech;

use App\Http\Controllers\Controller;
use App\Models\Office;
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
}
