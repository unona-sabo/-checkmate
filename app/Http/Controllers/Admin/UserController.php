<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Show the list of all registered users.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Users', [
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_admin', 'blocked_at', 'created_at']),
        ]);
    }

    /**
     * Block a user from signing in.
     */
    public function block(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot block your own account.');

        $user->update(['blocked_at' => now()]);

        return back()->with('success', "{$user->name} has been blocked.");
    }

    /**
     * Restore a blocked user's access.
     */
    public function unblock(User $user): RedirectResponse
    {
        $user->update(['blocked_at' => null]);

        return back()->with('success', "{$user->name} has been unblocked.");
    }

    /**
     * Permanently delete a user's account.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');

        $user->delete();

        return back()->with('success', "{$user->name} has been deleted.");
    }
}
