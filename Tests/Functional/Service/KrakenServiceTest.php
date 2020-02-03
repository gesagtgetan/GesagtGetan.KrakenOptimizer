<?php
namespace GesagtGetan\KrakenOptimizer\Tests\Functional\Service;

use GesagtGetan\KrakenOptimizer\Service\KrakenService;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Flow\ResourceManagement\ResourceManager;

class KrakenServiceTest extends FunctionalTestCase
{
    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

    /**
     * @var KrakenService
     */
    protected $krakenService;

    /**
     * @var PersistentResource
     */
    protected $testResource;

    public function setUp(): void
    {
        parent::setUp();

        $this->krakenService = $this->objectManager->get(KrakenService::class);
        $this->resourceManager = $this->objectManager->get(ResourceManager::class);
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" height="30" width="200">' .
            '<text x="0" y="15" fill="red">Test SVG</text></svg>';
        $this->testResource = $this->resourceManager->importResourceFromContent($svgContent, 'svg1526213193.svg');
    }

    /**
     * Successful resource replacement is covered within the KrakenController test case.
     * This case just checks if a valid kraken request / response is generated.
     *
     * @test
     */
    public function apiRequestWithArbitraryResourceReturnsSuccessfulResponse()
    {
        $response = json_decode(
            $this->krakenService->requestOptimizedResource($this->testResource, ['wait' => true]),
            true
        );

        // not much sensseful to check here, since kraken generates mostly random data in dev mode
        $this->assertTrue($response['success']);
        $this->assertIsString($response['file_name']);
        $this->assertIsString($response['kraked_url']);
        $this->assertIsInt($response['saved_bytes']);
    }
}
