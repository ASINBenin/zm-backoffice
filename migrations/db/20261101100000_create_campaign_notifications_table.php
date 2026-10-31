<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Courriels planifiés déjà envoyés : la tâche quotidienne ne les envoie qu'une fois par campagne.
 */
class CreateCampaignNotificationsTable extends AbstractMigration
{
    public function change(): void
    {
        $required = ['null' => false];

        $this->table('campaign_notifications', ['signed' => false])
            ->addColumn('campaign_id', 'integer', $required + ['signed' => false])
            ->addColumn('type', 'string', $required + ['limit' => 30])
            ->addColumn('sent_at', 'datetime', $required)
            ->addIndex(['campaign_id', 'type'], ['unique' => true, 'name' => 'uniq_campaign_notification'])
            ->addForeignKey('campaign_id', 'campaigns', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->create();
    }
}
