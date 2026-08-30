<?php

namespace media;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Playwright\Playwright;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\check;
use function Castor\fs;
use function Castor\io;
use function Castor\run;
use function Castor\variable;

#[AsTask(description: 'Installs Playwright browsers on the host (one-time setup)')]
function install(): void
{
    io()->title('Installing Playwright browsers');

    run([variable('root_dir') . '/.castor/vendor/bin/playwright-install', '--with-deps']);
}

#[AsTask(description: 'Generates the README screenshot and demo video', aliases: ['media'])]
function generate(
    #[AsOption(description: 'CSV fixture used for the demo import')]
    string $csv = 'data/nat2018-M.csv',
): void {
    io()->title('Generating README media');

    check(
        'Checking ffmpeg is available',
        'ffmpeg is required to convert the demo video to a GIF (see README).',
        static fn () => null !== (new ExecutableFinder())->find('ffmpeg'),
    );

    $rootDir = variable('root_dir');
    $mediaDir = $rootDir . '/media';
    $videoDir = sys_get_temp_dir() . '/castor-media-' . uniqid();

    fs()->mkdir($mediaDir);

    $context = Playwright::chromium([
        'headless' => true,
        'context' => [
            'viewport' => ['width' => 1280, 'height' => 800],
            'deviceScaleFactor' => 2,
            'ignoreHTTPSErrors' => true,
            'recordVideo' => [
                'dir' => $videoDir,
                'size' => ['width' => 1280, 'height' => 800],
            ],
        ],
    ]);

    // Hide the Symfony web debug toolbar (dev environment) so it never shows up
    // in the screenshot or the video.
    $context->addInitScript(<<<'JS'
        document.addEventListener('DOMContentLoaded', () => {
            const style = document.createElement('style');
            style.textContent = '.sf-toolbar{display:none!important}';
            document.head.appendChild(style);
        });
        JS);

    $page = $context->newPage();
    $video = $page->video();

    io()->section('Home page screenshot');
    $page->goto('https://' . variable('root_domain') . '/');
    $page->screenshot($mediaDir . '/home.png', ['fullPage' => true]);

    io()->section('Recording the async + Mercure demo');
    // The same href is also used by the top navbar link, hence ->last().
    $page->locator('a[href$="/async/feedback"]')->last()->click();
    $page->setInputFiles('input[type=file]', [$rootDir . '/' . $csv]);
    $page->click('button[type=submit]');
    $page->locator('text=Done!')->waitFor(['timeout' => 120_000]);
    sleep(1);

    $context->close();
    $video?->saveAs($mediaDir . '/demo.webm');

    io()->section('Converting the video to an animated GIF for the README');
    // GitHub only renders an animated GIF inline for a relative-path README
    // image; a committed video file (even referenced by URL) never plays inline.
    run([
        'ffmpeg', '-y',
        '-i', $mediaDir . '/demo.webm',
        '-vf', 'fps=10,scale=960:-1:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse',
        $mediaDir . '/demo.gif',
    ]);

    io()->success('Media generated in media/home.png, media/demo.webm and media/demo.gif');
}
