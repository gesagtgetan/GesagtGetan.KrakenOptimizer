<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\SystemLoggerInterface;
use GuzzleHttp\Client;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Thumbnail;
use Neos\Media\Domain\Repository\ThumbnailRepository;
use Neos\Utility;
use Neos\Flow\Utility\Environment;
use Neos\Flow\Exception;

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
     * @Flow\Inject
     * @var Environment
     */
    protected $environment;

    /**
     * @Flow\Inject
     * @var ThumbnailRepository
     */
    protected $thumbnailRepository;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    const TEMP_FOLDER_NAME = 'OptimizedImagesTemp';

    /**
     * Replaces the local file within the file system with the optimized image delivered by Kraken.
     *
     * Copies the optimized file to a temporary folder first, to prevent possible racing conditions when the service
     * responds very fast.
     *
     * @param array $krakenIoResult
     * @throws \Neos\Flow\Exception
     */
    public function replaceLocalFile(array $krakenIoResult)
    {
        // originalFilename is unimportant, only used for better debug message
        $originalFilename = isset($krakenIoResult['originalFilename']) ? $krakenIoResult['originalFilename'] : '';

        if (!isset($krakenIoResult['file_name']) || !self::isSha1($krakenIoResult['file_name'])) {
            throw new Exception(
                'Invalid or no file name was returned for resource ' . '('
                . $originalFilename .')' . ' by Kraken API',
                1526371181
            );
        }

        if (!isset($krakenIoResult['kraked_url'])) {
            throw new Exception(
                'No URL to optimized resource present in response from Kraken API for ' .
                '(' . $originalFilename .')',
                1526371191
            );
        }

        // represents SHA1 hash
        $fileName = $krakenIoResult['file_name'];

        if (isset($krakenIoResult['saved_bytes']) && $krakenIoResult['saved_bytes'] === 0) {
            $this->systemLogger->log(
                'No optimization necessary for file ' . $originalFilename . ' (' . $fileName .')',
                LOG_DEBUG
            );

            return;
        }

        try {
            $pathAndFilename = self::getFilePathForSha1($fileName);
            $temporaryPath = $this->environment->getPathToTemporaryDirectory() . self::TEMP_FOLDER_NAME . '/';
            Utility\Files::createDirectoryRecursively($temporaryPath);
            $temporaryPathAndFilename = $temporaryPath . $fileName;

            $this->guzzleHttpClient->get($krakenIoResult['kraked_url'], ['sink' => $temporaryPathAndFilename]);

            rename($temporaryPathAndFilename, $pathAndFilename);

            $this->systemLogger->log(
                'Replaced ' . $originalFilename . ' (' . $fileName .')' .
                ' with optimized version from Kraken. Saved ' . $krakenIoResult['saved_bytes'] . ' bytes!',
                LOG_DEBUG
            );
        } catch (\Exception $e) {
            throw new Exception(
                'Could not retrieve and / or write image from Kraken for ' . $fileName . '. ' . $e->getMessage(),
                1526327172
            );
        }
    }

    /**
     * Replaced the resource of the thumbnail with a new resource object containing the optimized image delivered by Kraken.
     *
     * @param Thumbnail $thumbnail
     * @param array $krakenIoResult
     * @throws Exception
     */
    public function replaceThumbnailResource(Thumbnail $thumbnail, array $krakenIoResult)
    {
        // originalFilename is unimportant, only used for better debug message
        $originalFilename = isset($krakenIoResult['originalFilename']) ? $krakenIoResult['originalFilename'] : '';

//        if (!isset($krakenIoResult['file_name']) || !self::isSha1($krakenIoResult['file_name'])) {
//            throw new Exception('Invalid or no file name was returned for resource ' . '(' . $originalFilename .')' . ' by Kraken API', 1526371181);
//        }

        if (!isset($krakenIoResult['kraked_url'])) {
            throw new Exception('No URL to optimized resource present in response from Kraken API for ' . '(' . $originalFilename .')', 1526371191);
        }

        // represents SHA1 hash
        $fileName = $krakenIoResult['file_name'];

        if (isset($krakenIoResult['saved_bytes']) && $krakenIoResult['saved_bytes'] === 0) {
            $this->systemLogger->log('No optimization necessary for file ' . $originalFilename . ' (' . $fileName .')', LOG_DEBUG);

            return;
        }

        try {
            $temporaryPath = $this->environment->getPathToTemporaryDirectory() . self::TEMP_FOLDER_NAME . '/';
            Utility\Files::createDirectoryRecursively($temporaryPath);
            $temporaryPathAndFilename = $temporaryPath . $fileName;

            $this->guzzleHttpClient->get($krakenIoResult['kraked_url'], ['sink' => $temporaryPathAndFilename]);

            $resource = $this->resourceManager->importResource($temporaryPathAndFilename);
            $thumbnail->setResource($resource);

            $this->thumbnailRepository->update($thumbnail);

            $this->systemLogger->log('Replaced ' . $originalFilename . ' (' . $fileName .')' . ' with optimized version from Kraken. Saved ' .
                $krakenIoResult['saved_bytes'] . ' bytes!', LOG_DEBUG);
        } catch (\Exception $e) {
            throw new Exception('Could not retrieve and / or write image from Kraken for ' . $fileName . '. ' . $e->getMessage(), 1526327172);
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
        return FLOW_PATH_DATA . 'Persistent/Resources/' .
            $sha1[0] . '/' . $sha1[1] . '/' . $sha1[2] . '/' . $sha1[3] . '/' . $sha1;
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
