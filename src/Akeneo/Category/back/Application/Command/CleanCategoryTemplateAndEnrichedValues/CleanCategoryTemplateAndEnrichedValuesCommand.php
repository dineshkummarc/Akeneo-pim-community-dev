<?php

declare(strict_types=1);

namespace Akeneo\Category\Application\Command\CleanCategoryTemplateAndEnrichedValues;

/**
 * @copyright 2023 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CleanCategoryTemplateAndEnrichedValuesCommand
{
    public function __construct(
        public readonly string $templateUuid,
    ) {
    }
}
