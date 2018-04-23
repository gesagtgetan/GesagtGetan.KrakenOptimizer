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
}
