<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
{
    /** PDF snapshot-dan yaradılır (diplom ödəniş bitəndən sonra da yüklənir). */
    public function pdf(Request $request, Certificate $certificate)
    {
        abort_unless($certificate->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_if($certificate->revoked_at, 410);
        $opt = new Options;
        $opt->set('isRemoteEnabled', false);
        $opt->set('defaultFont', 'DejaVu Sans');
        $opt->set('chroot', resource_path('views/certificates'));
        $pdf = new Dompdf($opt);
        $pdf->loadHtml(view('certificates.pdf', ['c' => $certificate, 's' => $certificate->snapshot,
            'url' => route('certificates.verify', $certificate->verify_id)])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="marketing-school-diplom-'.$certificate->verify_id.'.pdf"']);
    }

    /** Açıq yoxlama: yalnız ad, kurs və tarix. Rate limit + 128 bitlik ID enumerasiyanı praktiki mümkünsüz edir. */
    public function verify(string $verifyId)
    {
        abort_unless(preg_match('/^[A-Z2-7]{26}$/', $verifyId), 404);
        $row = DB::table('certificate_verifications')->where('verify_id', $verifyId)->first();
        abort_unless($row, 404);
        $row->revoked = $row->revoked_at !== null;
        $course = json_decode($row->course, true);
        $issued = Carbon::parse($row->issued_at);

        return response()->view('certificates.verify', ['holder' => $row->holder, 'course' => tr_field($course), 'issued' => $issued,
            'revoked' => $row->revoked, 'verifyId' => $verifyId,
            'linkedin' => 'https://www.linkedin.com/profile/add?'.http_build_query(['startTask' => 'CERTIFICATION_NAME', 'name' => tr_field($course),
                'organizationName' => 'Marketing School', 'issueYear' => $issued->year, 'issueMonth' => $issued->month,
                'certUrl' => route('certificates.verify', $verifyId), 'certId' => $verifyId])]);
    }
}
