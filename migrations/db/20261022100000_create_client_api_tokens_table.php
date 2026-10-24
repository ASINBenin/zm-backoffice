<?php

use Phinx\Migration\AbstractMigration;

class CreateClientApiTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $required = ['null' => false];

        // Jeton d'une entite aupres de l'API du BO : seule son empreinte SHA-256 est conservee
        $this->table('client_api_tokens', ['signed' => false])
            ->addColumn('client_id', 'integer', $required + ['signed' => false])
            ->addColumn('token_hash', 'char', $required + ['limit' => 64])
            ->addColumn('created_at', 'datetime', $required)
            ->addColumn('last_used_at', 'datetime', ['null' => true])
            ->addIndex(['client_id'], ['unique' => true, 'name' => 'uniq_client_api_token_client'])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uniq_client_api_token_hash'])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->create();
    }
}
