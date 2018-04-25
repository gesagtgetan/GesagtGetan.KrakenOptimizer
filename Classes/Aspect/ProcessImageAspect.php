<?php
namespace GesagtGetan\KrakenOptimizer\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Log\SystemLoggerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\ActionRequest;
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
     * @Flow\Around ("method(Neos\Media\Domain\Service\ImageService->processImage())")
     * @return array
     */
    public function retrieveAdjustedOriginalResource(JoinPointInterface $joinPoint): array
    {
        $originalResult = $joinPoint->getAdviceChain()->proceed($joinPoint);

        if ($this->settings['liveOptimization'] !== true) {
            return $originalResult;
        }

        /* @var $originalResource PersistentResource */
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
     * @param PersistentResource $originalResource
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    private function requestOptimizedResource(PersistentResource $originalResource)
    {
        $krakenOptions = [
            'callback_url' => $this->generateUri(
                'replaceLocalFile',
                'Kraken',
                'GesagtGetan.KrakenOptimizer',
                [
                    'originalFilename' => $originalResource->getFilename(),
                    'verificationToken' =>
                        password_hash($this->settings['krakenOptions']['auth']['api_key'], PASSWORD_BCRYPT, ['cost' => 4])
                ]
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
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
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
