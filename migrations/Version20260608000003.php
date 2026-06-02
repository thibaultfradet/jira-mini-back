<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Issue: add urgency and deadline columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE issue ADD urgency VARCHAR(20) DEFAULT NULL, ADD deadline DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE issue DROP urgency, DROP deadline');
    }
}
