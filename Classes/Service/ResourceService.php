<?php

namespace GesagtGetan\KrakenOptimizer\Service;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Client;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Fusion\Core\Cache\ContentCache;
use Neos\Media\Domain\Model\Thumbnail;
use Neos\Media\Domain\Repository\ThumbnailRepository;
use Neos\Media\Domain\Service\AssetService;
use Neos\Utility;
use Neos\Flow\Utility\Environment;
use Neos\Flow\Exception;
use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class ResourceService implements ResourceServiceInterface
{
    const TEMP_FOLDER_NAME = 'OptimizedImagesTemp';
    /**
     * @Flow\Inject
     * @var LoggerInterface
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
    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;
    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;
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
     * @var AssetService
     */
    protected $assetService;

    /**
     * Replaced the resource of the thumbnail with a new resource object
     * containing the optimized image delivered by Kraken.
     *
     * @param Thumbnail $thumbnail
     * @param array $krakenIoResult
     * @throws Exception
     */
    public function replaceThumbnailResource(Thumbnail $thumbnail, array $krakenIoResult)
    {
        $originalFilename = $krakenIoResult['originalFilename'];

        if (!isset($krakenIoResult['kraked_url'])) {
            throw new Exception(
                'No URL to optimized resource present in response from Kraken API for ' .
                '(' . $originalFilename . ')',
                1526371191
            );
        }

        if (isset($krakenIoResult['saved_bytes']) && $krakenIoResult['saved_bytes'] === 0) {
            $this->systemLogger->debug('No optimization necessary for file ' .
                $originalFilename . ' (' . $krakenIoResult['resourceIdentifier'] . ')');

            return;
        }

        try {
            $resource = $this->getOptimizedResource($krakenIoResult['kraked_url'], $originalFilename);

            if ($resource instanceof PersistentResource) {

                $thumbnail->setResource($resource);

                $this->thumbnailRepository->update($thumbnail);
                $this->assetService->emitAssetResourceReplaced($thumbnail->getOriginalAsset());

                $this->systemLogger->debug(
                    'Replaced ' .
                    $originalFilename .
                    ' (' . $krakenIoResult['resourceIdentifier'] . ')' .
                    ' with optimized version from Kraken. Saved ' .
                    $krakenIoResult['saved_bytes'] . ' bytes!'
                );
            }
        } catch (\Exception $e) {
            throw new Exception(
                'Could not retrieve and / or write image from Kraken for ' . $originalFilename .
                '. ' . $e->getMessage(),
                1526327172
            );
        }
    }

    /**
     * @param string $uri
     * @param string $originalFilename
     * @return PersistentResource
     * @throws Exception
     */
    public function getOptimizedResource(string $uri, string $originalFilename): PersistentResource
    {
        $optimizedResource = null;
        try {
            $temporaryPath = $this->environment->getPathToTemporaryDirectory() . self::TEMP_FOLDER_NAME . '/';
            Utility\Files::createDirectoryRecursively($temporaryPath);
            $temporaryPathAndFilename = $temporaryPath . $originalFilename;

            $response = $this->guzzleHttpClient->get($uri, ['sink' => $temporaryPathAndFilename]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('URI to optimized image could not be resolved', 1563577626);
            }

            $optimizedResource = $this->resourceManager->importResource($temporaryPathAndFilename);
        } catch (\Neos\Flow\Utility\Exception $e) {

        } catch (Utility\Exception\FilesException $e) {

        } catch (\Neos\Flow\ResourceManagement\Exception $e) {

        }

        return $optimizedResource;
    }

    /**
     * @param PersistentResource $resource
     * @return Thumbnail[]
     */
    public function getThumbnailsByResource(PersistentResource $resource): array
    {
        // Look for other thumbnails with the same resource
        $query = $this->entityManager->createQuery(
            'SELECT t FROM \Neos\Media\Domain\Model\Thumbnail t WHERE t.resource = :originalResource'
        );
        $query->setParameter(
            'originalResource',
            $resource
        );

        return $query->getResult();
    }

    protected function addRedirectAndDelete(PersistentResource $originalResource, PersistentResource $newResource)
    {

        // Add redirect from the old resource path to the new resource path
        // TODO doesn't work with foreign url's like Google Cloud Storage
        // $this->addRedirect($originalResource, $resource);

        // NOTE: Should be okay, let's add a flag to disable redirect generation
        // or keep the original resource if we can not create a redirect.
        // Maybe we can check where the resource is located and only create redirect for local resources?

        // Remove the old resource (happens automatically if not used anymore?)
        $this->resourceManager->deleteResource($originalResource);
    }

    /**
     * Adds a redirect from the old resource to the new resource
     *
     * @param PersistentResource $originalAssetResource
     * @param PersistentResource $newAssetResource
     */
    private function addRedirect(PersistentResource $originalAssetResource, PersistentResource $newAssetResource)
    {
        $redirectHandlerEnabled = $this->packageManager->isPackageAvailable('Neos.RedirectHandler');
        if ($redirectHandlerEnabled) {
            $originalAssetResourceUri = new Uri(
                $this->resourceManager->getPublicPersistentResourceUri($originalAssetResource)
            );
            $newAssetResourceUri = new Uri($this->resourceManager->getPublicPersistentResourceUri($newAssetResource));

            /** @var RedirectStorageInterface $redirectStorage */
            $redirectStorage = $this->objectManager->get(RedirectStorageInterface::class);
            $existingRedirect = $redirectStorage->getOneBySourceUriPathAndHost($originalAssetResourceUri);
            if ($existingRedirect === null) {
                $redirectStorage->addRedirect(
                    $originalAssetResourceUri->getPath(),
                    $newAssetResourceUri->getPath(),
                    301
                );
            }
        }
    }
}
