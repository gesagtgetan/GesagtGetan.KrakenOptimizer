<?php
namespace GesagtGetan\KrakenOptimizer;

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Media\Domain\Service\ThumbnailService;
use GesagtGetan\KrakenOptimizer\Slots\ProcessThumbnailSlot;

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
        $dispatcher->connect(ThumbnailService::class, 'thumbnailPersisted', ProcessThumbnailSlot::class, 'retrieveAdjustedThumbnailResource');
    }
}
