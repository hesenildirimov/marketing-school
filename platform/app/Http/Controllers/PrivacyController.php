<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Audit;
use App\Domain\Auth\SessionManager;
use App\Domain\Privacy\PrivacyService;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function show()
    {
        return view('account.privacy');
    }

    public function export(Request $request, PrivacyService $p)
    {
        Audit::record('privacy.export', $request->user(), actorId: $request->user()->id);

        return response()->json($p->export($request->user()), 200, ['Content-Disposition' => 'attachment; filename="marketing-school-data.json"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, PrivacyService $p, SessionManager $sessions)
    {
        $request->validate(['confirm' => 'required|in:DELETE,SİL,УДАЛИТЬ']);
        $user = $request->user();
        $p->delete($user, $sessions);
        $sessions->end($request, 'account_deleted');

        return redirect()->route('login')->with('status', __('privacy.deleted'));
    }
}
