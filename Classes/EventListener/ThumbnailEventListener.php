<?php
namespace GesagtGetan\KrakenOptimizer\EventListener;

use GesagtGetan\KrakenOptimizer\Slots\ProcessThumbnailSlot;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Persistence\Aspect\PersistenceMagicInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Media\Domain\Model\Thumbnail;

/**
 * @Flow\Scope("singleton")
 */
class ThumbnailEventListener
{
    /**
     * @var CacheManager
     * @Flow\Inject
     */
    protected $cacheManager;

    /**
     * @Flow\Inject
     * @var ProcessThumbnailSlot
     */
    protected $processThumbnailSlot;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @param LifecycleEventArgs $eventArgs
     * @return void
     */
    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $thumbnail = $eventArgs->getEntity();
        if ($thumbnail instanceof Thumbnail) {
            /** @var PersistentResource $resource */
            $resource = $thumbnail->getResource();
            /** @var StringFrontend $cache */
            $cache = $this->cacheManager->getCache('GesagtGetan_KrakenOptimizer_LiveOptimization');
            $cacheEntryIdentifier = $this->persistenceManager->getIdentifierByObject($thumbnail);
            $oldResourceSha1 = $cache->get($cacheEntryIdentifier);
            if ($resource !== null && !$oldResourceSha1) {
                $this->processThumbnailSlot->retrieveAdjustedThumbnailResource($thumbnail);
            } elseif ($oldResourceSha1 !== $resource->getSha1()) {
                $cache->remove($cacheEntryIdentifier);
            }
        }
    }
}
