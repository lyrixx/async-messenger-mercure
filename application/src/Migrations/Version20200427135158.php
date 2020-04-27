<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200427135158 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE first_name_stat_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE first_name_stat (id INT NOT NULL, gender INT NOT NULL, first_name VARCHAR(255) NOT NULL, year_of_birth INT DEFAULT NULL, count INT NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE first_name_stat_id_seq CASCADE');
        $this->addSql('DROP TABLE first_name_stat');
    }
}
