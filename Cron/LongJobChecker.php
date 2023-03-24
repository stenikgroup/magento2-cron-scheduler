<?php
/**
 * @package KiwiCommerce\CronScheduler
 * @author  Stenik Magento Team <magedev@stenik.bg>
 */

namespace KiwiCommerce\CronScheduler\Cron;

/**
 * Class LongJobChecker
 * @package KiwiCommerce\CronScheduler\Cron
 * @see \KiwiCommerce\CronScheduler\Controller\Adminhtml\Cron\LongJobChecker
 */
class LongJobChecker
{
    /**
     * @var \KiwiCommerce\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory
     */
    public $scheduleCollectionFactory = null;

    /**
     * @var string
     */
    private $timePeriod = '- 3 hour';

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    public $dateTime;

    /**
     * LongJobChecker constructor.
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \KiwiCommerce\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory $scheduleCollectionFactory
     */
    public function __construct(
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \KiwiCommerce\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory $scheduleCollectionFactory
    ) {
        $this->dateTime = $dateTime;
        $this->scheduleCollectionFactory = $scheduleCollectionFactory;
    }

    /**
     * Execute action
     */
    public function execute()
    {
        $collection = $this->scheduleCollectionFactory->create();
        $time = date('Y-m-d H:i:s', $this->dateTime->gmtTimestamp($this->timePeriod));

        $jobs = $collection->addFieldToFilter('status', \Magento\Cron\Model\Schedule::STATUS_RUNNING)
            ->addFieldToFilter(
                'finished_at',
                ['null' => true]
            )
            ->addFieldToFilter(
                'executed_at',
                ['lt' => $time]
            )
            ->addFieldToSelect(['schedule_id','pid'])
            ->load();

        foreach ($jobs as $job) {
            $pid = $job->getPid();

            $finished_at = date('Y-m-d H:i:s', $this->dateTime->gmtTimestamp());
            if (function_exists('posix_getsid') && posix_getsid($pid) === false) {
                $job->setData('status', \Magento\Cron\Model\Schedule::STATUS_ERROR);
                $job->setData('messages', __('Execution stopped due to some error.'));
                $job->setData('finished_at', $finished_at);
            } else {
                posix_kill($pid, 9);
                $job->setData('status', \KiwiCommerce\CronScheduler\Controller\Adminhtml\Cron\LongJobChecker::STATUS_KILLED);
                $job->setData('messages', __('It is killed as running for longer period.'));
                $job->setData('finished_at', $finished_at);
            }
            $job->save();
        }
    }
}
