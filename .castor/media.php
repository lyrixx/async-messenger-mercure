<?php

namespace media;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Page\PageInterface;
use Playwright\Playwright;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\check;
use function Castor\context;
use function Castor\fs;
use function Castor\io;
use function Castor\run;
use function Castor\variable;

// Large enough for a viewer to see the import progress.
const SLOW_CSV = 'data/nat2018-L.csv';
const FAST_CSV = 'data/nat2018-S.csv';

const IMPORT_TIMEOUT_IN_MILLISECONDS = 120_000;

#[AsTask(description: 'Installs Playwright browsers on the host (one-time setup)')]
function install(): void
{
    io()->title('Installing Playwright browsers');

    // vendor/bin/playwright-install needs symfony/console, which only exists inside the Castor process.
    $context = context()->withWorkingDirectory(variable('root_dir') . '/.castor/vendor/playwright-php/playwright/bin');

    run(['npm', 'install', '--omit=dev'], context: $context);
    run(['npx', 'playwright', 'install', '--with-deps', 'chromium'], context: $context);
}

#[AsTask(description: 'Generates the README screenshots, the demo video and the social preview image', aliases: ['media'])]
function generate(
    #[AsOption(description: 'CSV fixture used for the demo import')]
    string $csv = SLOW_CSV,
): void {
    io()->title('Generating the media');

    check(
        'Checking ffmpeg is available',
        'ffmpeg is required to convert the demo video to a GIF (see README).',
        static fn () => null !== (new ExecutableFinder())->find('ffmpeg'),
    );

    $mediaDir = variable('root_dir') . '/media';

    fs()->mkdir($mediaDir);

    generate_screenshots($mediaDir);
    generate_social_preview();
    generate_demo($mediaDir, $csv);

    io()->success('Media generated in media/ and assets/images/social-preview.png');
}

function generate_screenshots(string $mediaDir): void
{
    io()->section('Screenshots');

    $context = create_context(['deviceScaleFactor' => 2, 'viewport' => ['width' => 1280, 'height' => 800]]);
    $page = $context->newPage();
    $baseUrl = 'https://' . variable('root_domain');

    $page->goto($baseUrl . '/');
    freeze_pipeline($page);
    $page->screenshot($mediaDir . '/home.png', ['fullPage' => true]);

    $page->goto($baseUrl . '/sync');
    upload($page, SLOW_CSV);
    $page->waitForFunction("parseFloat(document.querySelector('[data-stopwatch-target=elapsed]').textContent) >= 4", null, ['timeout' => IMPORT_TIMEOUT_IN_MILLISECONDS]);
    $page->screenshot($mediaDir . '/sync.png', ['fullPage' => true]);
    $page->locator('.alert-success')->waitFor(['timeout' => IMPORT_TIMEOUT_IN_MILLISECONDS]);

    $page->goto($baseUrl . '/async');
    upload($page, FAST_CSV);
    $page->locator('.alert-success')->waitFor();
    $page->screenshot($mediaDir . '/async.png', ['fullPage' => true]);

    $page->goto($baseUrl . '/async/feedback');
    upload($page, SLOW_CSV);
    // The status only exists once the redirect that follows the upload is rendered.
    $page->waitForFunction("parseInt(document.querySelector('.mm-status-percent')?.textContent, 10) >= 60", null, ['timeout' => IMPORT_TIMEOUT_IN_MILLISECONDS]);
    $page->screenshot($mediaDir . '/async-feedback.png', ['fullPage' => true]);
    wait_for_end_of_import($page);

    $context->close();
}

function generate_social_preview(): void
{
    io()->section('Social preview image');

    // The size declared by the og:image:* tags of templates/base.html.twig.
    $context = create_context(['viewport' => ['width' => 1200, 'height' => 630]]);
    $page = $context->newPage();

    $page->goto('https://' . variable('root_domain') . '/');
    // A card is displayed small: only keep what stays readable at that size.
    $page->addStyleTag(['content' => <<<'CSS'
        .mm-band .btn,
        .mm-band .lead,
        .mm-footer,
        .mm-navbar .navbar-collapse,
        main {
            display: none !important;
        }

        .mm-band {
            display: flex;
            flex: 1;
            align-items: center;
        }

        .mm-band > .container-xl {
            width: 100%;
        }

        .mm-hero-title {
            font-size: 5.25rem;
        }

        .mm-kicker {
            font-size: 1.05rem;
        }

        .mm-navbar .navbar-brand {
            font-size: 1.5rem;
        }

        .mm-pipeline .job.static {
            display: inline;
        }
        CSS]);
    freeze_pipeline($page);
    $page->screenshot(variable('root_dir') . '/assets/images/social-preview.png');

    $context->close();
}

function generate_demo(string $mediaDir, string $csv): void
{
    io()->section('Recording the async + Mercure demo');

    $videoDir = sys_get_temp_dir() . '/castor-media-' . uniqid();
    // Tall enough to keep the progress and the log of events on screen.
    $size = ['width' => 1280, 'height' => 1000];
    $context = create_context([
        'deviceScaleFactor' => 2,
        'recordVideo' => ['dir' => $videoDir, 'size' => $size],
        'viewport' => $size,
    ]);
    $page = $context->newPage();
    $video = $page->video();

    $page->goto('https://' . variable('root_domain') . '/');
    sleep(4);
    // The same href is also used by the top navbar link and the hero, hence ->last().
    $page->locator('a[href$="/async/feedback"]')->last()->click();
    sleep(1);
    $page->setInputFiles('input[type=file]', [variable('root_dir') . '/' . $csv]);
    sleep(2);
    $page->click('button[type=submit]');
    wait_for_end_of_import($page);
    sleep(3);

    $context->close();
    $video?->saveAs($mediaDir . '/demo.webm');

    io()->section('Converting the video to an animated GIF for the README');
    // GitHub only renders an animated GIF inline for a relative-path README
    // image; a committed video file (even referenced by URL) never plays inline.
    run([
        'ffmpeg', '-y',
        // The first frames are the blank page, before the navigation.
        '-ss', '1.5',
        '-i', $mediaDir . '/demo.webm',
        // Sped up, small and without dithering: at full speed and in 960px, the GIF weighs 14 MB.
        '-vf', 'setpts=0.65*PTS,fps=8,scale=800:-1:flags=lanczos,split[s0][s1];[s0]palettegen=max_colors=128:stats_mode=diff[p];[s1][p]paletteuse=dither=none:diff_mode=rectangle',
        $mediaDir . '/demo.gif',
    ]);
}

/**
 * @param array<string, mixed> $options
 */
function create_context(array $options): BrowserContextInterface
{
    $context = Playwright::chromium([
        'headless' => true,
        'context' => ['ignoreHTTPSErrors' => true, ...$options],
    ]);

    // Hide the Symfony web debug toolbar (dev environment) so it never shows up in the media.
    $context->addInitScript(<<<'JS'
        document.addEventListener('DOMContentLoaded', () => {
            const style = document.createElement('style');
            style.textContent = '.sf-toolbar{display:none!important}';
            document.head.appendChild(style);
        });
        JS);

    return $context;
}

function upload(PageInterface $page, string $csv): void
{
    $page->setInputFiles('input[type=file]', [variable('root_dir') . '/' . $csv]);
    $page->click('button[type=submit]');
}

function wait_for_end_of_import(PageInterface $page): void
{
    // The log of the received events also contains this text.
    $page->locator('#status >> text=Done!')->waitFor(['timeout' => IMPORT_TIMEOUT_IN_MILLISECONDS]);
}

/**
 * Stops the animation of the hero while the progress events are flowing back to the browser.
 */
function freeze_pipeline(PageInterface $page): void
{
    $page->evaluate(<<<'JS'
        document.fonts.ready.then(() => {
            const pipeline = document.querySelector('.mm-pipeline');
            pipeline.pauseAnimations();
            pipeline.setCurrentTime(5);
        })
        JS);
}
