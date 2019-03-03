<?php
namespace GesagtGetan\KrakenOptimizer\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use GesagtGetan\KrakenOptimizer\Service\ResourceServiceInterface;
use GesagtGetan\KrakenOptimizer\Service\KrakenServiceInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
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
    public function replaceLocalFileAction()
    {
        $krakenIoResult = $this->request->getMainRequest()->getHttpRequest()->getArguments();

        if (!isset($krakenIoResult['success']) || $krakenIoResult['success'] !== 'true') {
            throw new \Neos\Flow\Exception('Kraken was unable to optimize resource', 1524665608);
        }

        if (!isset($krakenIoResult['file_name'])) {
            throw new \Neos\Flow\Exception('Filename missing in Kraken callback payload', 1524665605);
        }

        if (!isset($krakenIoResult['verificationToken']) ||
            !$this->krakenService->verifyToken($krakenIoResult['verificationToken'], $krakenIoResult['file_name'])) {
            throw new \Neos\Flow\Exception('Invalid verification token supplied', 1524665601);
        }

        $this->resourceService->replaceLocalFile($krakenIoResult);
    }

    /**
     * Replaces the local file within the file system with the optimized image delivered by Kraken.
     *
     * @throws \Neos\Flow\Exception
     * @see https://kraken.io/docs/wait-callback
     */
    public function replaceThumbnailResourceAction()
    {
        $krakenIoResult = $this->request->getArguments();

        if($this->request->hasArgument('mock')) {
            $krakenIoResult = array_merge($krakenIoResult, [
                'success' => 'true',
                'file_name' => 'stream',
                'kraked_url' => 'https://dl.kraken.io/api/01/a7/92/8570b2e1fb33232caea83921b5/c50c2b693bc8aa21b4c05814b3ead18ecb865eca',
                "original_size" => 324520,
                "kraked_size" => 165358,
                "saved_bytes" => 159162
            ]);
        }

        \Neos\Flow\var_dump($krakenIoResult);

        if (!isset($krakenIoResult['success']) || $krakenIoResult['success'] !== 'true') {
            throw new \Neos\Flow\Exception('Kraken was unable to optimize resource', 1524665608);
        }

        if (!isset($krakenIoResult['file_name'])) {
            throw new \Neos\Flow\Exception('Filename missing in Kraken callback payload', 1524665605);
        }

        if (!isset($krakenIoResult['verificationToken']) ||
            $this->krakenService->verifyToken($krakenIoResult['verificationToken'], $krakenIoResult['originalFilename']) === false) {
            throw new \Neos\Flow\Exception('Invalid verification token supplied', 1524665601);
        }

        // Get thumbnail identifier
        $resourceIdentifier = $this->request->getArgument('resourceIdentifier');
        $query = $this->entityManager->createQuery('SELECT t FROM Neos\Media\Domain\Model\Thumbnail t WHERE t.resource = :resource');
        $query->setParameter('resource', $resourceIdentifier);
        $query->setMaxResults(1);
        $thumbnail = $query->getSingleResult();

        $this->logger->debug('Found thumbnail for resource identifier', ['thumbnail' => $thumbnail, 'resource' => $resourceIdentifier]);

        $this->resourceService->replaceThumbnailResource($thumbnail, $krakenIoResult);
    }
}
