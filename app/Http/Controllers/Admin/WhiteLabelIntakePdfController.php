<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhiteLabelProjectIntake;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prompt 21-EXT2 §6 — exports one project-commencement brief as a PDF, for
 * handing to whoever actually does the deployment work. super_admin/admin
 * only, same gate as the rest of the White-Label Oversight screen this
 * button lives on.
 */
class WhiteLabelIntakePdfController extends Controller
{
    public function __invoke(WhiteLabelProjectIntake $intake): Response
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $intake->load('instance');

        $pdf = Pdf::loadView('pdf.white-label-project-intake', ['intake' => $intake]);

        $filename = 'white-label-project-'.$intake->id.'-'.str($intake->desired_brand_name)->slug().'.pdf';

        return $pdf->download($filename);
    }
}
