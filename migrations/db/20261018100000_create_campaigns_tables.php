<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateCampaignsTables extends AbstractMigration
{
    public function change(): void
    {
        $required = ['null' => false];

        $this->table('campaigns', ['signed' => false])
            ->addColumn('label', 'string', $required + ['limit' => 255])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('model_id', 'integer', $required + ['signed' => false])
            ->addColumn('start_date', 'date', $required)
            ->addColumn('response_deadline', 'date', $required)
            ->addColumn('end_date', 'date', $required)
            ->addColumn('is_signature_required', 'boolean', $required + ['default' => false])
            ->addColumn('status', 'string', $required + ['limit' => 20, 'default' => 'draft'])
            ->addColumn('published_at', 'datetime', ['null' => true])
            ->addColumn('closed_at', 'datetime', ['null' => true])
            ->addColumn('creator', 'string', $required + ['limit' => 255])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updater', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            // un modèle ne sert qu'à une seule campagne : il est figé à la publication
            ->addIndex(['model_id'], ['unique' => true, 'name' => 'uniq_campaign_model'])
            ->addIndex(['status'])
            ->create();

        $this->table('campaign_participants', ['signed' => false])
            ->addColumn('campaign_id', 'integer', $required + ['signed' => false])
            ->addColumn('client_id', 'integer', $required + ['signed' => false])
            ->addColumn('status', 'string', $required + ['limit' => 20, 'default' => 'invited'])
            ->addColumn('creator', 'string', $required + ['limit' => 255])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updater', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['campaign_id', 'client_id'], ['unique' => true, 'name' => 'uniq_campaign_client'])
            ->addIndex(['updated_at'])
            ->addForeignKey('campaign_id', 'campaigns', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->create();
    }
}
