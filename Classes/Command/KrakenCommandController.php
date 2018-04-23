<?php
namespace GesagtGetan\KrakenOptimizer\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Log\SystemLoggerInterface;
use Neos\Media\Domain\Repository\ThumbnailRepository;
use Neos\Media\Domain\Service\ThumbnailService;
use GesagtGetan\KrakenOptimizer\Service\ResourceServiceInterface;
use GesagtGetan\KrakenOptimizer\Service\KrakenServiceInterface;
use Neos\Flow\ResourceManagement\PersistentResource;

/**
 * @Flow\Scope("singleton")
 */
class KrakenCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $systemLogger;

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
     * @var array
     */
    protected $settings;

    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Optimize images with kraken.io API (recommended for first time optimization of existing thumbnails).
     *
     * Replaces all rendered thumbnails with an optimized version from Kraken.
     * It's recommended to run this command, just before activating "liveOptimization" in the settings.
     *
     * !!Warning!!
     * Executing this command will also send potentially optimized images to the Kraken API and thus will still
     * count towards your API quota and can lead to "over optimized" images when running lossy optimizations multiple times.
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
     * @throws \Neos\Flow\Exception
     */
    public function optimizeCommand()
    {
        $thumbnailCount = $this->thumbnailRepository->countAll();
        $iterator = $this->thumbnailRepository->findAllIterator();
        $this->output->progressStart($thumbnailCount);
        $savedBytes = 0;
        $answer = $this->output->askConfirmation('Send '. $thumbnailCount . ' thumbnails to Kraken for optimization?');

        if ($answer === false) {
            exit();
        }

        foreach ($this->thumbnailRepository->iterate($iterator) as $thumbnail) {
            if ($thumbnail->getResource() === null) {
                $this->output->progressAdvance(1);
                continue;
            }

            try {
                $krakenIoResult = json_decode($this->requestOptimizedResource($thumbnail->getResource()), true);
            } catch(\Exception $exception) {
                throw new \Neos\Flow\Exception(
                    'Failed to get optimized version for ' . $thumbnail->getResource()->getFileName() . '. ' .
                    'Original Message: ' . $exception->getMessage(), 1524251845 );
            }

            $krakenIoResult['originalFilename'] = $thumbnail->getResource()->getFileName();
            $savedBytes += $krakenIoResult['saved_bytes'];

            $this->resourceService->replaceLocalFile($krakenIoResult);
            $this->output->progressAdvance(1);
        }

        $this->outputLine('');
        $this->outputLine('Saved ' . $savedBytes . ' bytes!');
        if ($this->settings['liveOptimization'] !== true) {
            $this->outputLine('Consider turning on ``liveOptimization`` in the settings now.');
        }
    }

    /**
     * Request optimized resource from Kraken and return result.
     *
     * @param Resource $originalResource
     * @return string the response as JSON
     */
    private function requestOptimizedResource(Resource $originalResource): string
    {
        return $this->krakenService->requestOptimizedResource($originalResource, ['wait' => true]);
    }
}
