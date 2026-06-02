<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comment: make issue_id and author_id NOT NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM comment WHERE issue_id IS NULL OR author_id IS NULL');
        $this->addSql('ALTER TABLE comment CHANGE issue_id issue_id INT NOT NULL, CHANGE author_id author_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment CHANGE issue_id issue_id INT DEFAULT NULL, CHANGE author_id author_id INT DEFAULT NULL');
    }
}
