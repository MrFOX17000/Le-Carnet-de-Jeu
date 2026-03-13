<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute context_mode à la table activity (classement, duel, duel_equipe, groupe_vs_externe)';
    }

    public function up(Schema $schema): void
    {
        // SQLite supporte ADD COLUMN avec DEFAULT, sans recréer la table
        $this->addSql("ALTER TABLE activity ADD COLUMN context_mode VARCHAR(50) NOT NULL DEFAULT 'ranking'");
    }

    public function down(Schema $schema): void
    {
        // SQLite ne supporte pas DROP COLUMN : on recrée la table sans la colonne
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, name, group_id, created_at, created_by_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_AC74095AE54D947 FOREIGN KEY (group_id) REFERENCES game_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, name, group_id, created_at, created_by_id) SELECT id, name, group_id, created_at, created_by_id FROM __temp__activity');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095AE54D947 ON activity (group_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AB03A8386 ON activity (created_by_id)');
    }
}
