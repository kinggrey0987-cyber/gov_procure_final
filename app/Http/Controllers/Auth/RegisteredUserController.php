<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    private const PUBLIC_EMP_ID_PREFIX = 'PUB-';

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $publicRole = Role::query()->firstOrCreate(
            ['code' => UserRole::PUBLIC->value],
            [
                'name' => 'Public Viewer',
                'description' => 'Can only view approved and published public disclosures.',
                'is_active' => true,
            ],
        );

        $defaultOffice = Office::query()->firstOrCreate(
            ['code' => 'PUBLIC_PORTAL'],
            [
                'name' => 'Public Portal Office',
                'description' => 'System office used for public-facing user accounts.',
                'is_active' => true,
            ],
        );

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'emp_id' => $this->generatePublicEmpId(),
            'password' => Hash::make($request->password),
            'role_id' => $publicRole->id,
            'office_id' => $defaultOffice->id,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    private function generatePublicEmpId(): string
    {
        do {
            $candidate = self::PUBLIC_EMP_ID_PREFIX.strtoupper((string) str()->random(10));
        } while (User::query()->where('emp_id', $candidate)->exists());

        return $candidate;
    }
}
