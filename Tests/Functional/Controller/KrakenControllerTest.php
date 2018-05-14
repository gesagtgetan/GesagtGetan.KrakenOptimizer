<?php
namespace GesagtGetan\KrakenOptimizer\Tests\Functional\Controller;

use GesagtGetan\KrakenOptimizer\Service\KrakenService;
use TYPO3\Flow\Tests\FunctionalTestCase;

class KrakenControllerTest extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    const REPLACE_ACTION_URI = '/optimizeImage';

    /**
     * @var KrakenService
     */
    protected $krakenService;

    public function setUp()
    {
        parent::setUp();

        $this->krakenService = $this->objectManager->get(KrakenService::class);
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
            'file_name' => 'e6f36746ccba42c288acf906e636bb278eaeb7e8'
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
            'file_name' => 'e6f36746ccba42c288acf906e636bb278eaeb7e8',
            'verificationToken' => '$2c$03$vb.Vrkt3BddbmR.4yc6gJOfG5p.5BEeUcUIvMCvkQQe43de6S98ni'
        ];

        $response = $this->browser->request('http://localhost' . self::REPLACE_ACTION_URI, 'POST', $payload);
        $this->assertEquals('1524665601', $response->getHeader('X-Flow-ExceptionCode'));
    }

    /**
     * No exception should be thrown if payload passes checks.
     *
     * @test
     */
    public function controllerShouldReturnNullIfPayloadIsValid()
    {
        $payload = [
            'success' => 'true',
            'file_name' => 'e6f36746ccba42c288acf906e636bb278eaeb7e8'
        ];
        $payload['verificationToken'] = $this->krakenService->createToken($payload['file_name']);

        $response = $this->browser->request('http://localhost' . self::REPLACE_ACTION_URI, 'POST', $payload);
        $this->assertEquals('null', $response->getContent());
    }
}