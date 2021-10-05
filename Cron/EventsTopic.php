<?php

namespace Klaviyo\Reclaim\Cron;

use Klaviyo\Reclaim\Helper\Logger;
use Klaviyo\Reclaim\Model\SyncsFactory;
use Klaviyo\Reclaim\Model\Resourcemodel\Events\CollectionFactory;

class EventsTopic
{
    /**
     * Klaviyo Logger
     * @var Logger
     */
    protected $_klaviyoLogger;
    /**
     * Klaviyo Events Collection Factory
     * @var CollectionFactory
     */
    protected $_eventsCollectionFactory;

    /**
     * Klaviyo Sync Factory
     * @var SyncsFactory
     */
    protected $_klSyncFactory;

    /**
     * @param Logger $klaviyoLogger
     * @param CollectionFactory $eventsCollectionFactory
     * @param SyncsFactory $klSyncFactory
     */
    public function __construct(
        Logger $klaviyoLogger,
        CollectionFactory $eventsCollectionFactory,
        SyncsFactory $klSyncFactory
    )
    {
        $this->_klaviyoLogger = $klaviyoLogger;
        $this->_eventsCollectionFactory = $eventsCollectionFactory;
        $this->_klSyncFactory = $klSyncFactory;
    }

    /**
     *
     */
    public function moveRowsToSync()
    {
        // New Events to be moved to kl_sync table and update status of these to Moved, limit 500
        $eventsCollection = $this->_eventsCollectionFactory->create();
        $eventsData = $eventsCollection->getRowsForSync('NEW')
            ->addFieldToSelect(['id','event','payload','user_properties'])
            ->getData();

        if (empty( $eventsData )){
            return;
        }

        $idsMoved = [];

        // Capture all events that have been moved and add data to Sync table
        foreach ( $eventsData as $event ){
            //TODO: This can probably be done as one bulk update instead of individual inserts
            $sync = $this->_klSyncFactory->create();
            $sync->setData([
                'status' => 'NEW',
                'topic' => $event['event'],
                'user_properties' => $event['user_properties'],
                'payload' => $event['payload']
            ]);
            try {
                $sync->save();
                array_push($idsMoved, $event['id']);
            } catch (\Exception $e) {
                $this->_klaviyoLogger->log(sprintf("Unable to move row: %s", $e));
            }
        }

        // Update Status of rows in kl_events table to Moved
        $eventsCollection->updateRowStatus($idsMoved, 'MOVED');
    }

    public function deleteMovedRows()
    {
        // Delete rows that have been moved to sync table
        $eventsCollection = $this->_eventsCollectionFactory->create();
        $idsToDelete = $eventsCollection->getIdsToDelete('MOVED');

        $eventsCollection->deleteRows($idsToDelete);
    }


}