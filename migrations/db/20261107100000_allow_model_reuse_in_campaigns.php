<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Un même modèle spécifique peut servir à plusieurs campagnes : il est figé dès que l'une d'elles est publiée.
 */
class AllowModelReuseInCampaigns extends AbstractMigration
{
    public function up(): void
    {
        $this->table('campaigns')
            ->removeIndexByName('uniq_campaign_model')
            ->addIndex(['model_id'], ['name' => 'idx_campaign_model'])
            ->update();
    }

    public function down(): void
    {
        // Impossible si deux campagnes partagent déjà un modèle
        $this->table('campaigns')
            ->removeIndexByName('idx_campaign_model')
            ->addIndex(['model_id'], ['unique' => true, 'name' => 'uniq_campaign_model'])
            ->update();
    }
}
