<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use TYPO3\Flow\Annotations as Flow;
use GuzzleHttp\Client;
use TYPO3\Flow\Resource\Resource;
use GuzzleHttp\Psr7;
use TYPO3\Flow\Mvc\Routing\UriBuilder;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Mvc\ActionRequest;

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
     * @throws \TYPO3\Flow\Exception
     */
    public function injectSettings(array $settings)
    {
        if (!isset($settings['krakenOptions']['auth']['api_key']) || !isset($settings['krakenOptions']['auth']['api_secret'])) {
            throw new \TYPO3\Flow\Exception('Kraken requires ``api_key`` and ``api_secret`` to be definied in settings ', 1524401129);
        }

        $this->krakenOptions = $settings['krakenOptions'];
        $this->apiKey = $settings['krakenOptions']['auth']['api_key'];
        $this->optimizeOriginalResource = $settings['optimizeOriginalResource'];
    }

    /**
     * Request optimized resource from Kraken.
     *
     * @param Resource $thumbnail
     * @param array $krakenOptions
     * @return string the response as JSON containing the path the optimized resource and meta data
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \TYPO3\Flow\Exception
     */
    public function requestOptimizedResource(Resource $thumbnail, array $krakenOptions = []): string
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
     * @param Resource $thumbnail
     * @throws \TYPO3\Flow\Mvc\Routing\Exception\MissingActionNameException
     */
    public function requestOptimizedResourceAsynchronously(Resource $thumbnail)
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
     * Creates the verification token for securing callback calls.
     *
     * @param string $filename
     * @throws \TYPO3\Flow\Exception
     * @return string
     */
    public function createToken(string $filename): string
    {
        $token = password_hash($this->apiKey . $filename, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);

        if ($token === false) {
            throw new \TYPO3\Flow\Exception('Could not generate verfication token', 1525948005);
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
     * @throws \TYPO3\Flow\Mvc\Routing\Exception\MissingActionNameException
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
