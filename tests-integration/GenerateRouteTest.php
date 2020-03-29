<?php

namespace Pulsestorm\Pestle\TestsIntegration;

require_once 'PestleTestIntegration.php';

class GenerateRouteTest extends PestleTestIntegration
{
    const COMMAND = 'generate_route Pulsestorm_HelloWorld frontend pulsestorm_helloworld Index Index Index';

    /**
     * @var string
     */
    protected $result;

    /**
     * Setup the integration test.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->runCommand(self::COMMAND);
        // $this->result = $this->runCommand();
    }

    /**
     * Check routes declaration file exists.
     *
     * @test
     */
//     public function xtestRoutesFileExists()
//     {
//         $result = file_exists(__DIR__.'/../app/code/Pulsestorm/HelloWorld/etc/frontend/routes.xml');
//         $this->assertTrue($result);
//     }
// 
//     /**
//      * Check module registration file exists.
//      *
//      * @test
//      */
//     public function xtestControllerFileExists()
//     {
//         $result = file_exists(__DIR__.'/../app/code/Pulsestorm/HelloWorld/Controller/Index/Index.php');
//         $this->assertTrue($result);
//     }
}
