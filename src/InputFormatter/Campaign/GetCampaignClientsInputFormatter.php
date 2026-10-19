<?php declare(strict_types=1);

namespace Monarc\BackOffice\InputFormatter\Campaign;

use Monarc\BackOffice\InputFormatter\AbstractPaginatedInputFormatter;

class GetCampaignClientsInputFormatter extends AbstractPaginatedInputFormatter
{
    protected static array $allowedSearchFields = ['name', 'proxyAlias'];

    protected static string $defaultOrderFields = 'name';
}
