<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use Neos\Flow\ResourceManagement\PersistentResource;

interface KrakenServiceInterface
{
    /**
     * Request optimized resource from Kraken.
     *
     * @param PersistentResource $originalResource
     */
    public function requestOptimizedResource(PersistentResource $originalResource);

    /**
     * Request optimized resource from Kraken and also define callback URL
     * for asynchronous image replacement.
     *
     * @param PersistentResource $resource
     * @return string the response as JSON containing the Id of the async call
     */
    public function requestOptimizedResourceAsynchronously(PersistentResource $resource): string;

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
     * @param string $filename
     * @return bool
     */
    public function verifyToken(string $vericiationToken, string $filename): bool;

    /**
     * Check if flag is set to allow optimization of original resources that are too small
     * or just the right size, so no thumbnail was generated.
     *
     * @param PersistentResource $originalResource
     * @param PersistentResource $thumbnail
     * @return bool
     */
    public function shouldOptimize(PersistentResource $originalResource, PersistentResource $thumbnail): bool;
}
