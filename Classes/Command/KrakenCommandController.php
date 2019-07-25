<?php
namespace GesagtGetan\KrakenOptimizer\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Media\Domain\Model\Thumbnail;
use Neos\Media\Domain\Repository\ThumbnailRepository;
use Neos\Media\Domain\Service\ThumbnailService;
use GesagtGetan\KrakenOptimizer\Service\ResourceServiceInterface;
use GesagtGetan\KrakenOptimizer\Service\KrakenServiceInterface;
use Neos\Flow\Exception;

/**
 * @Flow\Scope("singleton")
 */
class KrakenCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var ThumbnailRepository
     */
    protected $thumbnailRepository;

    /**
     * @Flow\Inject
     * @var ThumbnailService
     */
    protected $thumbnailService;

    /**
     * @Flow\Inject
     * @var ResourceServiceInterface
     */
    protected $resourceService;

    /**
     * @Flow\Inject
     * @var KrakenServiceInterface
     */
    protected $krakenService;

    /**
     * @var bool
     */
    protected $liveOptimization;

    /**
     * @param array $settings
     */
    public function injectSettings(array $settings)
    {
        $this->liveOptimization = $settings['liveOptimization'];
    }

    /**
     * Optimize images with kraken.io API (recommended for first time optimization of existing thumbnails).
     *
     * Replaces all rendered thumbnails with an optimized version from Kraken.
     * It's recommended to run this command, just before activating "liveOptimization" in the settings.
     *
     * !!Warning!!
     * Executing this command multiple times will send potentially already optimized images to the Kraken API and thus
     * will still count towards your API quota and can lead to "over optimized" images when running lossy optimizations
     * multiple times.
     *
     * New resources are optimized automatically (if "liveOptimization" is activated), so there is no need to run
     * this command more than once, unless many resources need optimization (e.g. the first time run).
     *
     * An alternative is to run ``media:clearthumbnails`` followed by
     * ``flow:cache:flushone --identifier="Neos_Fusion_Content"`` . This way all thumbnails
     * are cleared (unless they are in use), and optimization is done on the fly, when Neos
     * generates new thumbnails.
     * The server might end up with handling a lot of requests, since for every optimized resource an
     * asynchronous callback method is invoked to replace the physical image.
     *
     * @param int $offset offset to start optimization (useful when optimization previously stopped
     *                    at a certain thumbnail)
     * @throws Exception
     */
    public function optimizeCommand(int $offset = 0)
    {
        $thumbnailCount = $this->thumbnailRepository->countAll();
        $iterator = $this->thumbnailRepository->findAllIterator();
        $this->output->progressStart($thumbnailCount);
        $savedBytes = 0;
        $iteration = 0;

        if ($thumbnailCount === 0) {
            $this->outputLine('No thumbnails present for optimization.');
            exit();
        }

        $answer = $this->output->askConfirmation(
            'Send ' . ($thumbnailCount - $offset) . ' thumbnails to Kraken for optimization?'
        );

        if ($answer === false) {
            exit();
        }

        // Warn user if `liveOptimization` is already active
        if ($this->liveOptimization === true) {
            $answer = $this->output->askConfirmation(
                '`liveOptimization` is already activated. Some resources might be optimized twice! Proceed?'
            );

            if ($answer === false) {
                exit();
            }
        }

        /** @var Thumbnail $thumbnail */
        foreach ($this->thumbnailRepository->iterate($iterator) as $thumbnail) {
            $originalAssetResource = $thumbnail->getOriginalAsset()->getResource();
            /** @var PersistentResource $thumbnailResource */
            $thumbnailResource = $thumbnail->getResource();

            if ($thumbnailResource === null ||
                $iteration < $offset ||
                $this->krakenService->shouldOptimize($originalAssetResource, $thumbnailResource) === false
            ) {
                $this->output->progressAdvance(1);
                $iteration++;
                continue;
            }

            try {
                $krakenIoResult = json_decode(
                    $this->krakenService->requestOptimizedResource($thumbnailResource),
                    true
                );
            } catch (\Exception $exception) {
                throw new Exception(
                    'Failed to get optimized version for ' . $thumbnailResource->getFileName() . '. ' .
                    'Original Message: ' . $exception->getMessage(),
                    1524251845
                );
            }

            $krakenIoResult['originalFilename'] = $thumbnailResource->getFileName();
            $krakenIoResult['resourceIdentifier'] = $thumbnailResource->getSha1();
            $savedBytes += $krakenIoResult['saved_bytes'];

            $this->resourceService->replaceThumbnailResource($thumbnail, $krakenIoResult);

            $this->output->progressAdvance(1);
            $iteration++;
        }

        $this->outputLine('');
        $this->outputLine('Saved ' . $savedBytes . ' bytes!');

        if ($this->liveOptimization === false) {
            $this->outputLine('Consider turning on `liveOptimization` in the settings now.');
        }
    }
}
