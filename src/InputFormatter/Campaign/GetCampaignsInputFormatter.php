<?php declare(strict_types=1);

namespace Monarc\BackOffice\InputFormatter\Campaign;

use Monarc\BackOffice\Entity\Campaign;
use Monarc\BackOffice\InputFormatter\AbstractPaginatedInputFormatter;

class GetCampaignsInputFormatter extends AbstractPaginatedInputFormatter
{
    protected static array $allowedSearchFields = ['label'];

    protected static array $allowedFilterFields = [
        'status' => [
            'type' => 'string',
            'inArray' => [Campaign::STATUS_DRAFT, Campaign::STATUS_PUBLISHED, Campaign::STATUS_CLOSED],
        ],
    ];

    protected static array $ignoredFilterFieldValues = ['status' => 'all'];

    protected static string $defaultOrderFields = '-createdAt';
}
