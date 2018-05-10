<?php
namespace GesagtGetan\KrakenOptimizer\Aspect;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Flow\Log\SystemLoggerInterface;
use TYPO3\Flow\Resource\Resource;
use GesagtGetan\KrakenOptimizer\Service\KrakenServiceInterface;

/**
 * @Flow\Aspect
 */
class ProcessImageAspect
{
    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
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
     * @param array $settings
     */
    public function injectSettings(array $settings)
    {
        $this->liveOptimization = $settings['liveOptimization'];
        $this->apiKey = $settings['krakenOptions']['auth']['api_key'];
    }

    /**
     * @param JoinPointInterface $joinPoint
     * @Flow\Around ("method(TYPO3\Media\Domain\Service\ImageService->processImage())")
     * @return array
     */
    public function retrieveAdjustedOriginalResource(JoinPointInterface $joinPoint): array
    {
        $originalResult = $joinPoint->getAdviceChain()->proceed($joinPoint);

        /* @var $thumbnail Resource */
        $thumbnail = $originalResult['resource'];

        /* @var $originalResource Resource */
        $originalResource = $joinPoint->getMethodArgument('originalResource');

        if ($this->liveOptimization === true && $this->krakenService->shouldOptimize($originalResource, $thumbnail) === true) {
            try {
                $this->krakenService->requestOptimizedResourceAsynchronously($thumbnail);
                $this->systemLogger->log('Requesting optimized version for ' . $thumbnail->getFilename() . ' (' . $thumbnail->getSha1() . ')' .
                    ' from Kraken. Actual replacement is done asynchronously via callback.', LOG_DEBUG);
            } catch (\Exception $exception) {
                $this->systemLogger->log('Was unable to request optimized resource for ' . $thumbnail->getFilename() . ' from Kraken.',
                    LOG_CRIT);
                $this->systemLogger->log($exception->getMessage(), LOG_CRIT);
            }
        }

        return $originalResult;
    }
}
