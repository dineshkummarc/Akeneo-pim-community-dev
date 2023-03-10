<?php

declare(strict_types=1);

/**
 * @copyright 2023 Akeneo SAS (https://www.akeneo.com)
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Akeneo\Pim\Structure\Bundle\Infrastructure\Job\AttributeGroup;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeRepository;
use Akeneo\Pim\Structure\Component\Exception\UserFacingError;
use Akeneo\Tool\Component\Batch\Item\DataInvalidItem;
use Akeneo\Tool\Component\Batch\Item\TrackableTaskletInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Job\JobStopper;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Connector\Step\TaskletInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\BulkSaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;

final class MoveChildAttributesTasklet implements TaskletInterface, TrackableTaskletInterface
{
    private ?StepExecution $stepExecution = null;

    public function __construct(
        private readonly AttributeRepository $attributeRepository,
        private readonly ObjectUpdaterInterface $updater,
        private readonly BulkSaverInterface $saver,
        private readonly EntityManagerClearerInterface $cacheClearer,
        private readonly JobRepositoryInterface $jobRepository,
        private readonly JobStopper $jobStopper,
        private readonly int $batchSize = 100,
    ) {
    }

    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    public function execute()
    {
        if (null === $this->stepExecution) {
            throw new \InvalidArgumentException(sprintf('In order to execute "%s" you need to set a step execution.', DeleteAttributeGroupsTasklet::class));
        }

        $attributeGroupCodesToDelete = $this->stepExecution->getJobParameters()->get('filters')['codes'];
        $replacementAttributeGroupCode = $this->stepExecution->getJobParameters()->get('replacement_attribute_group_code');

        $attributes = $this->attributeRepository->getAttributesByGroups($attributeGroupCodesToDelete);
        $this->stepExecution->setTotalItems(count($attributes));
        $this->stepExecution->addSummaryInfo('attributes_to_move', 0);

        foreach (array_chunk($attributes, $this->batchSize) as $attributeChunk) {
            $this->updateAttributes($attributeChunk, $replacementAttributeGroupCode);
            if ($this->jobStopper->isStopping($this->stepExecution)) {
                $this->jobStopper->stop($this->stepExecution);

                return;
            }

            $this->cacheClearer->clear();
            $this->jobRepository->updateStepExecution($this->stepExecution);
        }
    }

    public function updateAttributes(array $attributes, string $replacementAttributeGroupCode)
    {
        $this->stepExecution->incrementSummaryInfo('attributes_to_move', count($attributes));
        foreach ($attributes as $attribute) {
            try {
                $this->updater->update($attribute, ['group' => $replacementAttributeGroupCode]);
            } catch (UserFacingError $e) {
                $this->stepExecution->addWarning($e->translationKey(), $e->translationParameters(), new DataInvalidItem([
                    'code' => $attribute->getCode(),
                ]));
            }
        }
        $this->saver->saveAll($attributes);
    }

    public function isTrackable(): bool
    {
        return true;
    }
}
