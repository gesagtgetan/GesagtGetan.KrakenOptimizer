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

}
