<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sprint: add team_id FK, status, goal columns; drop is_active';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sprint ADD goal LONGTEXT DEFAULT NULL, ADD status VARCHAR(20) NOT NULL DEFAULT \'planned\', ADD team_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sprint DROP is_active');
        $this->addSql('ALTER TABLE sprint ADD CONSTRAINT FK_C4870BBA296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('CREATE INDEX IDX_C4870BBA296CD8AE ON sprint (team_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_C4870BBA296CD8AE ON sprint');
        $this->addSql('ALTER TABLE sprint DROP FOREIGN KEY FK_C4870BBA296CD8AE');
        $this->addSql('ALTER TABLE sprint ADD is_active TINYINT(1) NOT NULL DEFAULT 0, DROP goal, DROP status, DROP team_id');
    }
}
