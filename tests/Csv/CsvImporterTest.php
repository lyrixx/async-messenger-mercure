<?php

namespace App\Tests\Csv;

use App\Csv\CsvImporter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

final class CsvImporterTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM first_name_stat');
    }

    public function testImportCsvPersistsRows(): void
    {
        $content = <<<'CSV'
            gender;first_name;year_of_birth;count
            1;ALICE;2010;120
            2;BOB;XXXX;80
            CSV;

        $this->importer()->importCsv($content, 'import-1', false);

        $rows = $this->connection
            ->executeQuery('SELECT gender, first_name, year_of_birth, count FROM first_name_stat ORDER BY first_name')
            ->fetchAllAssociative()
        ;

        self::assertCount(2, $rows);
        self::assertSame([
            'gender' => 1,
            'first_name' => 'ALICE',
            'year_of_birth' => '2010',
            'count' => 120,
        ], $rows[0]);
        self::assertSame([
            'gender' => 2,
            'first_name' => 'BOB',
            'year_of_birth' => null,
            'count' => 80,
        ], $rows[1]);
    }

    public function testImportCsvReplacesPreviousData(): void
    {
        $this->importer()->importCsv("gender;first_name;year_of_birth;count\n1;OLD;2000;1", 'import-1', false);

        $this->importer()->importCsv("gender;first_name;year_of_birth;count\n2;NEW;2001;2", 'import-2', false);

        $count = (int) $this->connection->fetchOne('SELECT count(*) FROM first_name_stat');
        $firstName = $this->connection->fetchOne('SELECT first_name FROM first_name_stat');

        self::assertSame(1, $count);
        self::assertSame('NEW', $firstName);
    }

    public function testImportInvalidCsvRollsBack(): void
    {
        $this->importer()->importCsv("gender;first_name;year_of_birth;count\n1;KEEP;2000;1", 'import-1', false);

        $invalidContent = "gender;first_name;year_of_birth;count\n1;BROKEN;2000";

        try {
            $this->importer()->importCsv($invalidContent, 'import-2', false);
            self::fail('Expected RuntimeException to be thrown.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Invalid CSV format on line 1.', $e->getMessage());
        }

        $firstName = $this->connection->fetchOne('SELECT first_name FROM first_name_stat');

        self::assertSame('KEEP', $firstName);
    }

    public function testDebugProcessorIsInjected(): void
    {
        $importer = $this->importer();

        $processor = (new \ReflectionProperty($importer, 'processor'))->getValue($importer);

        self::assertInstanceOf(DebugProcessor::class, $processor);
    }

    public function testProgressGoesFromZeroToDone(): void
    {
        $published = [];
        $hub = self::createStub(HubInterface::class);
        $hub->method('publish')->willReturnCallback(static function (Update $update) use (&$published): string {
            $published[] = (string) $update->getData();

            return 'update-id';
        });

        $container = self::getContainer();
        $importer = new CsvImporter(
            $container->get(EntityManagerInterface::class),
            $hub,
            $container->get(Environment::class),
            $container->get('services_resetter'),
            $container->get('monolog.logger.request'),
        );

        $importer->importCsv("gender;first_name;year_of_birth;count\n1;ALICE;2010;120\n", 'import-1', true);

        self::assertStringContainsString('>0<small>%</small>', $published[0]);
        self::assertStringContainsString('(0 / 2)', $published[0]);
        self::assertStringContainsString('>100<small>%</small>', $published[array_key_last($published)]);
        self::assertStringContainsString('Done!', $published[array_key_last($published)]);
    }

    public function testStatusOnlyDisplaysOneHundredPercentOnceDone(): void
    {
        $html = self::getContainer()->get(Environment::class)
            ->load('csv/async.html.twig')
            ->renderBlock('status', ['percent' => 99.9, 'catch_phrase' => 'Just a few more...'])
        ;

        self::assertStringContainsString('>99<small>%</small>', $html);
        self::assertStringNotContainsString('is-done', $html);
    }

    private function importer(): CsvImporter
    {
        return self::getContainer()->get(CsvImporter::class);
    }
}
