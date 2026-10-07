<?php

namespace App\Services\Articles;

use App\Models\Article;
use App\Models\Category;
use App\Models\Unite;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Import des articles depuis un fichier tableur (Excel, LibreOffice, CSV) et modèle à remplir. Colonnes de la fiche
 * article (cahier §9). Import en TOUT OU RIEN : la moindre erreur et rien n'est enregistré ; le rapport donne la
 * ligne, la colonne et le motif. Catégorie inconnue : créée ; unité inconnue : refusée (elle porte la règle des
 * décimales) ; référence existante : ignorée, ou mise à jour si demandé. Chaque article créé ou modifié est audité
 * (LogsActivity), plus un résumé `articles.imported`.
 */
class ArticleImportService
{
    public const MAX_ROWS = 2000;

    /** En-têtes du modèle : clé => [libellé, obligatoire]. */
    public const COLUMNS = [
        'reference' => ['Référence', true],
        'designation' => ['Désignation', true],
        'category' => ['Catégorie', true],
        'unite' => ['Unité', true],
        'brand_model' => ['Marque / modèle', false],
        'min_threshold' => ['Seuil minimum', false],
        'location' => ['Emplacement', false],
        'observation' => ['Observation', false],
        'status' => ['Statut', false],
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Modèle à remplir : feuille « Articles » (en-têtes, listes déroulantes), « Listes » (catégories, unités,
     * statuts de l'application) et « Mode d'emploi ».
     */
    public function template(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('Modèle d\'import des articles')->setCreator(config('brand.name'));

        $categories = Category::active()->orderBy('name')->pluck('name')->all();
        $unites = Unite::active()->orderBy('name')->get(['name', 'symbol']);

        // Feuille « Listes » : valeurs de référence des listes déroulantes.
        $lists = $book->getActiveSheet()->setTitle('Listes');
        $lists->fromArray(['Catégories', 'Unités', 'Statuts'], null, 'A1');
        foreach ($categories as $i => $name) {
            $lists->setCellValue('A'.($i + 2), $name);
        }
        foreach ($unites as $i => $unite) {
            $lists->setCellValue('B'.($i + 2), $unite->name);
        }
        $lists->setCellValue('C2', 'Actif')->setCellValue('C3', 'Inactif');
        $this->styleHeader($lists, 'A1:C1');
        foreach (['A' => 34, 'B' => 18, 'C' => 12] as $col => $width) {
            $lists->getColumnDimension($col)->setWidth($width);
        }

        // Feuille « Articles » : celle qu'on remplit (première feuille du classeur).
        $sheet = $book->createSheet(0)->setTitle('Articles');
        $headers = array_map(fn ($column) => $column[0].($column[1] ? ' *' : ''), self::COLUMNS);
        $sheet->fromArray(array_values($headers), null, 'A1');
        $this->styleHeader($sheet, 'A1:I1');
        $sheet->freezePane('A2');
        foreach (['A' => 16, 'B' => 42, 'C' => 26, 'D' => 14, 'E' => 22, 'F' => 15, 'G' => 18, 'H' => 40, 'I' => 11] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $rows = self::MAX_ROWS + 1;
        $this->listValidation($sheet, "C2:C{$rows}", '=Listes!$A$2:$A$'.max(2, count($categories) + 1), DataValidation::STYLE_INFORMATION,
            'Catégorie', 'Choisissez une catégorie de la liste, ou tapez-en une nouvelle : elle sera créée à l\'import.');
        $this->listValidation($sheet, "D2:D{$rows}", '=Listes!$B$2:$B$'.max(2, $unites->count() + 1), DataValidation::STYLE_STOP,
            'Unité', 'Choisissez une unité de la liste (une nouvelle unité se crée d\'abord dans l\'application).');
        $this->listValidation($sheet, "I2:I{$rows}", '=Listes!$C$2:$C$3', DataValidation::STYLE_STOP, 'Statut', 'Actif (par défaut si vide) ou Inactif.');
        $sheet->getStyle("F2:F{$rows}")->getNumberFormat()->setFormatCode('0.###');
        $sheet->getStyle("A2:A{$rows}")->getNumberFormat()->setFormatCode('@');

        // Feuille « Mode d'emploi ».
        $help = $book->createSheet()->setTitle('Mode d\'emploi');
        $lines = [
            ['Import des articles — mode d\'emploi'],
            [''],
            ['1. Remplissez la feuille « Articles » : une ligne par article, à partir de la ligne 2. Ne modifiez pas la ligne des titres.'],
            ['2. Colonnes obligatoires (*) : Référence (unique, lettres, chiffres, espaces et . _ / -), Désignation, Catégorie, Unité.'],
            ['3. Catégorie : choisissez-la dans la liste ; une catégorie nouvelle est créée automatiquement à l\'import.'],
            ['4. Unité : obligatoirement une unité de la liste (nom ou symbole). Une nouvelle unité se crée d\'abord dans Articles → Unités.'],
            ['5. Seuil minimum : nombre positif ; décimales seulement si l\'unité les admet (mètre…). Laissez vide si aucun seuil.'],
            ['6. Statut : Actif (par défaut) ou Inactif. Le stock ne s\'importe pas : il se constitue par les entrées.'],
            ['7. Une référence déjà connue est ignorée, sauf si vous cochez « Mettre à jour les articles existants » au moment de l\'import.'],
            ['8. L\'import est « tout ou rien » : s\'il y a une erreur, rien n\'est enregistré et la liste des erreurs vous est affichée.'],
            ['9. '.self::MAX_ROWS.' articles au plus par fichier. Formats acceptés : .xlsx, .xls, .ods, .csv.'],
            [''],
            ['Exemple : ELEC-001 | Réglette complète 1,20 m Ingelec LED | Éclairage | Unité | Ingelec | 20 | Rayon A1 | | Actif'],
        ];
        $help->fromArray($lines, null, 'A1');
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $help->getColumnDimension('A')->setWidth(120);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * Importe un fichier. Rien n'est enregistré s'il y a une erreur.
     *
     * @return array{ok: bool, errors: list<array{line: int, column: string, message: string}>, created: int, updated: int, skipped: int, categories: list<string>}
     */
    public function import(User $user, string $path, string $originalName, bool $updateExisting): array
    {
        $report = ['ok' => false, 'errors' => [], 'created' => 0, 'updated' => 0, 'skipped' => 0, 'categories' => []];
        $fail = function (int $line, string $column, string $message) use (&$report) {
            $report['errors'][] = compact('line', 'column', 'message');
        };

        try {
            $reader = IOFactory::createReaderForFile($path);
            if ($reader instanceof Csv) {
                $reader->setInputEncoding(Csv::GUESS_ENCODING)->setDelimiter(null); // « ; » d'Excel en français ou « , »
            }
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (Throwable) {
            $fail(0, '—', 'Fichier illisible : utilisez le modèle Excel (.xlsx), ou un fichier .xls, .ods ou .csv.');

            return $report;
        }

        $sheet = $book->getSheetByName('Articles') ?? $book->getSheet(0);
        $raw = $sheet->toArray(null, true, false, false);
        $map = $this->mapHeaders($raw[0] ?? []);

        foreach (self::COLUMNS as $key => [$label, $required]) {
            if ($required && ! isset($map[$key])) {
                $fail(1, $label, 'Colonne obligatoire absente : téléchargez et utilisez le modèle.');
            }
        }
        if ($report['errors'] !== []) {
            return $report;
        }

        // Lignes de données (les lignes entièrement vides sont ignorées).
        $rows = [];
        foreach (array_slice($raw, 1, null, true) as $index => $cells) {
            $row = [];
            // Toutes les colonnes du modèle : une colonne facultative absente du fichier vaut « vide ».
            foreach (array_keys(self::COLUMNS) as $key) {
                $value = isset($map[$key]) ? ($cells[$map[$key]] ?? null) : null;
                $row[$key] = is_string($value) ? trim($value) : $value;
                $row[$key] = $row[$key] === '' ? null : $row[$key];
            }
            if (array_filter($row, fn ($v) => $v !== null) !== []) {
                $rows[$index + 1] = $row; // numéro de ligne Excel
            }
        }

        if ($rows === []) {
            $fail(2, '—', 'Le fichier ne contient aucun article (remplissez la feuille « Articles » à partir de la ligne 2).');

            return $report;
        }
        if (count($rows) > self::MAX_ROWS) {
            $fail(0, '—', 'Trop de lignes : '.self::MAX_ROWS.' articles au plus par fichier.');

            return $report;
        }

        $unites = Unite::all();
        $findUnite = fn (string $value) => $unites->first(fn (Unite $u) => mb_strtolower($u->name) === mb_strtolower($value) || mb_strtolower($u->symbol) === mb_strtolower($value));
        $existing = Article::withTrashed()->pluck('id', 'reference')->all();
        $seen = [];
        $clean = [];

        foreach ($rows as $line => $row) {
            $reference = mb_strtoupper((string) ($row['reference'] ?? ''));
            $label = self::COLUMNS;

            if ($reference === '') {
                $fail($line, $label['reference'][0], 'Référence obligatoire.');
            } elseif (mb_strlen($reference) > 50 || ! preg_match('/^[A-Z0-9][A-Z0-9 ._\/-]*$/u', $reference)) {
                $fail($line, $label['reference'][0], "Référence « {$reference} » invalide (50 caractères au plus : lettres, chiffres, espaces et . _ / -).");
            } elseif (isset($seen[$reference])) {
                $fail($line, $label['reference'][0], "Référence « {$reference} » déjà présente ligne {$seen[$reference]} du fichier.");
            }
            $seen[$reference] = $seen[$reference] ?? $line;

            $designation = (string) ($row['designation'] ?? '');
            if ($designation === '' || mb_strlen($designation) > 200) {
                $fail($line, $label['designation'][0], $designation === '' ? 'Désignation obligatoire.' : 'Désignation trop longue (200 caractères au plus).');
            }

            $category = (string) ($row['category'] ?? '');
            if ($category === '' || mb_strlen($category) > 100) {
                $fail($line, $label['category'][0], $category === '' ? 'Catégorie obligatoire.' : 'Catégorie trop longue (100 caractères au plus).');
            }

            $unite = $row['unite'] !== null ? $findUnite((string) $row['unite']) : null;
            if ($unite === null) {
                $fail($line, $label['unite'][0], $row['unite'] === null ? 'Unité obligatoire.' : "Unité « {$row['unite']} » inconnue : choisissez une unité de la liste.");
            }

            $threshold = $row['min_threshold'];
            if ($threshold !== null) {
                $threshold = str_replace([',', ' '], ['.', ''], (string) $threshold);
                if (! is_numeric($threshold) || (float) $threshold < 0) {
                    $fail($line, $label['min_threshold'][0], 'Seuil minimum : nombre positif attendu.');
                } elseif ($unite && ! $unite->allows_decimal && floor((float) $threshold) != (float) $threshold) {
                    $fail($line, $label['min_threshold'][0], "L'unité « {$unite->name} » n'admet pas de quantité décimale.");
                }
            }

            $status = mb_strtolower((string) ($row['status'] ?? 'actif'));
            if (! in_array($status, ['actif', 'inactif', 'oui', 'non', '1', '0'], true)) {
                $fail($line, $label['status'][0], 'Statut : « Actif » ou « Inactif ».');
            }

            foreach (['brand_model' => 150, 'location' => 100, 'observation' => 2000] as $key => $max) {
                if ($row[$key] !== null && mb_strlen((string) $row[$key]) > $max) {
                    $fail($line, $label[$key][0], "{$max} caractères au plus.");
                }
            }

            $clean[$line] = [
                'reference' => $reference, 'designation' => $designation, 'category' => $category, 'unite_id' => $unite?->id,
                'brand_model' => $row['brand_model'] !== null ? (string) $row['brand_model'] : null,
                'min_threshold' => $threshold, 'location' => $row['location'] !== null ? (string) $row['location'] : null,
                'observation' => $row['observation'] !== null ? (string) $row['observation'] : null,
                'is_active' => in_array($status, ['actif', 'oui', '1'], true),
            ];
        }

        if ($report['errors'] !== []) {
            return $report;
        }

        DB::transaction(function () use ($user, $clean, $existing, $updateExisting, $originalName, &$report) {
            $categories = Category::withTrashed()->get()->keyBy(fn (Category $c) => mb_strtolower($c->name));

            foreach ($clean as $data) {
                $key = mb_strtolower($data['category']);
                if (! isset($categories[$key])) {
                    $categories[$key] = Category::create(['name' => $data['category']]);
                    $report['categories'][] = $data['category'];
                }

                $attributes = ['category_id' => $categories[$key]->id] + array_diff_key($data, ['category' => true]);

                if (isset($existing[$data['reference']])) {
                    if (! $updateExisting) {
                        $report['skipped']++;

                        continue;
                    }
                    $article = Article::withTrashed()->findOrFail($existing[$data['reference']]);
                    $article->fill($attributes);
                    if ($article->isDirty()) {
                        $article->save();
                        $report['updated']++;
                    } else {
                        $report['skipped']++;
                    }
                } else {
                    Article::create($attributes);
                    $report['created']++;
                }
            }

            $this->logger->log('articles.imported', 'articles', "Import d'articles — {$originalName}", new: [
                'fichier' => $originalName, 'crees' => $report['created'], 'mis_a_jour' => $report['updated'],
                'ignores' => $report['skipped'], 'categories_creees' => $report['categories'],
            ], actor: $user);
        });

        $report['ok'] = true;

        return $report;
    }

    /**
     * Repère les colonnes par leur titre (insensible à la casse, aux accents, à « * »), quel que soit leur ordre.
     *
     * @param  array<int, mixed>  $headers
     * @return array<string, int>
     */
    private function mapHeaders(array $headers): array
    {
        $normalize = fn ($v) => Str::of((string) $v)->replace('*', '')->ascii()->lower()->replaceMatches('/[^a-z]+/', '')->toString();
        $wanted = collect(self::COLUMNS)->mapWithKeys(fn ($column, $key) => [$normalize($column[0]) => $key])->all()
            + ['marque' => 'brand_model', 'modele' => 'brand_model', 'seuil' => 'min_threshold', 'unite' => 'unite', 'ref' => 'reference'];

        $map = [];
        foreach ($headers as $index => $header) {
            $key = $wanted[$normalize($header)] ?? null;
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D4E89');
    }

    private function listValidation(Worksheet $sheet, string $range, string $formula, string $errorStyle, string $title, string $prompt): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle($errorStyle)->setAllowBlank(true)
            ->setShowDropDown(true)->setShowInputMessage(true)->setShowErrorMessage(true)
            ->setPromptTitle($title)->setPrompt($prompt)->setErrorTitle($title)->setError($prompt)
            ->setFormula1($formula);
        $sheet->setDataValidation($range, $validation);
    }
}
