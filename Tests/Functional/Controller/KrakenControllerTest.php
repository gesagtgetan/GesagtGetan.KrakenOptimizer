<?php
namespace GesagtGetan\KrakenOptimizer\Tests\Functional\Controller;

use GesagtGetan\KrakenOptimizer\Service\KrakenService;
use GesagtGetan\KrakenOptimizer\Service\ResourceService;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Model\Thumbnail;
use Neos\Media\Domain\Model\ThumbnailConfiguration;
use Neos\Media\Domain\Repository\ThumbnailRepository;
use Neos\Media\Domain\Service\ThumbnailService;

class KrakenControllerTest extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    const REPLACE_ACTION_URI = '/optimizeImage';

    /**
     * @var KrakenService
     */
    protected $krakenService;

    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var ResourceService
     */
    protected $resourceService;

    /**
     * @var ThumbnailRepository
     */
    protected $thumbnailRepository;

    /**
     * @var ThumbnailService
     */
    protected $thumbnailService;

    public function setUp()
    {
        parent::setUp();

        $this->krakenService = $this->objectManager->get(KrakenService::class);
        $this->resourceService =  $this->objectManager->get(ResourceService::class);
        $this->resourceManager = $this->objectManager->get(ResourceManager::class);
        $this->thumbnailRepository = $this->objectManager->get(ThumbnailRepository::class);
        $this->thumbnailService = $this->objectManager->get(ThumbnailService::class);
    }

    /**
     * Controller should throw exception if verification token is missing in request.
     *
     * @test
     */
    public function missingVerificationTokenThrowsException()
    {
        $payload = [
            'success' => 'true',
            'resourceIdentifier' => 'e6f36746ccba42c288acf906e636bb278eaeb7e8',
            'originalFilename' => 'testimage.jpg'
        ];

        $response = $this->browser->request('http://localhost' . self::REPLACE_ACTION_URI, 'POST', $payload);
        $this->assertEquals('1524665601', $response->getHeader('X-Flow-ExceptionCode'));
    }

    /**
     * Controller should throw exception if verification token is invalid.
     *
     * @test
     */
    public function arbitraryVerificationTokenThrowsException()
    {
        $payload = [
            'success' => 'true',
            'resourceIdentifier' => 'e6f36746ccba42c288acf906e636bb278eaeb7e8',
            'originalFilename' => 'testimage.jpg',
            'verificationToken' => '$2c$03$vb.Vrkt3BddbmR.4yc6gJOfG5p.5BEeUcUIvMCvkQQe43de6S98ni'
        ];

        $response = $this->browser->request('http://localhost' . self::REPLACE_ACTION_URI, 'POST', $payload);
        $this->assertEquals('1524665601', $response->getHeader('X-Flow-ExceptionCode'));
    }

    /**
     * Replace local thumbnail with data retrieved from valid simulated kraken callback payload.
     * Also generate multiple thumbnails with the same resource to ensure all resources are replaced.
     *
     * @test
     */
    public function correctPayloadWillReplaceResource()
    {
        $testResource = $this->resourceManager->importResource(
            'resource://GesagtGetan.KrakenOptimizer/Private/Testing/TestImage_Not_Optimized.jpg'
        );

        $optimizedTestResource= $this->resourceManager->importResource(
            'resource://GesagtGetan.KrakenOptimizer/Private/Testing/TestImage_Optimized.jpg'
        );

        $thumbnails = $this->generateFakeThumbnails(
            $testResource,
            new ThumbnailConfiguration(50, 50, 50, 50),
            5
        );

        // fake optimized resource, no external request to kraken is happening
        $this->inject($this->resourceService, 'optimizedResource', $optimizedTestResource);

        $payload = [
            'success' => 'true',
            'resourceIdentifier' => $thumbnails[0]->getResource()->getSha1(),
            'originalFilename' => $testResource->getFilename(),
            'verificationToken' => $this->krakenService->createToken($testResource->getFilename()),
            'kraked_url' => 'https://dl.kraken.io/web/5ad0990c18e2b106322e41e175ad1165/TestImage_Not_Optimized.jpg',
            'saved_bytes' => '12990'
        ];

        $this->browser->request('http://localhost' . self::REPLACE_ACTION_URI, 'POST', $payload);

        foreach ($thumbnails as $thumbnail) {
            $this->assertEquals($optimizedTestResource->getSha1(), $thumbnail->getResource()->getSha1());
        }

        // restore initial state
        $this->inject($this->resourceService, 'optimizedResource', null);
    }

    /**
     * Original Thumbnail is still in place if error occurs during generation.
     *
     * @test
     */
    public function originalThumbnailIsPreservedUponFailure()
    {
        $testResource = $this->resourceManager->importResource(
            'resource://GesagtGetan.KrakenOptimizer/Private/Testing/TestImage_Not_Optimized.jpg'
        );

        $thumbnail = $this->generateFakeThumbnails(
            $testResource,
            new ThumbnailConfiguration(100, 100, 100, 100)
        )[0];

        $initialSha1 = $thumbnail->getResource()->getSha1();

        $payload = [
            'success' => 'true',
            'resourceIdentifier' => $thumbnail->getResource()->getSha1(),
            'originalFilename' => $testResource->getFilename(),
            'verificationToken' => $this->krakenService->createToken($testResource->getFilename()),
            'kraked_url' => 'https://dl.kraken.io/web/5ad0990c18e2b106322e41e175ad1165/ImageNotFound.jpg',
            'saved_bytes' => '12990'
        ];

        $this->browser->request('http://localhost' . self::REPLACE_ACTION_URI, 'POST', $payload);

        $this->assertEquals($initialSha1, $thumbnail->getResource()->getSha1());
    }

    /**
     * @param PersistentResource $resource
     * @param ThumbnailConfiguration $thumbnailConfiguration
     * @param int $amount
     * @return Thumbnail[]
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
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
            ) instanceof Thumbnail
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
