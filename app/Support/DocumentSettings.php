<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Paramètres des documents imprimés, modifiables depuis l'interface (Paramètres → Documents) : en-tête officiel,
 * logo, signataires de chaque document et numérotation. Une valeur jamais enregistrée reprend la valeur par défaut
 * (config/brand.php, config/documents.php, donc .env). Les noms des signataires peuvent être fixes (« M. Souleymane
 * MAKADJI ») ou venir du document lui-même grâce aux jetons de TOKENS (demandeur, agent qui a validé…).
 */
class DocumentSettings
{
    private const CACHE_KEY = 'app_settings';

    /** Champs de l'en-tête officiel : clé => libellé du formulaire. */
    public const HEADER = [
        'ministry' => 'Ministère',
        'country' => 'Pays',
        'motto' => 'Devise',
        'structure' => 'Structure',
        'entity' => 'Entité (encadré au-dessus du titre)',
        'service' => 'Service émetteur',
        'city' => 'Ville (« Ségou, le … »)',
    ];

    /** Documents qui portent des signatures : clé => libellé. */
    public const DOCUMENTS = [
        'bon_sortie' => 'Bon de sortie',
        'fiche_entree' => 'Bon d\'entrée du magasin',
        'fiche_mouvement' => 'Fiche de mouvement d\'un article',
        'rapport' => 'Rapports et états',
    ];

    /** Jetons utilisables dans le nom d'un signataire, remplacés par les données du document. */
    public const TOKENS = [
        '{demandeur}' => 'Demandeur de la sortie',
        '{service}' => 'Service bénéficiaire de la sortie',
        '{fournisseur}' => 'Fournisseur de l\'entrée',
        '{valide_par}' => 'Agent qui a validé le document',
        '{saisi_par}' => 'Agent qui a saisi le document',
        '{utilisateur}' => 'Agent qui imprime le document',
    ];

    /** Signataires par défaut (relevés sur les documents de l'Université) : [libellé, nom]. */
    private const DEFAULT_SIGNATURES = [
        'bon_sortie' => [['Le Demandeur', '{demandeur}'], ['Le Comptable Matière', '{valide_par}'], ['Visa du Chef de Service des Finances', '']],
        'fiche_entree' => [['Le Livreur', '{fournisseur}'], ['Le Comptable Matière', '{valide_par}'], ['Le Magasinier', '']],
        'fiche_mouvement' => [['Le Comptable Matière', '{utilisateur}'], ['', ''], ['Le Magasinier', '']],
        'rapport' => [['Le Comptable Matière', '{utilisateur}'], ['', ''], ['Le Magasinier', '']],
    ];

    public const LOGO_DIRECTORY = 'brand';

    public function get(string $key): ?string
    {
        $stored = $this->stored();

        return array_key_exists($key, $stored) ? $stored[$key] : $this->default($key);
    }

    /** @return array<string, string> */
    public function header(): array
    {
        return collect(array_keys(self::HEADER))->mapWithKeys(fn ($key) => [$key => (string) $this->get("header.{$key}")])->all();
    }

    /**
     * Signataires d'un document, jetons remplacés par le contexte (valeurs absentes = vide).
     *
     * @param  array<string, ?string>  $context  ex. ['demandeur' => 'M. Keïta', 'valide_par' => 'Awa Traoré']
     * @return list<array{label: string, name: string}>
     */
    public function signatures(string $document, array $context = []): array
    {
        $context += ['utilisateur' => auth()->user()?->name];
        $replace = collect(self::TOKENS)->keys()->mapWithKeys(fn ($token) => [$token => (string) ($context[trim($token, '{}')] ?? '')])->all();

        return array_map(fn (array $slot) => [
            'label' => (string) $slot['label'],
            'name' => trim(strtr((string) $slot['name'], $replace)),
        ], $this->rawSignatures($document));
    }

    /**
     * Signataires tels qu'enregistrés (jetons non remplacés), pour le formulaire.
     *
     * @return list<array{label: string, name: string}>
     */
    public function rawSignatures(string $document): array
    {
        $stored = json_decode((string) $this->get("signatures.{$document}"), true);

        return is_array($stored) && count($stored) === 3
            ? array_map(fn ($slot) => ['label' => (string) ($slot['label'] ?? ''), 'name' => (string) ($slot['name'] ?? '')], $stored)
            : array_map(fn ($slot) => ['label' => $slot[0], 'name' => $slot[1]], self::DEFAULT_SIGNATURES[$document] ?? [['', ''], ['', ''], ['', '']]);
    }

    public function numberFormat(): string
    {
        return (string) $this->get('numbering.format');
    }

    public function numberPadding(): int
    {
        return max(1, min(8, (int) $this->get('numbering.padding')));
    }

    public function prefix(string $type): string
    {
        return (string) $this->get("numbering.prefix.{$type}");
    }

    /** Chemin absolu du logo enregistré, sinon de celui déposé dans public/ (ancienne méthode), sinon null. */
    public function logoPath(): ?string
    {
        $stored = $this->get('logo.path');
        if ($stored && Storage::disk('local')->exists($stored)) {
            return Storage::disk('local')->path($stored);
        }

        return collect(config('brand.logo_candidates', []))->map(fn ($path) => public_path($path))->first(fn ($path) => is_file($path));
    }

    /** Logo en data URI (PDF et aperçu), sans exposer de fichier public. */
    public function logoDataUri(): ?string
    {
        $path = $this->logoPath();

        return $path ? 'data:'.mime_content_type($path).';base64,'.base64_encode((string) file_get_contents($path)) : null;
    }

    /**
     * Enregistre des valeurs (clé => valeur). Une valeur égale au défaut supprime la surcharge. Audité par AppSetting.
     *
     * @param  array<string, ?string>  $values
     */
    public function save(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                $setting = AppSetting::firstWhere('key', $key);

                if ($value === $this->default($key) || $value === null) {
                    $setting?->delete();

                    continue;
                }

                $setting ? $setting->update(['value' => $value]) : AppSetting::create(['key' => $key, 'value' => $value]);
            }
        });

        Cache::forget(self::CACHE_KEY);
    }

    public function storeLogo(UploadedFile $file): void
    {
        $previous = $this->stored()['logo.path'] ?? null;
        $path = $file->storeAs(self::LOGO_DIRECTORY, 'logo-'.now()->format('YmdHis').'.'.$file->extension(), 'local');
        $this->save(['logo.path' => $path]);

        if ($previous && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }
    }

    public function removeLogo(): void
    {
        $previous = $this->stored()['logo.path'] ?? null;
        $this->save(['logo.path' => null]);

        if ($previous) {
            Storage::disk('local')->delete($previous);
        }
    }

    public function default(string $key): ?string
    {
        return match (true) {
            str_starts_with($key, 'header.') => config('brand.'.substr($key, 7)),
            str_starts_with($key, 'signatures.') => json_encode(array_map(
                fn ($slot) => ['label' => $slot[0], 'name' => $slot[1]],
                self::DEFAULT_SIGNATURES[substr($key, 11)] ?? [],
            ), JSON_UNESCAPED_UNICODE),
            $key === 'numbering.format' => config('documents.number_format'),
            $key === 'numbering.padding' => (string) config('documents.number_padding'),
            str_starts_with($key, 'numbering.prefix.') => config('documents.prefixes.'.substr($key, 17)),
            default => null,
        };
    }

    /**
     * Valeurs enregistrées en base (cache vidé à chaque enregistrement). Base non migrée : aucune surcharge.
     *
     * @return array<string, ?string>
     */
    private function stored(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => Schema::hasTable('app_settings') ? AppSetting::pluck('value', 'key')->all() : []);
        } catch (Throwable) {
            return [];
        }
    }
}
