<?php

namespace App\Http\Controllers\AdminTech;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with(['role:id,code,name', 'office:id,code,name'])
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('AdminTech/Users/Index', [
            'users' => $users,
        ]);
    }
}
