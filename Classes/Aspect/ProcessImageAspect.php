<?php
namespace GesagtGetan\KrakenOptimizer\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use GesagtGetan\KrakenOptimizer\Service\KrakenServiceInterface;
use Neos\Media\Domain\Model\Thumbnail;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Aspect
 */
class ProcessImageAspect
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
     * @param JoinPointInterface $joinPoint
     * @Flow\AfterReturning ("method(Neos\Media\Domain\Service\ThumbnailService->getThumbnail())")
     * @return array
     */
    public function retrieveAdjustedOriginalResource(JoinPointInterface $joinPoint): array
    {
        /** @var Thumbnail $thumbnail */
        $thumbnail = $joinPoint->getResult();

        if($this->persistenceManager->isNewObject($thumbnail)) {

            /* @var $thumbnailResource PersistentResource */
            $thumbnailResource = $thumbnail->getResource();

            /* @var $originalResource PersistentResource */
            $originalResource = $thumbnail->getOriginalAsset()->getResource();

            if ($this->liveOptimization === true && $this->krakenService->shouldOptimize($originalResource,$thumbnailResource)) {
                try {
                    $this->krakenService->requestOptimizedResourceAsynchronously($thumbnailResource);
                    $this->systemLogger->debug('Requesting optimized version for ' . $thumbnailResource->getFilename() . ' (' . $thumbnailResource->getSha1() . ')' .
                        ' from Kraken. Actual replacement is done asynchronously via callback.');
                } catch (\Exception $exception) {
                    $this->systemLogger->critical('Was unable to request optimized resource for ' . $thumbnailResource->getFilename() . ' from Kraken.');
                    $this->systemLogger->critical($exception->getMessage());
                }
            }
        }
    }
}
