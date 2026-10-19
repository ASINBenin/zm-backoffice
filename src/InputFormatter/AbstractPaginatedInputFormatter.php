<?php declare(strict_types=1);

namespace Monarc\BackOffice\InputFormatter;

use Monarc\Core\InputFormatter\AbstractInputFormatter;
use Monarc\Core\InputFormatter\FormattedInputParams;

/**
 * Pagination obligatoire : la limite demandée est ramenée entre 1 et MAX_LIMIT.
 */
abstract class AbstractPaginatedInputFormatter extends AbstractInputFormatter
{
    protected const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function format(array $inputParams): FormattedInputParams
    {
        $params = parent::format($inputParams);

        return $params->setLimit(min(max($params->getLimit(), 1), self::MAX_LIMIT));
    }
}
