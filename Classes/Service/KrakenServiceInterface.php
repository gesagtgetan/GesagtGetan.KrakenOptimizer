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
}
