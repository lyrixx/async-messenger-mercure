<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CsvControllerTest extends WebTestCase
{
    public function testAsyncPageDisplaysForm(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form'));
    }

    public function testAsyncUploadDispatchesImportAndRedirects(): void
    {
        $client = self::createClient();
        $this->cleanTable($client->getContainer());

        $crawler = $client->request('GET', '/');
        $form = $crawler->selectButton('Send')->form();
        $form['form[csv]']->upload(__DIR__ . '/../Fixtures/first-names.csv');

        $client->submit($form);

        self::assertResponseRedirects();
        self::assertMatchesRegularExpression(
            '{^/\?importId=[0-9a-f-]+&sendNotification=0$}i',
            (string) $client->getResponse()->headers->get('Location'),
        );

        // The message is dispatched to the async transport: nothing is imported yet
        self::assertSame(0, $this->countRows($client->getContainer()));
    }

    public function testSyncUploadImportsCsv(): void
    {
        $client = self::createClient();
        $this->cleanTable($client->getContainer());

        $crawler = $client->request('GET', '/sync');
        $form = $crawler->selectButton('Send')->form();
        $form['form[csv]']->upload(__DIR__ . '/../Fixtures/first-names.csv');

        $client->submit($form);

        self::assertResponseRedirects();

        $rows = $this->connection($client->getContainer())
            ->executeQuery('SELECT first_name FROM first_name_stat ORDER BY first_name')
            ->fetchFirstColumn()
        ;

        self::assertSame(['ALICE', 'BOB', 'CLAIRE'], $rows);
    }

    public function testAsyncUploadKeepsSendNotificationParameter(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/?sendNotification=1');
        $form = $crawler->selectButton('Send')->form();
        $form['form[csv]']->upload(__DIR__ . '/../Fixtures/first-names.csv');

        $client->submit($form);

        self::assertResponseRedirects();
        self::assertMatchesRegularExpression(
            '{^/\?importId=[0-9a-f-]+&sendNotification=1$}i',
            (string) $client->getResponse()->headers->get('Location'),
        );
    }

    public function testUploadWithoutFileIsRejected(): void
    {
        $client = self::createClient();
        $this->cleanTable($client->getContainer());

        $crawler = $client->request('GET', '/sync');
        $form = $crawler->selectButton('Send')->form();
        unset($form['form[csv]']);

        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.invalid-feedback', 'Please upload a CSV file.');
        self::assertSame(0, $this->countRows($client->getContainer()));
    }

    private function connection(ContainerInterface $container): Connection
    {
        return $container->get(Connection::class);
    }

    private function cleanTable(ContainerInterface $container): void
    {
        $this->connection($container)->executeStatement('DELETE FROM first_name_stat');
    }

    private function countRows(ContainerInterface $container): int
    {
        return (int) $this->connection($container)->fetchOne('SELECT count(*) FROM first_name_stat');
    }
}
