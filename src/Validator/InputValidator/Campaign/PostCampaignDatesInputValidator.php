<?php declare(strict_types=1);

namespace Monarc\BackOffice\Validator\InputValidator\Campaign;

use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

/**
 * Nouvelles dates d'une campagne publiée.
 */
class PostCampaignDatesInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            PostCampaignInputValidator::getDateRule('responseDeadline'),
            PostCampaignInputValidator::getDateRule('endDate'),
        ];
    }
}
