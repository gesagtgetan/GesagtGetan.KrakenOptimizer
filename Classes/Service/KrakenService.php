<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Client;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use GuzzleHttp\Psr7;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Exception;
use Neos\Media\Domain\Model\Thumbnail;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class KrakenService implements KrakenServiceInterface
{
    /**
     * @Flow\Inject
     * @var Client
     */
    protected $guzzleHttpClient;

    /**
     * @var array
     */
    protected $krakenOptionsFromSettings;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var Bootstrap
     * @Flow\Inject
     */
    protected $bootstrap;

    /**
     * @Flow\Inject
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * default cost for creating hashed verifcation token
     */
    const BCRYPT_COST = 4;

    /**
     * @var bool
     */
    protected $optimizeOriginalResource;

    /**
     * @Flow\Inject
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var ActionRequestFactory
     */
    protected $actionRequestFactory;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $systemLogger;

    /**
     * @var CacheManager
     * @Flow\Inject
     */
    protected $cacheManager;

    /**
     * @param array $settings
     */
    public function injectSettings(array $settings)
    {
        $this->krakenOptionsFromSettings = $settings['krakenOptions'];
        $this->apiKey = $settings['krakenOptions']['auth']['api_key'];
        $this->optimizeOriginalResource = $settings['optimizeOriginalResource'];
    }

    /**
     * Request optimized resource from Kraken and wait for optimized resource in response.
     *
     * @param Thumbnail $thumbnail
     * @param array $krakenOptionsOverride
     * @return string the response as JSON containing the path the optimized resource and meta data
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    public function requestOptimizedResource(Thumbnail $thumbnail, array $krakenOptionsOverride = []): string
    {
        $cacheEntryIdentifier = $this->persistenceManager->getIdentifierByObject($thumbnail);
        $resource = $thumbnail->getResource();
        $this->cacheManager->getCache('GesagtGetan_KrakenOptimizer_LiveOptimization')->set($cacheEntryIdentifier, $resource->getSha1());

        if (!isset($this->krakenOptionsFromSettings['auth']['api_key']) ||
            !isset($this->krakenOptionsFromSettings['auth']['api_secret'])) {
            throw new Exception(
                'Kraken requires ``api_key`` and ``api_secret`` to be definied in settings ',
                1524401129
            );
        }

        $krakenOptions = array_merge($this->krakenOptionsFromSettings, ['wait' => true], $krakenOptionsOverride);

        $this->systemLogger->debug('Request optimized resource from Kraken.io', ['krakenOptions' => $krakenOptions]);

        return $this->guzzleHttpClient->request(
            'POST',
            '',
            [
                'multipart' => [
                    [
                        'name' => 'data',
                        'contents' => json_encode($krakenOptions)
                    ],
                    [
                        'name' => 'file',
                        'contents'=> Psr7\stream_for($resource->getStream())
                    ]
                ]
            ]
        )->getBody();
    }

    /**
     * Request optimized resource from Kraken and also define callback URL
     * for asynchronous image replacement.
     *
     * @param Thumbnail $thumbnail
     * @return string the response as JSON containing the Id of the async call
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    public function requestOptimizedResourceAsynchronously(Thumbnail $thumbnail): string
    {
        $resource = $thumbnail->getResource();

        if (!isset($this->krakenOptionsFromSettings['auth']['api_key']) ||
            !isset($this->krakenOptionsFromSettings['auth']['api_secret'])) {
            throw new Exception(
                'Kraken requires ``api_key`` and ``api_secret`` to be defined in settings ',
                1524401129
            );
        }

        $krakenOptions = [
            'wait' => false,
            'callback_url' => $this->generateUri(
                'replaceThumbnailResource',
                'Kraken',
                'GesagtGetan.KrakenOptimizer',
                [
                    'originalFilename' => $resource->getFilename(),
                    'resourceIdentifier' => $this->persistenceManager->getIdentifierByObject($resource),
                    'verificationToken' => $this->createToken($resource->getFilename())
                ]
            )
        ];

        return $this->requestOptimizedResource($thumbnail, $krakenOptions);
    }

    /**
     * Check if flag is set to allow optimization for original resources.
     * If flag is not set, check if original sized image is used as thumbnail and do not optimize.
     *
     * @param PersistentResource $originalResource
     * @param PersistentResource $thumbnail
     * @return bool
     */
    public function shouldOptimize(PersistentResource $originalResource, PersistentResource $thumbnail): bool
    {
        if ($this->optimizeOriginalResource === false) {
            if ($originalResource->getSha1() === $thumbnail->getSha1()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Creates the verification token for securing callback calls.
     *
     * @param string $filename
     * @throws Exception
     * @return string
     */
    public function createToken(string $filename): string
    {
        $token = password_hash($this->apiKey . $filename, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);

        if ($token === false) {
            throw new Exception('Could not generate verfication token', 1525948005);
        }

        return password_hash($this->apiKey . $filename, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /**
     * Verifies if the given verification token is correct.
     *
     * @param string $verificationToken
     * @param string $filename
     * @return bool
     */
    public function verifyToken(string $verificationToken, string $filename): bool
    {
        return password_verify($this->apiKey . $filename, $verificationToken);
    }

    /**
     * @param string $actionName
     * @param string $controllerName
     * @param string $packageKey
     * @param array $controllerArguments
     * @return string
     * @throws \Neos\Flow\Http\Exception
     * @throws \Neos\Flow\Mvc\Exception\InvalidActionNameException
     * @throws \Neos\Flow\Mvc\Exception\InvalidArgumentNameException
     * @throws \Neos\Flow\Mvc\Exception\InvalidArgumentTypeException
     * @throws \Neos\Flow\Mvc\Exception\InvalidControllerNameException
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    protected function generateUri(
        string $actionName,
        string $controllerName,
        string $packageKey,
        array $controllerArguments = []
    ): string {
        $httpRequest = new ServerRequest('GET', '');
        $request = $this->actionRequestFactory->createActionRequest($httpRequest);
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($request);
        return $uriBuilder->reset()->setCreateAbsoluteUri(true)->uriFor(
            $actionName,
            $controllerArguments,
            $controllerName,
            $packageKey
        );
    }
}
