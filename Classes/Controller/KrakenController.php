<?php
namespace GesagtGetan\KrakenOptimizer\Controller;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;
use TYPO3\Flow\Mvc\View\JsonView;
use GesagtGetan\KrakenOptimizer\Service\ResourceServiceInterface;

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
     * @throws \Neos\Flow\Exception
     * @see https://kraken.io/docs/wait-callback
     */
    public function replaceLocalFileAction()
    {
        $krakenIoResult = $this->request->getMainRequest()->getHttpRequest()->getArguments();

        if (isset($krakenIoResult['verificationToken']) &&
            password_verify($this->settings['krakenOptions']['auth']['api_key'], $krakenIoResult['verificationToken']) === false) {
            throw new \Neos\Flow\Exception('Invalid verification token supplied', 1524665601);
        }

        if (isset($krakenIoResult['success']) && $krakenIoResult['success'] !== true) {
            $this->resourceService->replaceLocalFile($krakenIoResult);
        } else {
            $this->systemLogger->log('Kraken was unable to optimize resource ' .
                (isset($krakenIoResult['file_name']) ? $krakenIoResult['file_name'] : '<no file name returned>'));
        }
    }
}
