<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118134021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE "user_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE "user" (id INT NOT NULL, identifier VARCHAR(255) NOT NULL, role VARCHAR(50) NOT NULL, params JSON DEFAULT NULL, google_id VARCHAR(100) DEFAULT NULL, git_hub_id INT DEFAULT NULL, git_lab_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649772E836A ON "user" (identifier)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE "user_id_seq" CASCADE');
        $this->addSql('DROP TABLE "user"');
    }
}
