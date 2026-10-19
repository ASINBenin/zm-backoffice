<?php declare(strict_types=1);

namespace Monarc\BackOffice\InputFormatter\Campaign;

use Monarc\BackOffice\InputFormatter\AbstractPaginatedInputFormatter;

class GetCampaignModelsInputFormatter extends AbstractPaginatedInputFormatter
{
    protected static array $allowedSearchFields = ['label1', 'label2', 'label3', 'label4'];

    protected static string $defaultOrderFields = '-createdAt';
}
