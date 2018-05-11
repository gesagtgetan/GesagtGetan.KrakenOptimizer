<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Client;
use Neos\Flow\ResourceManagement\PersistentResource;
use GuzzleHttp\Psr7;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\ActionRequest;

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
    protected $krakenOptions;

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
     * @param array $settings
     * @throws \Neos\Flow\Exception
     */
    public function injectSettings(array $settings)
    {
        if (!isset($settings['krakenOptions']['auth']['api_key']) || !isset($settings['krakenOptions']['auth']['api_secret'])) {
            throw new \Neos\Flow\Exception('Kraken requires ``api_key`` and ``api_secret`` to be definied in settings ', 1524401129);
        }

        $this->krakenOptions = $settings['krakenOptions'];
        $this->apiKey = $settings['krakenOptions']['auth']['api_key'];
        $this->optimizeOriginalResource = $settings['optimizeOriginalResource'];
    }

    /**
     * Request optimized resource from Kraken.
     *
     * @param PersistentResource $thumbnail
     * @param array $krakenOptions
     * @return string the response as JSON containing the path the optimized resource and meta data
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function requestOptimizedResource(PersistentResource $thumbnail, array $krakenOptions = []): string
    {
        $krakenOptions = array_merge($krakenOptions, $this->krakenOptions);

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
                        'contents'=> Psr7\stream_for($thumbnail->getStream())
                    ]
                ]
            ]
        )->getBody();
    }

    /**
     * Request optimized resource from Kraken and also define callback URL
     * for asynchronous image replacement.
     *
     * @param PersistentResource $thumbnail
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Neos\Flow\Exception
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    public function requestOptimizedResourceAsynchronously(PersistentResource $thumbnail)
    {
        $krakenOptions = [
            'callback_url' => $this->generateUri(
                'replaceLocalFile',
                'Kraken',
                'GesagtGetan.KrakenOptimizer',
                [
                    'originalFilename' => $thumbnail->getFilename(),
                    'verificationToken' => $this->createToken($thumbnail->getSha1())
                ]
            )
        ];

        $this->requestOptimizedResource($thumbnail, $krakenOptions);
    }

    /**
     * Check if flag is set to allow optimization of original resources that are too small
     * or just the right size, so no thumbnail was generated.
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
     * @throws \Neos\Flow\Exception
     * @return string
     */
    public function createToken(string $filename): string
    {
        $token = password_hash($this->apiKey . $filename, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);

        if ($token === false) {
            throw new \Neos\Flow\Exception('Could not generate verfication token', 1525948005);
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
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    protected function generateUri(string $actionName, string $controllerName, string $packageKey, array $controllerArguments = []): string
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
