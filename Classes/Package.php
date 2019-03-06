<?php
namespace GesagtGetan\KrakenOptimizer;

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Media\Domain\Model\Thumbnail;

/**
 * The KrakenOptimizer Package
 */
class Package extends BasePackage
{
    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(Thumbnail::class, 'thumbnailPersisted', \GesagtGetan\KrakenOptimizer\Slots\ProcessThumbnailSlot::class, 'retrieveAdjustedThumbnailResource');
    }
}
