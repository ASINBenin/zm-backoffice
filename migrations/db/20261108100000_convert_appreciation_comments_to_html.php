<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Les commentaires d'appréciation sont désormais du HTML mis en forme : ceux saisis en texte simple sont échappés,
 * retours à la ligne gardés.
 */
class ConvertAppreciationCommentsToHtml extends AbstractMigration
{
    public function up(): void
    {
        $connection = $this->getAdapter()->getConnection();
        $rows = $this->fetchAll('SELECT id, appreciation_comment FROM entity_submissions WHERE appreciation_comment IS NOT NULL');
        foreach ($rows as $row) {
            $html = '<div>' . nl2br(htmlspecialchars($row['appreciation_comment'], ENT_QUOTES, 'UTF-8'), false) . '</div>';
            $this->execute(sprintf(
                'UPDATE entity_submissions SET appreciation_comment = %s WHERE id = %d',
                $connection->quote($html),
                (int)$row['id']
            ));
        }
    }

    public function down(): void
    {
    }
}
