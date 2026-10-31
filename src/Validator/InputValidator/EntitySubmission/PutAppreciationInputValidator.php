<?php declare(strict_types=1);

namespace Monarc\BackOffice\Validator\InputValidator\EntitySubmission;

use Laminas\Filter\StringTrim;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\BackOffice\Entity\EntitySubmission;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class PutAppreciationInputValidator extends AbstractInputValidator
{
    private const COMMENT_MAX_LENGTH = 5000;

    protected function getRules(): array
    {
        return [
            [
                'name' => 'level',
                'required' => true,
                'validators' => [['name' => InArray::class, 'options' => [
                    'haystack' => EntitySubmission::APPRECIATION_LEVELS,
                    'strict' => InArray::COMPARE_STRICT,
                ]]],
            ],
            [
                'name' => 'comment',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [['name' => StringLength::class, 'options' => ['max' => self::COMMENT_MAX_LENGTH]]],
            ],
        ];
    }
}
