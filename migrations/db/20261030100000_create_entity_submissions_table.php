<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateEntitySubmissionsTable extends AbstractMigration
{
    public function change(): void
    {
        $required = ['null' => false];

        $this->table('entity_submissions', ['signed' => false])
            ->addColumn('client_id', 'integer', $required + ['signed' => false])
            // vide : rapport soumis hors campagne
            ->addColumn('campaign_id', 'integer', ['null' => true, 'signed' => false])
            // analyse de l'entité (identifiant dans son FO) : chaque envoi en est une nouvelle version
            ->addColumn('source_anr_id', 'integer', $required + ['signed' => false])
            ->addColumn('analysis_label', 'string', $required + ['limit' => 255])
            ->addColumn('version', 'integer', $required + ['signed' => false])
            ->addColumn('stored_name', 'string', $required + ['limit' => 64])
            ->addColumn('file_size', 'integer', $required + ['signed' => false])
            ->addColumn('sha256', 'char', $required + ['limit' => 64])
            ->addColumn('is_signed', 'boolean', $required + ['default' => false])
            ->addColumn('creator', 'string', $required + ['limit' => 255])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addIndex(['client_id', 'campaign_id', 'source_anr_id', 'version'], ['unique' => true, 'name' => 'uniq_submission_version'])
            ->addIndex(['campaign_id'])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->addForeignKey('campaign_id', 'campaigns', 'id', ['delete' => 'RESTRICT', 'update' => 'RESTRICT'])
            ->create();
    }
}
