<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        $invoice->load(['subscription.church', 'subscription.plan']);
        $church = $invoice->subscription?->church;
        $plan = $invoice->subscription?->plan;
        $branding = app(\App\Services\Themes\ThemeCompiler::class)->resolveForPdf();

        $html = View::make('billing.invoice-pdf', [
            'invoice' => $invoice,
            'church' => $church,
            'plan' => $plan,
            'brandName' => $branding['app_name'] ?? 'Hope Works',
            'themeColor' => $branding['primary'] ?? '#3E5C82',
            'footerText' => $branding['footer_text'] ?? '',
        ])->render();

        $path = 'invoices/'.$invoice->id.'/invoice-'.$invoice->id.'.html';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $path = 'invoices/'.$invoice->id.'/invoice-'.$invoice->id.'.pdf';
            Storage::disk('local')->put($path, $pdf->output());
        } else {
            Storage::disk('local')->put($path, $html);
        }

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }
}
