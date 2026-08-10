<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Block : couleur du sous-titre (colorSecondary).
 */
final class Version20260707000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add colorSecondary to cms_layout_block';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_layout_block ADD colorSecondary VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_layout_block DROP colorSecondary');
    }
}
