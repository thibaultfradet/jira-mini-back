<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203073630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sprint_issue (sprint_id INT NOT NULL, issue_id INT NOT NULL, INDEX IDX_204E8D728C24077B (sprint_id), INDEX IDX_204E8D725E7AA58C (issue_id), PRIMARY KEY (sprint_id, issue_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sprint_issue ADD CONSTRAINT FK_204E8D728C24077B FOREIGN KEY (sprint_id) REFERENCES sprint (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sprint_issue ADD CONSTRAINT FK_204E8D725E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sprint_issue DROP FOREIGN KEY FK_204E8D728C24077B');
        $this->addSql('ALTER TABLE sprint_issue DROP FOREIGN KEY FK_204E8D725E7AA58C');
        $this->addSql('DROP TABLE sprint_issue');
    }
}
