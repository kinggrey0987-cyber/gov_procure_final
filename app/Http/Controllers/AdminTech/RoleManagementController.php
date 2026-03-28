<?php

namespace App\Http\Controllers\AdminTech;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Inertia\Inertia;
use Inertia\Response;

class RoleManagementController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return Inertia::render('AdminTech/Roles/Index', [
            'roles' => $roles,
        ]);
    }
}
