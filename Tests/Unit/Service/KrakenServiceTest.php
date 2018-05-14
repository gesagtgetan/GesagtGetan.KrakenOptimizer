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
        $krakenService = new KrakenService();
        $this->inject($krakenService, 'optimizeOriginalResource', false);

        $originalResource = $this->createMock(Resource::class);
        $originalResource->expects($this->once())->method('getSha1')->willReturn("e6f36746ccba42c288acf906e636bb278eaeb7e8");
        $thumbnail = $this->createMock(Resource::class);
        $thumbnail->expects($this->once())->method('getSha1')->willReturn("e6f36746ccba42c288acf906e636bb278eaeb7e8");

        $result = $krakenService->shouldOptimize($originalResource, $thumbnail);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function shouldOptimizeWillReturnTrueIfOptimizeOriginalResourceIsTrueAndSha1HashesMatch()
    {
        $krakenService = new KrakenService();
        $this->inject($krakenService, 'optimizeOriginalResource', true);

        $originalResource = $this->createMock(Resource::class);
        $originalResource->expects($this->once())->method('getSha1')->willReturn("e6f36746ccba42c288acf906e636bb278eaeb7e8");
        $thumbnail = $this->createMock(Resource::class);
        $thumbnail->expects($this->once())->method('getSha1')->willReturn("e6f36746ccba42c288acf906e636bb278eaeb7e8");

        $result = $krakenService->shouldOptimize($originalResource, $thumbnail);

        $this->assertTrue($result);
    }
}