<?php

namespace Akeneo\Platform\Bundle\ImportExportBundle\Factory;

use Akeneo\Platform\Bundle\NotificationBundle\Factory\AbstractNotificationFactory;
use Akeneo\Platform\Bundle\NotificationBundle\Factory\NotificationFactoryInterface;
use Akeneo\Tool\Component\Batch\Model\JobExecution;
use Doctrine\Common\Util\ClassUtils;

/**
 * Factory that creates a notification for mass edit from a job instance.
 *
 * @author    Marie Bochu <marie.bochu@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MassEditNotificationFactory extends AbstractNotificationFactory implements NotificationFactoryInterface
{
    /** @var string[] */
    protected $notificationTypes;

    /** @var string */
    protected $notificationClass;

    /**
     * @param string[] $notificationTypes
     * @param string   $notificationClass
     */
    public function __construct(array $notificationTypes, $notificationClass)
    {
        $this->notificationTypes = $notificationTypes;
        $this->notificationClass = $notificationClass;
    }

    public function create($jobExecution)
    {
        if (!$jobExecution instanceof JobExecution) {
            throw new \InvalidArgumentException(sprintf('Expects a Akeneo\Tool\Component\Batch\Model\JobExecution, "%s" provided', ClassUtils::getClass($jobExecution)));
        }

        $notification = new $this->notificationClass();
        $type = $jobExecution->getJobInstance()->getType();
        $status = $this->getJobStatus($jobExecution);

        $notification
            ->setType($status)
            ->setMessage(sprintf('pim_mass_edit.notification.%s.%s', $type, $status))
            ->setMessageParams(['%label%' => $jobExecution->getJobInstance()->getLabel()])
            ->setRoute('akeneo_job_process_tracker_details')
            ->setRouteParams(['id' => $jobExecution->getId()])
            ->setContext(['actionType' => $type]);

        return $notification;
    }

    public function supports($type)
    {
        return in_array($type, $this->notificationTypes);
    }
}
