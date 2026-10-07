<?php

namespace App\Support;

/**
 * Diapositives de la page de connexion (config/brand.php). Une image se dépose dans public/assets/images/login/ sous le
 * nom déclaré (`slide-1.jpg`, `.jpeg`, `.webp` ou `.png`) ; sans image, la diapositive garde son dégradé de repli.
 */
class LoginSlides
{
    public const DIRECTORY = 'assets/images/login';

    public const DEFAULT_POSITION = 'center';

    /** Ordre de priorité si plusieurs formats du même nom coexistent. */
    public const EXTENSIONS = ['jpg', 'jpeg', 'webp', 'png'];

    /**
     * @return list<array{file: string, icon: string, badge: string, title: string, text: string, position: string, image: ?string}>
     */
    public function all(): array
    {
        return collect(config('brand.login_slides', []))
            ->map(fn (array $slide) => $slide + ['image' => null, 'position' => self::DEFAULT_POSITION])
            ->map(function (array $slide) {
                $slide['image'] = $this->imageUrl($slide['file']);

                return $slide;
            })
            ->values()
            ->all();
    }

    /** Dossier des images, relatif à public/. */
    public function directory(): string
    {
        return trim((string) config('brand.login_images_directory', self::DIRECTORY), '/');
    }

    /** URL de l'image (avec l'horodatage du fichier pour vider le cache du navigateur), ou null si elle n'existe pas. */
    private function imageUrl(string $basename): ?string
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = $this->directory().'/'.$basename.'.'.$extension;

            if (is_file(public_path($path))) {
                return asset($path).'?v='.filemtime(public_path($path));
            }
        }

        return null;
    }
}
