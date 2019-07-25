<?php
namespace GesagtGetan\KrakenOptimizer\Service;

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
}
