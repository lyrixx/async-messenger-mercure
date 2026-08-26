<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ArticleControllerTest extends WebTestCase
{
    public function testIndexIsSuccessful(): void
    {
        $client = self::createClient();

        $client->request('GET', '/article');

        self::assertResponseIsSuccessful();
    }
}
