<?php

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/workspaces/{workspace}/me', function ($workspace, Request $request) {
        $membership = Membership::with('team')
            ->where('workspace_id', $workspace)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return [
            'user' => $request->user(),
            'membership' => $membership,
        ];
    })->middleware('workspace.member');
});
