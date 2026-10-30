<?php declare(strict_types=1);

namespace Monarc\BackOffice\Validator\InputValidator\EntitySubmission;

use Laminas\Filter\Boolean;
use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\Validator\GreaterThan;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class PostEntitySubmissionInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                // absent : rapport soumis hors campagne
                'name' => 'campaignId',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => ToInt::class]],
            ],
            [
                'name' => 'anrId',
                'required' => true,
                'filters' => [['name' => ToInt::class]],
                'validators' => [['name' => GreaterThan::class, 'options' => ['min' => 0]]],
            ],
            [
                'name' => 'analysisLabel',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [['name' => StringLength::class, 'options' => ['min' => 1, 'max' => 255]]],
            ],
            [
                'name' => 'isSigned',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => Boolean::class]],
            ],
            [
                'name' => 'submittedBy',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [['name' => StringLength::class, 'options' => ['min' => 1, 'max' => 255]]],
            ],
        ];
    }
}
