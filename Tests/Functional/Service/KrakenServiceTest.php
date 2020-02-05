<?php

namespace GesagtGetan\KrakenOptimizer\Tests\Functional\Service;

use GesagtGetan\KrakenOptimizer\Service\KrakenService;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Model\Thumbnail;
use Neos\Media\Domain\Model\ThumbnailConfiguration;
use Neos\Media\Domain\Repository\ThumbnailRepository;
use Neos\Media\Domain\Service\ThumbnailService;

class KrakenServiceTest extends FunctionalTestCase
{
    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;
    /**
     * @var ResourceManager
     */
    protected $resourceManager;
    /**
     * @var KrakenService
     */
    protected $krakenService;

    /**
     * @var PersistentResource
     */
    protected $testResource;

    /**
     * @var ThumbnailRepository
     */
    protected $thumbnailRepository;

    /**
     * @var ThumbnailService
     */
    protected $thumbnailService;

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
        $thumbnails = $this->generateFakeThumbnails(
            $this->testResource,
            new ThumbnailConfiguration(50, 50, 50, 50),
            5
        );

        $response = json_decode(
            $this->krakenService->requestOptimizedResource($thumbnails[0]->getResource(), ['wait' => true]),
            true
        );

        // not much sensseful to check here, since kraken generates mostly random data in dev mode
        $this->assertTrue($response['success']);
        $this->assertIsString($response['file_name']);
        $this->assertIsString($response['kraked_url']);
        $this->assertIsInt($response['saved_bytes']);
    }

    /**
     * @param PersistentResource $resource
     * @param ThumbnailConfiguration $thumbnailConfiguration
     * @param int $amount
     * @return Thumbnail[]
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function generateFakeThumbnails(
        PersistentResource $resource,
        ThumbnailConfiguration $thumbnailConfiguration,
        int $amount = 1
    ): array {
        $thumbnails = [];

        $image = new Image($resource);
        while ($amount > 0) {
            $thumbnail = $this->thumbnailService->getThumbnail($image, $thumbnailConfiguration);
            $this->persistenceManager->persistAll();
            if ($this->thumbnailRepository->findOneByAssetAndThumbnailConfiguration(
                $image,
                $thumbnailConfiguration
            )
            instanceof Thumbnail
            ) {
                $this->thumbnailRepository->update($thumbnail);
            } else {
                $this->thumbnailRepository->add($thumbnail);
            }

            $thumbnails[] = $thumbnail;
            $amount--;
        }

        return $thumbnails;
    }
}
