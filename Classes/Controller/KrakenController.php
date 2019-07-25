<?php
namespace GesagtGetan\KrakenOptimizer\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use GesagtGetan\KrakenOptimizer\Service\ResourceServiceInterface;
use GesagtGetan\KrakenOptimizer\Service\KrakenServiceInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Media\Domain\Model\Thumbnail;

/**
 * This is the Callback Controller for the Kraken API.
 *
 * @Flow\Scope("singleton")
 */
class KrakenController extends ActionController
{
    /**
     * @Flow\Inject
     * @var ResourceServiceInterface
     */
    protected $resourceService;

    /**
     * @var string
     */
    protected $defaultViewObjectName = JsonView::class;

    /**
     * @Flow\Inject
     * @var KrakenServiceInterface
     */
    protected $krakenService;

    /**
     * @Flow\Inject
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * Replaces the local file within the file system with the optimized image delivered by Kraken.
     *
     * @throws \Neos\Flow\Exception
     * @see https://kraken.io/docs/wait-callback
     */
    public function replaceThumbnailResourceAction()
    {
        $krakenIoResult = $this->request->getArguments();

        if (!isset($krakenIoResult['success']) || $krakenIoResult['success'] !== 'true') {
            throw new \Neos\Flow\Exception('Kraken was unable to optimize resource', 1524665608);
        }

        if (!isset($krakenIoResult['resourceIdentifier']) ||
            !isset($krakenIoResult['originalFilename'])
        ) {
            throw new \Neos\Flow\Exception(
                'Filename or resource identifier missing in Kraken callback payload',
                1524665605
            );
        }

        if (!isset($krakenIoResult['verificationToken']) ||
            $this->krakenService->verifyToken(
                $krakenIoResult['verificationToken'],
                $krakenIoResult['originalFilename']
            ) === false
        ) {
            throw new \Neos\Flow\Exception('Invalid verification token supplied', 1524665601);
        }

        try {
            $resourceIdentifier = $krakenIoResult['resourceIdentifier'];
            $resource = $this->resourceManager->getResourceBySha1($resourceIdentifier);

            if ((!$resource instanceof PersistentResource)) {
                throw new Exception(
                    'Could not find resource with identifier ' . $resourceIdentifier,
                    1524665602
                );
            }
            $thumbnail = $this->resourceService->getThumbnailsByResource($resource);

            if (isset($thumbnail[0]) && $thumbnail[0] instanceof Thumbnail) {
                $thumbnail = $thumbnail[0];
                $this->logger->debug(
                    sprintf('Found thumbnail for resource identifier %s', $resourceIdentifier)
                );

                $this->resourceService->replaceThumbnailResource($thumbnail, $krakenIoResult);
            } else {
                $this->logger->debug(
                    sprintf('Could not find a thumbnail object for resource identifier %s', $resourceIdentifier)
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed attempting to replace resource %s for thumbnail', $resourceIdentifier)
            );
        }
    }
}
