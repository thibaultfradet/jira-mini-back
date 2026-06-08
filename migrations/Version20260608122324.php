<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608122324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add issue_status_history table for burndown/burnup tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE issue_status_history (
            id INT AUTO_INCREMENT NOT NULL,
            issue_id INT NOT NULL,
            changed_by_id INT DEFAULT NULL,
            from_status VARCHAR(20) NOT NULL,
            to_status VARCHAR(20) NOT NULL,
            changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_ISSUE_STATUS_HISTORY_ISSUE (issue_id),
            INDEX IDX_ISSUE_STATUS_HISTORY_CHANGED_BY (changed_by_id),
            INDEX IDX_ISSUE_STATUS_HISTORY_TO_STATUS (to_status),
            INDEX IDX_ISSUE_STATUS_HISTORY_CHANGED_AT (changed_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE issue_status_history
            ADD CONSTRAINT FK_ISH_ISSUE FOREIGN KEY (issue_id) REFERENCES issue (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_ISH_USER FOREIGN KEY (changed_by_id) REFERENCES user (id) ON DELETE SET NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE issue_status_history DROP FOREIGN KEY FK_ISH_ISSUE');
        $this->addSql('ALTER TABLE issue_status_history DROP FOREIGN KEY FK_ISH_USER');
        $this->addSql('DROP TABLE issue_status_history');
    }
}
