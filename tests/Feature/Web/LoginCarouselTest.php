<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\LoginSlides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Page de connexion : formulaire à droite, diaporama d'images et légendes à gauche (config/brand.php).
 */
class LoginCarouselTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private const TEST_DIRECTORY = 'assets/images/_test_login';

    protected function setUp(): void
    {
        parent::setUp();

        // Dossier temporaire : ces tests ne dépendent pas des vraies images livrées.
        config(['brand.login_images_directory' => self::TEST_DIRECTORY]);
        @mkdir(public_path(self::TEST_DIRECTORY), 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path(self::TEST_DIRECTORY).'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir(public_path(self::TEST_DIRECTORY));

        parent::tearDown();
    }

    private function dropImage(string $name): string
    {
        $path = public_path(self::TEST_DIRECTORY.'/'.$name);
        file_put_contents($path, 'fake-image-bytes');

        return $path;
    }

    public function test_the_login_form_sits_on_the_right_of_the_slideshow(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('login-page login-page--right', false)
            ->assertSee('bg-slideshow', false)
            ->assertSee('bg-caption-wrap', false)
            ->assertSee('id="loginForm"', false);
    }

    public function test_every_configured_slide_is_rendered_with_its_caption_and_a_dot(): void
    {
        $slides = config('brand.login_slides');
        $this->assertGreaterThanOrEqual(3, count($slides));

        $page = $this->get('/login')->assertOk();

        $page->assertSee('class="bg-dots"', false);
        foreach ($slides as $index => $slide) {
            $page->assertSee($slide['title'])->assertSee($slide['badge'])->assertSee($slide['icon'], false);
        }
        $html = $page->getContent();
        $this->assertSame(count($slides), substr_count($html, 'class="bg-slide bg-slide--'));
        $this->assertSame(count($slides), preg_match_all('/class="bg-dot[ "]/', $html), 'Un point par diapositive (le conteneur bg-dots n\'est pas un point).');
        $this->assertSame(count($slides), preg_match_all('/class="bg-caption[ "]/', $html));
        $this->assertSame(1, preg_match_all('/class="bg-slide [^"]*\bactive\b/', $html), 'Une seule diapositive active au départ.');
    }

    public function test_without_images_the_slides_still_rotate_with_a_gradient_fallback(): void
    {
        foreach ((new LoginSlides)->all() as $slide) {
            $this->assertNull($slide['image']);
        }

        $html = $this->get('/login')->getContent();

        $this->assertStringNotContainsString('background-image: url(', $html);
        $this->assertStringContainsString('bg-slide--1', $html);
    }

    public function test_an_image_dropped_by_name_is_used_for_its_slide_only(): void
    {
        $this->dropImage('slide-2.jpg');

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, "background-image: url('"));
        $this->assertMatchesRegularExpression('#slide-2\\.jpg\\?v=\\d+#', $html);
    }

    public function test_jpg_jpeg_webp_and_png_are_all_accepted_and_jpg_wins(): void
    {
        $this->dropImage('slide-1.webp');
        $this->dropImage('slide-3.png');
        $this->dropImage('slide-4.jpeg');

        $images = collect((new LoginSlides)->all())->pluck('image', 'file');
        $this->assertStringContainsString('slide-1.webp', $images['slide-1']);
        $this->assertStringContainsString('slide-3.png', $images['slide-3']);
        $this->assertStringContainsString('slide-4.jpeg', $images['slide-4']);
        $this->assertNull($images['slide-2']);

        $this->dropImage('slide-1.jpg');
        $this->assertStringContainsString('slide-1.jpg', collect((new LoginSlides)->all())->firstWhere('file', 'slide-1')['image']);
    }

    public function test_an_unknown_extension_is_ignored(): void
    {
        $this->dropImage('slide-5.gif');

        $this->assertNull(collect((new LoginSlides)->all())->firstWhere('file', 'slide-5')['image']);
    }

    public function test_a_slide_position_is_applied_to_its_image_and_defaults_to_center(): void
    {
        $this->dropImage('slide-1.jpg');
        config(['brand.login_slides.0.position' => '10% 20%']);

        $this->get('/login')->assertSee('background-position: 10% 20%;', false);

        $slides = collect((new LoginSlides)->all());
        config(['brand.login_slides' => [['file' => 'x', 'icon' => 'bx bx-x', 'badge' => 'b', 'title' => 't', 'text' => 'x']]]);
        $this->assertSame('center', (new LoginSlides)->all()[0]['position']);
        $this->assertGreaterThan(1, $slides->count());
    }

    public function test_the_slide_configuration_is_complete_and_unique(): void
    {
        $slides = collect(config('brand.login_slides'));

        foreach ($slides as $slide) {
            foreach (['file', 'icon', 'badge', 'title', 'text'] as $key) {
                $this->assertNotEmpty($slide[$key] ?? null, "Chaque diapositive doit avoir « $key ».");
            }
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slide['file']);
            $this->assertMatchesRegularExpression('/^(\d{1,3}%|left|center|right|top|bottom)( (\d{1,3}%|left|center|right|top|bottom))?$/', $slide['position'] ?? 'center', 'Cadrage CSS invalide.');
        }
        $this->assertSame($slides->count(), $slides->pluck('file')->unique()->count());
    }

    public function test_the_carousel_script_and_reduced_motion_support_are_present(): void
    {
        $this->get('/login')->assertSee('6000', false)->assertSee('prefers-reduced-motion', false);
    }

    public function test_the_other_guest_pages_keep_their_centered_layout(): void
    {
        $this->syncPermissions();
        $user = User::factory()->administrateur()->mustChangePassword()->create();

        $this->actingAs($user)->get('/password/change')->assertOk()->assertDontSee('login-page--right', false)->assertDontSee('bg-slideshow', false);
        $this->get('/page-inexistante')->assertNotFound()->assertDontSee('bg-slideshow', false);
    }

    public function test_the_profile_card_fills_the_whole_content_width(): void
    {
        $this->syncPermissions();

        $this->actingAs(User::factory()->administrateur()->create())->get('/profile')
            ->assertOk()
            ->assertDontSee('col-xl-8', false)
            ->assertDontSee('col-lg-8', false);
    }

    public function test_the_delivered_images_stay_light_enough_for_a_login_page(): void
    {
        config(['brand.login_images_directory' => 'assets/images/login']); // les vraies images du dépôt

        // Les images du magasin restent à fournir (CLAUDE.md §7) : une absence n'est pas une erreur (dégradé bleu),
        // mais toute image livrée respecte ces contraintes.
        foreach ((new LoginSlides)->all() as $slide) {
            if ($slide['image'] === null) {
                continue;
            }

            $path = public_path(LoginSlides::DIRECTORY.'/'.$slide['file'].'.jpg');
            $this->assertFileExists($path, "{$slide['file']} doit être livrée en JPEG (un PNG de 2 Mo alourdit la page).");
            $this->assertLessThanOrEqual(400 * 1024, filesize($path), "{$slide['file']}.jpg dépasse 400 Ko.");
            [$width, $height] = getimagesize($path);
            $this->assertGreaterThanOrEqual(1280, $width, "{$slide['file']} est trop petite ($width px de large).");
            $this->assertGreaterThanOrEqual(1.5, $width / $height, "{$slide['file']} doit être en format paysage (16:9 idéalement) : une image carrée est trop rognée en plein écran.");
        }
        $this->assertEmpty(glob(public_path(LoginSlides::DIRECTORY).'/*.png'), 'Aucun PNG brut dans le dossier du diaporama : convertir en JPEG.');
    }
}
