<?php
namespace GesagtGetan\KrakenOptimizer\Service;

interface ResourceServiceInterface
{
    /**
     * Replaces the local file within the file system with the optimized image delivered by Kraken.
     *
     * @param array $krakenIoResult
     */
    public function replaceLocalFile(array $krakenIoResult);
}
