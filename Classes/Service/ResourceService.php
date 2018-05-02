<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Log\SystemLoggerInterface;
use GuzzleHttp\Client;

/**
 * @Flow\Scope("singleton")
 */
class ResourceService implements ResourceServiceInterface
{
    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $systemLogger;

    /**
     * @Flow\Inject
     * @var Client
     */
    protected $guzzleHttpClient;

    /**
     * Replaces the local file within the file system with the optimized image delivered by Kraken.
     *
     * @param array $krakenIoResult
     */
    public function replaceLocalFile(array $krakenIoResult)
    {
        if (!isset($krakenIoResult['file_name']) || !self::isSha1($krakenIoResult['file_name'])) {
            $this->systemLogger->log('Invalid file name was returned for resource by kraken.io API', LOG_CRIT);

            return;
        }

        // represents SHA1 hash
        $fileName = $krakenIoResult['file_name'];
        // originalFilename is unimportant, only used for better debug message
        $originalFilename = isset($krakenIoResult['originalFilename']) ? $krakenIoResult['originalFilename'] : '';

        if (isset($krakenIoResult['saved_bytes']) && $krakenIoResult['saved_bytes'] === 0) {
            $this->systemLogger->log('No optimization necessary for file ' . $originalFilename . '(' . $fileName .')', LOG_DEBUG);

            return;
        }

        try {
            $pathAndFilename = self::getFilePathForSha1($fileName);
            $resource = fopen($pathAndFilename, 'w');

            // download image from Kraken and override local thumbnail
            $this->guzzleHttpClient->get($krakenIoResult['kraked_url'], ['sink' => $resource]);

            $this->systemLogger->log('Replaced ' . $originalFilename . '(' . $fileName .')' . ' with optimized version from Kraken. Saved ' .
                $krakenIoResult['saved_bytes'] . ' bytes!', LOG_DEBUG);
        } catch (\Exception  $e) {
            $this->systemLogger->log('Could not retrieve and / or write image from Kraken for ' . $fileName, LOG_CRIT);
        }
    }

    /**
     * Return physical file path for Sha1 of resource.
     *
     * @param string $sha1
     * @return string
     */
    private static function getFilePathForSha1(string $sha1): string
    {
        return FLOW_PATH_DATA .'Persistent/Resources/' . $sha1[0] . '/' . $sha1[1] . '/' . $sha1[2] . '/' . $sha1[3] . '/' . $sha1;
    }

    /**
     * Check if a given string is a valid SHA1 hash.
     *
     * @param string $sha1
     * @return bool
     */
    private static function isSha1(string $sha1)
    {
        return (is_string($sha1) && preg_match('/[A-Fa-f0-9]{40}/', $sha1) === 1);
    }
}
