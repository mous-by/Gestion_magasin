<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Support\DocumentSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paramètres → Documents : en-tête officiel, logo, signataires de chaque document (libellé + nom fixe ou jeton
 * du document) et numérotation. Rien n'est figé dans le code : tout se règle ici, audité (AppSetting).
 */
class DocumentSettingsController extends Controller
{
    public function __construct(private readonly DocumentSettings $settings) {}

    public function edit(): View
    {
        return view('documents-settings.index', [
            'header' => $this->settings->header(),
            'signatures' => collect(DocumentSettings::DOCUMENTS)->mapWithKeys(fn ($label, $doc) => [$doc => $this->settings->rawSignatures($doc)])->all(),
            'numbering' => [
                'format' => $this->settings->numberFormat(),
                'padding' => $this->settings->numberPadding(),
                'prefixes' => ['entree' => $this->settings->prefix('entree'), 'sortie' => $this->settings->prefix('sortie'), 'regularisation' => $this->settings->prefix('regularisation')],
            ],
            'logo' => $this->settings->logoDataUri(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'numbering.format' => ['required', 'string', 'max:60', 'regex:/\{number\}/', 'regex:/\{year\}/'],
            'numbering.padding' => ['required', 'integer', 'min:1', 'max:8'],
            'numbering.prefixes.*' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/'],
            'signatures.*.*.label' => ['nullable', 'string', 'max:80'],
            'signatures.*.*.name' => ['nullable', 'string', 'max:120'],
        ];
        foreach (array_keys(DocumentSettings::HEADER) as $key) {
            $rules["header.{$key}"] = [$key === 'service' ? 'nullable' : 'required', 'string', 'max:150'];
        }

        $data = $request->validate($rules, [
            'numbering.format.regex' => 'Le format doit contenir {year} et {number} : sinon deux documents pourraient porter le même numéro (RG10).',
            'numbering.prefixes.*.regex' => 'Un préfixe ne contient que des lettres, chiffres, tirets et tirets bas.',
        ]);

        $prefixes = $data['numbering']['prefixes'] ?? [];
        if (count(array_unique(array_map('mb_strtoupper', $prefixes))) !== count($prefixes)) {
            return back()->withInput()->withErrors(['numbering.prefixes' => 'Chaque type de document doit avoir son propre préfixe.']);
        }

        $values = [
            'numbering.format' => trim($data['numbering']['format']),
            'numbering.padding' => (string) $data['numbering']['padding'],
        ];
        foreach ($prefixes as $type => $prefix) {
            if (array_key_exists($type, config('documents.prefixes'))) {
                $values["numbering.prefix.{$type}"] = trim($prefix);
            }
        }
        foreach (array_keys(DocumentSettings::HEADER) as $key) {
            $values["header.{$key}"] = trim((string) ($data['header'][$key] ?? ''));
        }
        foreach (array_keys(DocumentSettings::DOCUMENTS) as $doc) {
            $slots = array_values($data['signatures'][$doc] ?? []);
            $values["signatures.{$doc}"] = json_encode(array_map(fn ($i) => [
                'label' => trim((string) ($slots[$i]['label'] ?? '')),
                'name' => trim((string) ($slots[$i]['name'] ?? '')),
            ], [0, 1, 2]), JSON_UNESCAPED_UNICODE);
        }

        $this->settings->save($values);

        return redirect()->route('documents.settings')->with('status', 'Paramètres des documents enregistrés : ils s\'appliquent aux prochains documents imprimés.');
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:1024']], [
            'logo.max' => 'Le logo ne doit pas dépasser 1 Mo.',
            'logo.mimes' => 'Le logo doit être une image PNG ou JPEG.',
        ]);

        $this->settings->storeLogo($request->file('logo'));

        return redirect()->route('documents.settings')->with('status', 'Logo enregistré.');
    }

    public function removeLogo(): RedirectResponse
    {
        $this->settings->removeLogo();

        return redirect()->route('documents.settings')->with('status', 'Logo retiré : le nom de la structure s\'affiche à sa place.');
    }

    /** Aperçu d'un bon de sortie fictif avec les paramètres enregistrés. */
    public function preview(): Response
    {
        return Pdf::loadView('pdf.apercu', [])->setPaper('a4')->stream('apercu-document.pdf');
    }
}
