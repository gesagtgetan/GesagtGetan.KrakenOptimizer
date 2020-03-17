<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Media\Domain\Model\Thumbnail;

interface KrakenServiceInterface
{
    /**
     * Request optimized resource from Kraken.
     *
     * @param Thumbnail $thumbnail
     */
    public function requestOptimizedResource(Thumbnail $thumbnail);

    /**
     * Request optimized resource from Kraken and also define callback URL
     * for asynchronous image replacement.
     *
     * @param Thumbnail $thumbnail
     * @return string the response as JSON containing the Id of the async call
     */
    public function requestOptimizedResourceAsynchronously(Thumbnail $thumbnail): string;

    /**
     * Creates the verification token for securing callback calls.
     *
     * @param string $filename
     * @return string
     */
    public function createToken(string $filename): string;

    /**
     * Verifies if the given verification token is correct.
     *
     * @param string $vericiationToken
     * @param string $filename
     * @return bool
     */
    public function verifyToken(string $vericiationToken, string $filename): bool;

    /**
     * Check if optimization should occur.
     *
     * @param PersistentResource $originalResource
     * @param PersistentResource $thumbnail
     * @return bool
     */
    public function shouldOptimize(PersistentResource $originalResource, PersistentResource $thumbnail): bool;
}
