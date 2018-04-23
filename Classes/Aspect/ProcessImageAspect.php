<?php
namespace GesagtGetan\KrakenOptimizer\Aspect;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Flow\Log\SystemLoggerInterface;
use TYPO3\Flow\ResourceManagement\PersistentResource;
use TYPO3\Flow\Mvc\Routing\UriBuilder;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Mvc\ActionRequest;
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
     * @Flow\Inject
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var Bootstrap
     * @Flow\Inject
     */
    protected $bootstrap;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var KrakenServiceInterface
     */
    protected $krakenService;

    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param JoinPointInterface $joinPoint
     * @Flow\Around ("method(TYPO3\Media\Domain\Service\ImageService->processImage())")
     * @throws \TYPO3\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @return array
     */
    public function retrieveAdjustedOriginalResource(JoinPointInterface $joinPoint): array
    {
        $originalResult = $joinPoint->getAdviceChain()->proceed($joinPoint);

        if ($this->settings['liveOptimization'] !== true) {
            return $originalResult;
        }

        /* @var $originalResource Resource */
        $originalResource = $originalResult['resource'];

        try {
            $this->requestOptimizedResource($originalResource);
        } catch(\Exception $exception) {
            $this->systemLogger->log('Was unable to request optimized resource for' . $originalResource->getFilename() . ' from Kraken.', LOG_CRIT);
            $this->systemLogger->log($exception->getMessage(), LOG_CRIT);

            return $originalResult;
        }

        $this->systemLogger->log('Requesting optimized version for ' . $originalResource->getFilename() . '(' . $originalResource->getSha1() . ')' .
            ' from Kraken. Actual replacement is done asynchronously via callback.', LOG_DEBUG);

        return $originalResult;
    }

    /**
     * Request optimized resource from Kraken and also define callback URL
     * for asynchronous image replacement.
     *
     * @param Resource $originalResource
     * @throws \TYPO3\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    private function requestOptimizedResource(Resource $originalResource)
    {
        $krakenOptions = [
            'callback_url' => $this->generateUri(
                'replaceLocalFile',
                'Kraken',
                'GesagtGetan.KrakenOptimizer',
                ['originalFilename' => $originalResource->getFilename()]
            )
        ];

        $this->krakenService->requestOptimizedResource($originalResource, $krakenOptions);
    }

    /**
     * @param string $actionName
     * @param string $controllerName
     * @param string $packageKey
     * @param array $controllerArguments
     * @return string
     * @throws \TYPO3\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    private function generateUri(string $actionName, string $controllerName, string $packageKey, array $controllerArguments = []): string
    {
        $urlBuilder = new UriBuilder();
        $requestHandler = $this->bootstrap->getActiveRequestHandler();
        $urlBuilder->setRequest(new ActionRequest($requestHandler->getHttpRequest()));
        return $urlBuilder->reset()->setCreateAbsoluteUri(true)->uriFor(
            $actionName,
            $controllerArguments,
            $controllerName,
            $packageKey
        );
    }
}
