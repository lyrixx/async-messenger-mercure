<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomePageLinksToAllUploadModes(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $links = $crawler->filter('a.btn-primary')->each(static fn ($node) => $node->attr('href'));
        self::assertCount(3, $links);

        foreach ($links as $link) {
            self::assertMatchesRegularExpression('{/(async(/feedback)?|sync)$}', (string) $link);
        }

        self::assertCount(1, $crawler->filter('a.btn-primary[href$="/sync"]'));
        self::assertCount(1, $crawler->filter('a.btn-primary[href$="/async"]'));
        self::assertCount(1, $crawler->filter('a.btn-primary[href$="/async/feedback"]'));
    }

    public function testHomePageLinksToGithubAndSlides(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="https://github.com/lyrixx/async-messenger-mercure"]');
        self::assertSelectorExists('a[href="https://s.lyrixx.info/async"]');
    }
}
