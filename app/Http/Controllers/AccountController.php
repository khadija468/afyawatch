<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'alert_preference' => ['required', 'in:immediate,digest,off'],
        ]);

        $request->user()->update($data);
        AuditLog::record($request, 'alert_preference_changed', $data);

        return redirect()->route('account')->with('status', 'Your alert setting has been saved.');
    }
}
