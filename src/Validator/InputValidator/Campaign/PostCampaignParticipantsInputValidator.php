<?php declare(strict_types=1);

namespace Monarc\BackOffice\Validator\InputValidator\Campaign;

use Laminas\Filter\ToInt;
use Laminas\InputFilter\ArrayInput;
use Laminas\Validator\GreaterThan;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

/**
 * Entités invitées à une campagne déjà publiée.
 */
class PostCampaignParticipantsInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'clientIds',
                'type' => ArrayInput::class,
                'required' => true,
                'filters' => [['name' => ToInt::class]],
                'validators' => [['name' => GreaterThan::class, 'options' => ['min' => 0]]],
            ],
        ];
    }
}
