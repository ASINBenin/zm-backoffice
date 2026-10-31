<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Appréciation de l'ASIN sur une version de rapport : un niveau et un commentaire, sans effet bloquant.
 */
class AddAppreciationToEntitySubmissions extends AbstractMigration
{
    public function change(): void
    {
        $this->table('entity_submissions')
            ->addColumn('appreciation_level', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('appreciation_comment', 'text', ['null' => true])
            ->addColumn('appreciated_by', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('appreciated_at', 'datetime', ['null' => true])
            ->update();
    }
}
