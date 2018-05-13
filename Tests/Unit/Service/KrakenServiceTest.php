<?php
namespace GesagtGetan\KrakenOptimizer\Tests\Unit\Service;

use GesagtGetan\KrakenOptimizer\Service\KrakenService;
use TYPO3\Flow\Resource\Resource;
use TYPO3\Flow\Tests\UnitTestCase;

class KrakenServiceTest extends UnitTestCase
{
    /**
     * @test
     */
    public function shouldOptimizeWillReturnFalseIfOptimizeOriginalResourceIsFalseAndSha1HashesMatch()
    {
        $originalResource = new Resource();
        $thumbnail = new Resource();
        $originalResource->setSha1('e6f36746ccba42c288acf906e636bb278eaeb7e8');
        $thumbnail->setSha1('e6f36746ccba42c288acf906e636bb278eaeb7e8');

        $krakenService = new KrakenService();
        $this->inject($krakenService, 'optimizeOriginalResource', false);
        $result = $krakenService->shouldOptimize($originalResource, $thumbnail);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function shouldOptimizeWillReturnTrueIfOptimizeOriginalResourceIsTrueAndSha1HashesMatch()
    {
        $originalResource = new Resource();
        $thumbnail = new Resource();
        $originalResource->setSha1('e6f36746ccba42c288acf906e636bb278eaeb7e8');
        $thumbnail->setSha1('e6f36746ccba42c288acf906e636bb278eaeb7e8');

        $krakenService = new KrakenService();
        $this->inject($krakenService, 'optimizeOriginalResource', true);
        $result = $krakenService->shouldOptimize($originalResource, $thumbnail);

        $this->assertTrue($result);
    }
}