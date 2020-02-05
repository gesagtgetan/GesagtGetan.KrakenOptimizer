<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Media\Domain\Model\Thumbnail;

interface ResourceServiceInterface
{
    /**
     * Replaced the resource of the thumbnail with a new resource
     * object containing the optimized image delivered by Kraken.
     *
     * @param Thumbnail $thumbnail
     * @param array $krakenIoResult
     */
    public function replaceThumbnailResource(Thumbnail $thumbnail, array $krakenIoResult);

    /**
     * @param PersistentResource $resource
     * @return Thumbnail[]
     */
    public function getThumbnailsByResource(PersistentResource $resource): array;

    /**
     * @param string $uri
     * @param string $originalFilename
     * @return PersistentResource
     */
    public function getOptimizedResource(string $uri, string $originalFilename): PersistentResource;
}
