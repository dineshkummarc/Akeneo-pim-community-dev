<?php

declare(strict_types=1);

namespace Akeneo\Catalogs\Application\Mapping\ValueExtractor\Extractor\Number;

use Akeneo\Catalogs\Application\Mapping\ValueExtractor\Extractor\NumberValueExtractorInterface;

/**
 * @copyright 2023 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class NumberFromNumberAttributeValueExtractor implements NumberValueExtractorInterface
{
    public function extract(
        array $product,
        string $code,
        ?string $locale,
        ?string $scope,
        ?array $parameters,
    ): null | float | int {
        /** @var mixed $value */
        $value = $product['raw_values'][$code][$scope][$locale] ?? null;

        // @todo check if value is "floatable" or "intable" (i.e if it's a string that can be parsed)
        return null !== $value ? (float) $value : null;
    }

    public function getSupportedType(): string
    {
        return 'pim_catalog_number';
    }

    public function getSupportedTargetType(): string
    {
        return 'number';
    }

    public function getSupportedTargetFormat(): ?string
    {
        return null;
    }
}
