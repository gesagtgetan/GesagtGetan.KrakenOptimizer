<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use TYPO3\Flow\Resource\Resource;

interface KrakenServiceInterface
{
    /**
     * Request optimized resource from Kraken.
     *
     * @param Resource $originalResource
     * @param array $krakenOptions
     */
    public function requestOptimizedResource(Resource $originalResource, array $krakenOptions = []);

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
     * @param Resource $originalResource
     * @param Resource $thumbnail
     * @return bool
     */
    public function shouldOptimize(Resource $originalResource, Resource $thumnail): bool;
}
