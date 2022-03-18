<?php

namespace Sugarcrm\REST\Tests\Auth;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Sugarcrm\REST\Auth\SugarOAuthController;
use Sugarcrm\REST\Client\SugarApi;
use Sugarcrm\REST\Endpoint\OAuth2Sudo;
use Sugarcrm\REST\Storage\SugarStaticStorage;
use Sugarcrm\REST\Tests\Stubs\Auth\SugarOAuthStub;
use Sugarcrm\REST\Tests\Stubs\Client\Client;


/**
 * Class SugarOAuthControllerTest
 * @package Sugarcrm\REST\Tests\Auth
 * @coversDefaultClass Sugarcrm\REST\Auth\SugarOAuthController
 * @group SugarOAuthControllerTest
 */
class SugarOAuthControllerTest extends \PHPUnit\Framework\TestCase {
    /**
     * @var Client
     */
    protected static $client;

    public static function setUpBeforeClass(): void {
        //Add Setup for static properties here
        self::$client = new Client();
    }

    public static function tearDownAfterClass(): void {
        //Add Tear Down for static properties here
    }

    public function setUp(): void {
        parent::setUp();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * @covers ::__construct
     */
    public function testConstructor() {
        $Auth = new SugarOAuthStub();
        $this->assertEquals(true, in_array('sudo', $Auth->getActions()));
    }

    /**
     * @covers ::setCredentials
     * @covers ::setPlatform
     * @covers ::getPlatform
     * @covers ::reset
     */
    public function testSetCredentials() {
        $Auth = new SugarOAuthController();
        $Storage = new SugarStaticStorage();
        $Auth->setStorageController($Storage);
        $this->assertEquals($Auth, $Auth->setCredentials(array(
            'username' => 'admin',
            'password' => '',
            'client_id' => 'sugar',
            'client_secret' => '',
            'platform' => 'api'
        )));
        $this->assertEquals('api', $Auth->getPlatform());
        $this->assertEmpty($Auth->getToken());
        $Storage->store($Auth->getCredentials(), array(
            'access_token' => '1234',
            'refresh_token' => '5678',
        ));
        $this->assertEquals($Auth, $Auth->setCredentials(array(
            'username' => 'admin',
            'password' => '',
            'client_id' => 'sugar',
            'client_secret' => '',
            'platform' => 'api'
        )));
        $this->assertEquals(array(
            'access_token' => '1234',
            'refresh_token' => '5678',
        ), $Auth->getToken());
        $this->assertEquals($Auth, $Auth->setPlatform('mobile'));
        $creds = $Auth->getCredentials();
        $this->assertEquals('mobile', $creds[$Auth::OAUTH_PROP_PLATFORM]);
        $this->assertEquals(array(
            'username' => 'admin',
            'password' => '',
            'client_id' => 'sugar',
            'client_secret' => '',
            'platform' => 'mobile'
        ), $creds);
        $this->assertEquals('mobile', $Auth->getPlatform());
        $Auth->reset();
        $this->assertEquals(SugarApi::PLATFORM_BASE,$Auth->getPlatform());
        $this->assertEquals([],$Auth->getCredentials());
    }

    /**
     * @covers ::updateCredentials
     */
    public function testUpdateCredentials() {
        $Auth = new SugarOAuthController();
        $this->assertEquals(array(
            'username' => '',
            'password' => '',
            'client_id' => 'sugar',
            'client_secret' => '',
            'platform' => 'base'
        ), $Auth->getCredentials());
        $this->assertEquals($Auth, $Auth->updateCredentials(array(
            'username' => 'admin'
        )));
        $this->assertEquals(array(
            'username' => 'admin',
            'password' => '',
            'client_id' => 'sugar',
            'client_secret' => '',
            'platform' => 'base'
        ), $Auth->getCredentials());
        $this->assertEquals($Auth, $Auth->updateCredentials(array(
            'username' => 'system',
            'password' => 'asdf'
        )));
        $this->assertEquals(array(
            'username' => 'system',
            'password' => 'asdf',
            'client_id' => 'sugar',
            'client_secret' => '',
            'platform' => 'base'
        ), $Auth->getCredentials());
        $this->assertEquals($Auth, $Auth->updateCredentials(array(
            'platform' => ''
        )));
        $this->assertEquals(array(
            'username' => 'system',
            'password' => 'asdf',
            'client_id' => 'sugar',
            'client_secret' => '',
            'platform' => ''
        ), $Auth->getCredentials());
    }

    /**
     * @covers ::getAuthHeaderValue
     */
    public function testAuthHeader() {
        $Auth = new SugarOAuthStub();
        $Request = $Auth->configureRequest(new Request("POST", "/"));
        $headers = $Request->getHeaders();
        $this->assertEquals(['bar'], $headers['OAuth-Token']);
    }

    /**
     * @covers ::sudo
     * @covers ::configureSudoEndpoint
     * @covers Sugarcrm\REST\Client\SugarApi::sudo
     */
    public function testSudo() {
        self::$client->container = [];
        self::$client->mockResponses->append(new Response(200, [], json_encode(['access_token' => 'at-bar'])));

        $Auth = new SugarOAuthStub();
        $Auth->setCredentials(array(
            'username' => 'system',
            'password' => 'asdf',
            'client_id' => 'sugar',
            'client_secret' => '',
            'platform' => 'api'
        ));
        $EP = new OAuth2Sudo();
        $EP->setClient(self::$client);
        $EP->setBaseUrl('http://localhost/rest/v10');
        $Auth->setActionEndpoint($Auth::ACTION_SUGAR_SUDO, $EP);
        $Auth->sudo('max');
        $request = current(self::$client->container)['request'];
        $this->assertEquals('http://localhost/rest/v10/oauth2/sudo/max', $request->getUri()->__toString());
        $this->assertEquals('{"platform":"api","client_id":"sugar"}', $request->getBody()->getContents());
    }
}
