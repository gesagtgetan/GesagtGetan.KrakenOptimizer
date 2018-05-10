<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use Neos\Flow\ResourceManagement\PersistentResource;

interface KrakenServiceInterface
{
    /**
     * Request optimized resource from Kraken.
     *
     * @param PersistentResource $originalResource
     * @param array $krakenOptions
     */
    public function requestOptimizedResource(PersistentResource $originalResource, array $krakenOptions = []);

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
    public function shouldOptimize(PersistentResource $originalResource, PersistentResource $thumnail): bool;
}
