<?php
namespace GesagtGetan\KrakenOptimizer\Controller;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;
use TYPO3\Flow\Mvc\View\JsonView;
use GesagtGetan\KrakenOptimizer\Service\ResourceServiceInterface;
use GesagtGetan\KrakenOptimizer\Service\KrakenServiceInterface;

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
     * @var array
     */
    protected $settings;

    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Replaces the local file within the file system with the optimized image delivered by Kraken.
     *
     * @throws \TYPO3\Flow\Exception
     * @see https://kraken.io/docs/wait-callback
     */
    public function replaceLocalFileAction()
    {
        $krakenIoResult = $this->request->getMainRequest()->getHttpRequest()->getArguments();

        if (!isset($krakenIoResult['success']) || $krakenIoResult['success'] !== 'true') {
            throw new \TYPO3\Flow\Exception('Kraken was unable to optimize resource', 1524665608);
        }

        if (!isset($krakenIoResult['file_name'])) {
            throw new \TYPO3\Flow\Exception('Filename missing in Kraken callback payload', 1524665605);
        }

        if (!isset($krakenIoResult['verificationToken']) ||
            $this->krakenService->verifyToken($krakenIoResult['verificationToken'], $krakenIoResult['file_name']) === false) {
            throw new \TYPO3\Flow\Exception('Invalid verification token supplied', 1524665601);
        }

        $this->resourceService->replaceLocalFile($krakenIoResult);
    }
}
