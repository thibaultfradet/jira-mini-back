<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sub_task table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sub_task (id INT AUTO_INCREMENT NOT NULL, issue_id INT NOT NULL, title VARCHAR(255) NOT NULL, is_done TINYINT(1) NOT NULL DEFAULT 0, position INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C4A78ABD5E7AA58C (issue_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_C4A78ABD5E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sub_task DROP FOREIGN KEY FK_C4A78ABD5E7AA58C');
        $this->addSql('DROP TABLE sub_task');
    }
}
