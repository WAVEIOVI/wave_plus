<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Shared\Services\DocumentRenderService;

class UserPdfController extends Controller
{
    public function __construct(
        private readonly DocumentRenderService $documentRender
    ) {}

    /**
     * Export a landscape PDF of all users.
     * Route is already protected by role:Super Admin|Admin middleware.
     * The additional check below provides defence-in-depth.
     */
    public function exportList()
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['Super Admin', 'Admin']),
            403,
            'Insufficient permissions to export the user list.'
        );

        $users = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        $html = view('pdf.users.users-list', compact('users'))->render();

        return $this->documentRender->renderPdfResponse(
            $html,
            'users-list.pdf',
            landscape: true
        );
    }

    /**
     * Export a portrait PDF for a single user.
     * Route is already protected by role:Super Admin|Admin middleware.
     */
    public function exportSingle(string $id)
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['Super Admin', 'Admin']),
            403,
            'Insufficient permissions to export user records.'
        );

        $user = User::with('roles')->findOrFail($id);

        $html = view('pdf.users.user-record', compact('user'))->render();

        return $this->documentRender->renderPdfResponse(
            $html,
            'user-'.$user->id.'.pdf',
            landscape: false
        );
    }
}
