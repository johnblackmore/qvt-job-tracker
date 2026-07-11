<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class QuotePdfController extends Controller
{
    public function download(Quote $quote): Response
    {
        $quote->load(['customer', 'lineItems']);

        $pdf = Pdf::loadView('pdf.quote', compact('quote'));
        $pdf->setPaper('a4');

        $filename = sprintf('QVT-Quote-%s.pdf', $quote->reference_number);

        return $pdf->download($filename);
    }

    public function stream(Quote $quote): Response
    {
        $quote->load(['customer', 'lineItems']);

        $pdf = Pdf::loadView('pdf.quote', compact('quote'));
        $pdf->setPaper('a4');

        return $pdf->stream(sprintf('QVT-Quote-%s.pdf', $quote->reference_number));
    }
}
