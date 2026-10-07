<?php

namespace App\Services\Rapports;

use App\Enums\DocumentStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Entree;
use App\Models\Fournisseur;
use App\Models\MouvementStock;
use App\Models\Service;
use App\Models\Sortie;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Données des rapports (cahier §18) sous une forme unique (titre, filtres en clair, colonnes, lignes, totaux),
 * rendue telle quelle en PDF (pdf/rapport) ou en CSV. Seuls les documents VALIDÉS comptent : un brouillon n'a
 * aucun effet et une opération annulée est neutralisée par son contre-mouvement.
 */
class RapportBuilder
{
    public const TITLES = [
        'etat-stock' => 'État de stock',
        'entrees' => 'Rapport des entrées',
        'sorties' => 'Rapport des sorties',
        'ecarts' => 'Rapport des écarts de livraison',
    ];

    /**
     * @param  array<string, mixed>  $filters  du, au, category_id, fournisseur_id, service_id
     * @return array{title: string, subtitle: string, columns: list<string>, numeric: list<int>, rows: list<list<string>>, totals: list<string>|null, landscape: bool}
     */
    public function build(string $type, array $filters): array
    {
        return match ($type) {
            'etat-stock' => $this->etatStock($filters),
            'entrees' => $this->entrees($filters),
            'sorties' => $this->sorties($filters),
            'ecarts' => $this->ecarts($filters),
        };
    }

    /** État de stock à une date (par défaut aujourd'hui) : solde = somme des mouvements jusqu'à cette date (Annexe B). */
    private function etatStock(array $filters): array
    {
        $date = $filters['au'] ?? null;
        $balances = $date
            ? MouvementStock::where('moved_on', '<=', $date)->selectRaw('article_id, SUM(quantity) as total')->groupBy('article_id')->pluck('total', 'article_id')
            : null;

        $articles = Article::with(['category', 'unite'])
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('stock_quantity', '!=', 0))
            ->orderBy('designation')->get();

        $rows = $articles->map(function (Article $article) use ($balances) {
            $stock = $balances ? (float) ($balances[$article->id] ?? 0) : (float) $article->stock_quantity;
            $situation = match (true) {
                $stock <= 0 => 'Rupture',
                $article->min_threshold !== null && $stock < (float) $article->min_threshold => 'Sous le seuil',
                default => 'Normal',
            };

            return [$article->reference, $article->designation, $article->category?->name ?? '', $article->unite?->symbol ?? '', $article->thresholdForDisplay() ?? '', Quantity::format($stock), $situation];
        })->values()->all();

        return [
            'title' => self::TITLES['etat-stock'],
            'subtitle' => 'Situation au '.($date ? Carbon::parse($date)->format('d/m/Y') : now()->format('d/m/Y à H:i')).$this->categoryLabel($filters),
            'columns' => ['Référence', 'Désignation', 'Catégorie', 'Unité', 'Seuil', 'Stock', 'Situation'],
            'numeric' => [4, 5],
            'rows' => $rows,
            'totals' => ['', count($rows).' article(s)', '', '', '', '', collect($rows)->where(6, '!=', 'Normal')->count().' à surveiller'],
            'landscape' => false,
        ];
    }

    private function entrees(array $filters): array
    {
        $entrees = Entree::with(['fournisseur', 'details.article.unite'])
            ->where('status', DocumentStatus::Validee->value)
            ->when($filters['du'] ?? null, fn ($q, $d) => $q->whereDate('received_on', '>=', $d))
            ->when($filters['au'] ?? null, fn ($q, $d) => $q->whereDate('received_on', '<=', $d))
            ->when($filters['fournisseur_id'] ?? null, fn ($q, $id) => $q->where('fournisseur_id', $id))
            ->orderBy('received_on')->orderBy('id')->get();

        $rows = [];
        foreach ($entrees as $entree) {
            foreach ($entree->details as $detail) {
                $rows[] = [
                    $entree->received_on->format('d/m/Y'), $entree->number, $entree->fournisseur?->name ?? '', $entree->delivery_note_number ?? '',
                    $detail->article?->label() ?? '', $detail->article?->unite?->symbol ?? '',
                    Quantity::format($detail->delivered_qty), Quantity::format($detail->accepted_qty), (float) $detail->rejected_qty > 0 ? Quantity::format($detail->rejected_qty) : '',
                ];
            }
        }

        return [
            'title' => self::TITLES['entrees'],
            'subtitle' => $this->periodLabel($filters).$this->namedFilter($filters, 'fournisseur_id', Fournisseur::class, 'Fournisseur'),
            'columns' => ['Date', 'N° entrée', 'Fournisseur', 'Bordereau', 'Article', 'Unité', 'Livré', 'Accepté', 'Rejeté'],
            'numeric' => [6, 7, 8],
            'rows' => $rows,
            'totals' => ['', $entrees->count().' entrée(s)', '', '', count($rows).' ligne(s)', '', '', '', ''],
            'landscape' => true,
        ];
    }

    private function sorties(array $filters): array
    {
        $sorties = Sortie::with(['service', 'details.article.unite'])
            ->where('status', DocumentStatus::Validee->value)
            ->when($filters['du'] ?? null, fn ($q, $d) => $q->whereDate('issued_on', '>=', $d))
            ->when($filters['au'] ?? null, fn ($q, $d) => $q->whereDate('issued_on', '<=', $d))
            ->when($filters['service_id'] ?? null, fn ($q, $id) => $q->where('service_id', $id))
            ->orderBy('issued_on')->orderBy('id')->get();

        $rows = [];
        foreach ($sorties as $sortie) {
            foreach ($sortie->details as $detail) {
                $rows[] = [
                    $sortie->issued_on->format('d/m/Y'), $sortie->number, $sortie->service?->name ?? '', $sortie->requester_name,
                    $detail->article?->label() ?? '', $detail->article?->unite?->symbol ?? '', Quantity::format($detail->quantity), $sortie->reason,
                ];
            }
        }

        return [
            'title' => self::TITLES['sorties'],
            'subtitle' => $this->periodLabel($filters).$this->namedFilter($filters, 'service_id', Service::class, 'Service'),
            'columns' => ['Date', 'N° sortie', 'Service', 'Demandeur', 'Article', 'Unité', 'Quantité', 'Motif'],
            'numeric' => [6],
            'rows' => $rows,
            'totals' => ['', $sorties->count().' sortie(s)', '', '', count($rows).' ligne(s)', '', '', ''],
            'landscape' => true,
        ];
    }

    private function ecarts(array $filters): array
    {
        $entrees = Entree::with(['fournisseur', 'details.article'])
            ->where('status', DocumentStatus::Validee->value)
            ->when($filters['du'] ?? null, fn ($q, $d) => $q->whereDate('received_on', '>=', $d))
            ->when($filters['au'] ?? null, fn ($q, $d) => $q->whereDate('received_on', '<=', $d))
            ->when($filters['fournisseur_id'] ?? null, fn ($q, $id) => $q->where('fournisseur_id', $id))
            ->orderBy('received_on')->orderBy('id')->get();

        $rows = [];
        foreach ($entrees as $entree) {
            $planned = $entree->isPlanned();
            foreach ($entree->details as $detail) {
                if (($labels = $detail->discrepancies($planned)) === []) {
                    continue;
                }
                $rows[] = [
                    $entree->received_on->format('d/m/Y'), $entree->number, $entree->fournisseur?->name ?? '', $detail->article?->label() ?? '',
                    Quantity::format($detail->expected_qty) ?? '', Quantity::format($detail->delivered_qty), Quantity::format($detail->accepted_qty),
                    implode(', ', $labels), $detail->observation ?? '',
                ];
            }
        }

        return [
            'title' => self::TITLES['ecarts'],
            'subtitle' => $this->periodLabel($filters).$this->namedFilter($filters, 'fournisseur_id', Fournisseur::class, 'Fournisseur'),
            'columns' => ['Date', 'N° entrée', 'Fournisseur', 'Article', 'Prévu', 'Livré', 'Accepté', 'Écart', 'Observation'],
            'numeric' => [4, 5, 6],
            'rows' => $rows,
            'totals' => null,
            'landscape' => true,
        ];
    }

    private function periodLabel(array $filters): string
    {
        $du = isset($filters['du']) ? Carbon::parse($filters['du'])->format('d/m/Y') : null;
        $au = isset($filters['au']) ? Carbon::parse($filters['au'])->format('d/m/Y') : null;

        return match (true) {
            $du && $au => "Du {$du} au {$au}",
            (bool) $du => "Depuis le {$du}",
            (bool) $au => "Jusqu'au {$au}",
            default => 'Toute la période',
        };
    }

    private function categoryLabel(array $filters): string
    {
        return $this->namedFilter($filters, 'category_id', Category::class, 'Catégorie');
    }

    /** @param class-string<Model> $model */
    private function namedFilter(array $filters, string $key, string $model, string $label): string
    {
        $name = isset($filters[$key]) ? $model::withTrashed()->whereKey($filters[$key])->value('name') : null;

        return $name ? " · {$label} : {$name}" : '';
    }
}
