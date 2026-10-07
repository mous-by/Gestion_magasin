<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Fournisseur;
use App\Models\Service;
use App\Services\Audit\ActivityLogger;
use App\Services\Rapports\RapportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapports (phase 7, cahier §17, §18) : un formulaire par rapport, sortie PDF (rapports.view) ou CSV pour tableur
 * (rapports.export). L'export est journalisé (données sortant de l'application).
 */
class RapportController extends Controller
{
    public function index(): View
    {
        return view('rapports.index', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'fournisseurs' => Fournisseur::orderBy('name')->get(['id', 'name']),
            'services' => Service::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, string $type, RapportBuilder $builder, ActivityLogger $logger): Response
    {
        $filters = array_filter($request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'fournisseur_id' => ['nullable', 'integer', 'exists:fournisseurs,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'format' => ['nullable', 'in:pdf,csv'],
        ], ['au.after_or_equal' => 'La date de fin doit suivre la date de début.']), fn ($v) => $v !== null && $v !== '');

        $format = $filters['format'] ?? 'pdf';
        unset($filters['format']);
        $report = $builder->build($type, $filters);
        $filename = Str::slug($report['title']).'-'.now()->format('Ymd-His');

        if ($format === 'csv') {
            Gate::authorize('rapports.export');
            $logger->log('rapports.exported', 'rapports', "Export tableur — {$report['title']}", new: ['filtres' => $filters, 'lignes' => count($report['rows'])]);

            return $this->csv($report, $filename.'.csv');
        }

        return Pdf::loadView('pdf.rapport', ['report' => $report])
            ->setPaper('a4', $report['landscape'] ? 'landscape' : 'portrait')
            ->stream($filename.'.pdf');
    }

    /** CSV pour Excel : UTF-8 avec BOM (accents), séparateur « ; » (réglage français d'Excel). */
    private function csv(array $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$report['title'].' — '.$report['subtitle']], ';');
            fputcsv($out, $report['columns'], ';');
            foreach ($report['rows'] as $row) {
                // Quantités sans espace des milliers, pour qu'Excel les lise comme des nombres (« 1250 », « 2,5 »).
                foreach ($report['numeric'] as $column) {
                    $row[$column] = str_replace(' ', '', (string) $row[$column]);
                }
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
