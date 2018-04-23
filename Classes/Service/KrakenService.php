<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use TYPO3\Flow\Annotations as Flow;
use GuzzleHttp\Client;
use TYPO3\Flow\Resource\Resource;
use GuzzleHttp\Psr7;

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
    protected $settings;

    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Request optimized resource from Kraken.
     *
     * @param Resource $originalResource
     * @param array $krakenOptions
     * @return string the response as JSON containing the path the optimized resource and meta data
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \TYPO3\Flow\Exception
     */
    public function requestOptimizedResource(Resource $originalResource, array $krakenOptions = []): string
    {
        if (!isset($this->settings['krakenOptions']['auth']['api_key']) || !isset($this->settings['krakenOptions']['auth']['api_secret'])) {
            throw new \TYPO3\Flow\Exception('Kraken requires ``api_key`` and ``api_secret`` to be definied in settings ', 1524401129);
        }

        $krakenOptions = array_merge($krakenOptions, $this->settings['krakenOptions']);

        return $this->guzzleHttpClient->request('POST', '',
            [
                'multipart' => [
                    [
                        'name' => 'data',
                        'contents' => json_encode($krakenOptions)
                    ],
                    [
                        'name' => 'file',
                        'contents'=> Psr7\stream_for($originalResource->getStream())
                    ]
                ]
            ]
        )->getBody();
    }
}
