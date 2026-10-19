<?php declare(strict_types=1);

namespace Monarc\BackOffice\Validator\InputValidator\Campaign;

use Laminas\Filter\Boolean;
use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\InputFilter\ArrayInput;
use Laminas\Validator\Date;
use Laminas\Validator\GreaterThan;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class PostCampaignInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'label',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [['name' => StringLength::class, 'options' => ['min' => 1, 'max' => 255]]],
            ],
            [
                'name' => 'description',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
            ],
            [
                'name' => 'modelId',
                'required' => true,
                'filters' => [['name' => ToInt::class]],
                'validators' => [['name' => GreaterThan::class, 'options' => ['min' => 0]]],
            ],
            $this->getDateRule('startDate'),
            $this->getDateRule('responseDeadline'),
            $this->getDateRule('endDate'),
            [
                'name' => 'isSignatureRequired',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => Boolean::class]],
            ],
            [
                'name' => 'clientIds',
                'type' => ArrayInput::class,
                'required' => false,
                'filters' => [['name' => ToInt::class]],
                'validators' => [['name' => GreaterThan::class, 'options' => ['min' => 0]]],
            ],
        ];
    }

    private function getDateRule(string $name): array
    {
        return [
            'name' => $name,
            'required' => true,
            'filters' => [['name' => StringTrim::class]],
            'validators' => [['name' => Date::class, 'options' => ['format' => 'Y-m-d', 'strict' => true]]],
        ];
    }
}
