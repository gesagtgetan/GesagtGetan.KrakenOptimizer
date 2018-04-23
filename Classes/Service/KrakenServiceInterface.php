<?php
namespace GesagtGetan\KrakenOptimizer\Service;

use Neos\Flow\ResourceManagement\PersistentResource;

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
