<?php
namespace GesagtGetan\KrakenOptimizer\Slots;

use Neos\Flow\Annotations as Flow;
use GesagtGetan\KrakenOptimizer\Service\KrakenServiceInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Media\Domain\Model\Thumbnail;
use Psr\Log\LoggerInterface;

class ProcessThumbnailSlot
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $systemLogger;

    /**
     * @var bool
     */
    protected $liveOptimization;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @Flow\Inject
     * @var KrakenServiceInterface
     */
    protected $krakenService;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @param array $settings
     */
    public function injectSettings(array $settings)
    {
        $this->liveOptimization = $settings['liveOptimization'];
        $this->apiKey = $settings['krakenOptions']['auth']['api_key'];
    }

    /**
     * @param Thumbnail $thumbnail
     * @return void
     */
    public function retrieveAdjustedThumbnailResource(Thumbnail $thumbnail)
    {
        $this->systemLogger->debug('retrieveAdjustedThumbnailResource was called');

        /* @var $thumbnailResource PersistentResource */
        $thumbnailResource = $thumbnail->getResource();

        $this->systemLogger->debug('Thumbnail to be optimized', ['resource' => $thumbnailResource]);

        /* @var $originalResource PersistentResource */
        $originalResource = $thumbnail->getOriginalAsset()->getResource();

        if ($thumbnailResource && $this->liveOptimization === true &&
            $this->krakenService->shouldOptimize($originalResource, $thumbnailResource)
        ) {
            try {
                $this->krakenService->requestOptimizedResourceAsynchronously($thumbnailResource);
                $this->systemLogger->debug('Requesting optimized version for ' . $thumbnailResource->getFilename() .
                    ' (' . $thumbnailResource->getSha1() . ')' .
                    ' from Kraken. Actual replacement is done asynchronously via callback.');
            } catch (\Exception $exception) {
                $this->systemLogger->critical('Was unable to request optimized resource for ' .
                    $thumbnailResource->getFilename() . ' from Kraken.');
                $this->systemLogger->critical($exception->getMessage());
            }
        }
    }
}
