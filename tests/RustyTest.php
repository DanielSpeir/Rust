<?php
/**
 * Created by PhpStorm.
 * User: danielspeir
 * Date: 7/8/15
 * Time: 8:15 AM
 */

class RustyTest extends PHPUnit_Framework_TestCase
{

    public $oInstance = null;

    public $bCleanOutput = true;

    public static $aTestCase = array();

    /**
     * Set Up
     */
    public function setUp()
    {
        // Ob Start
        if ($this->bCleanOutput) {
            ob_start();
        }

        // Get Rust instance
        $this->oInstance = Rust::getRouter();

        // Define Rust configuration
        $this->oInstance->config(array(
            'dev' => true,
            'unit_test' => true,
            'build_all' => true
        ));

        // Clean routes & param casts after every unit test
        $this->oInstance->cleanRoutes();
        $this->oInstance->cleanCastParams();

        // Reset $aTestCase var after every unit test
        self::$aTestCase = array();
    }

    /**
     * Tear Down
     */
    public function tearDown()
    {
        // Clean the OB at the end of very unit test
        if ($this->bCleanOutput) {
            ob_end_clean();
        }
    }

    /**
     * Test serve with no routes
     */
    public function testServeWithNoRoutes()
    {
        $this->assertTrue($this->oInstance->serve());
    }

    /**
     * Build a basic Parent Route with a string return.
     */
    public function testParentRouteCreationWithStringReturn()
    {
        $this->assertTrue(
            $this->oInstance->route('base', 'Basic Parent Route Return')
        );
    }

    /**
     * Build a basic Child Route with a string return.
     */
    public function testChildRouteCreationWithStringReturn()
    {
        $this->assertTrue(
            $this->oInstance->route('base/param', 'Basic Child Route Return')
        );
    }

    /**
     * Build a basic Parent Route with a Closure return.
     */
    public function testParentRouteCreationWithClosureReturn()
    {
        $this->assertTrue(
            $this->oInstance->route('base', function () {
                return true;
            })
        );
    }

    /**
     * Build a basic Child Route with a Closure return.
     */
    public function testChildRouteCreationWithClosureReturn()
    {
        $this->assertTrue(
            $this->oInstance->route('base/param', function () {
                return true;
            })
        );
    }

    /**
     * Build a basic "All" route with a string return.
     */
    public function testIndexRouteCreation()
    {
        $this->assertTrue(
            $this->oInstance->index('Basic Index Route Return')
        );
    }

    /**
     * Build a basic "All" route with a string return.
     */
    public function testAllRouteCreation()
    {
        $this->assertTrue(
            $this->oInstance->all('Basic All Route Return')
        );
    }

    /**
     * Build a basic "Otherwise" route with a string return.
     */
    public function testOtherwiseRouteCreation()
    {
        $this->assertTrue(
            $this->oInstance->otherwise('Basic Otherwise Route Return')
        );
    }

    public function testRouteCreationWithNamespace()
    {
        $this->assertTrue(
            $this->oInstance->group('base', function(){
                $this->index(function(){
                   return true;
                });
            })
        );
    }

    /**
     * Retest the Serve, now with Routes built.
     */
    public function testRouteServeWithRoute()
    {
        $this->oInstance->route('base', function () {
            return 'base';
        });
        $this->assertTrue($this->oInstance->serve('base'));
    }

    /**
     * Test a dynamic route
     */
    public function testDynamicRoute()
    {
        $this->oInstance->route('base/:id', function () {
            $this->response(function ($id) {
                return self::$aTestCase[0] = $id;
            });
        });
        $this->oInstance->serve('base/5');

        $this->assertEquals(self::$aTestCase[0], '5');
    }

    /**
     * Test json response
     */
    public function testJsonResponse()
    {
        // Create some fake key/value pairs in Rust's Store object
        $this->oInstance->store()->fake = "data";
        $this->oInstance->store()->moreFake = "moreData";

        $this->oInstance->route('base', function(){
           $this->response(function(){
              return self::$aTestCase[0] = $this->json($this->store());
           });
        });
        $this->oInstance->serve('base');
        $this->assertJsonStringEqualsJsonString(self::$aTestCase[0], json_encode($this->oInstance->store()));
    }

    /**
     * Test Ajax
     */
    public function testIsAjax()
    {
        // Initial test should return false
        $this->assertFalse($this->oInstance->isAjax());

        // Simulate the server var set when an AJAX reuqest is made.
        // Now test should return true.
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPREQUEST';
        $this->assertTrue($this->oInstance->isAjax());
    }

    /**
     * Test storage object
     */
    public function testStorageObject()
    {
        $this->oInstance->store()->sampleVar = 'sampleVal';
        $this->assertEquals($this->oInstance->store('sampleVar'), 'sampleVal');
    }

    /**
     * Test clean of storage object
     */
    public function testStorageClean()
    {
        $this->assertTrue($this->oInstance->cleanStore());
        $this->assertFalse($this->oInstance->store('sampleVar'));
    }

    /**
     * Test Render View
     */
    public function testRenderView(){
        $this->oInstance->route('base', function(){
            $this->response(function(){
                return self::$aTestCase[0] = $this->renderView('tests/view');
            });
        });
        $this->oInstance->serve('base');
        $this->assertTrue(self::$aTestCase[0]);
    }

    /**
     * Test the castParams function at the Route level.
     * That is, directly within the "route" function.
     */
    public function testRouteLevelCastParams()
    {

        $this->oInstance->route('base/:id', function () {
            $this->castParams(['id' => 'int']);
            $this->response(function ($id) {
                return self::$aTestCase[0] = $id;
            });
        });
        $this->oInstance->serve('base/4');

        $this->assertSame(self::$aTestCase[0], 4);
    }

    /**
     * Test the castParams function at the Namespace level.
     * That is, directly within the "group" function.
     */
    public function testNamespaceLevelCastParams()
    {
        $this->oInstance->group('base', function () {
            // Namespace-level param cast
            $this->castParams([
                'id' => 'int',
                'bool' => 'boolean'
            ]);

            $this->route(':id/:bool', function () {
                // Indexes on Route-level param cast should override
                // Namespace and Global-level, since they're more specific
                $this->castParams([
                    'id' => 'string'
                ]);

                $this->response(function ($id, $bool) {
                    self::$aTestCase[0] = $id;
                    self::$aTestCase[1] = $bool;
                });

            });
        });
        $this->oInstance->serve('base/4/test');

        $this->assertSame(self::$aTestCase[0], '4');
        $this->assertSame(self::$aTestCase[1], true);
    }

    /**
     * Test castParams function at the Global level.
     * That is, outside of all Namespace and Route functions.
     */
    public function testGlobalLevelCastParams()
    {

        $this->oInstance->castParams([
            'id' => 'int'
        ]);

        $this->oInstance->group('base', function () {
            $this->route(':id', function () {

                $this->response(function ($id, $bool) {
                    self::$aTestCase[0] = $id;
                    self::$aTestCase[1] = $bool;
                });

            });
        });
        $this->oInstance->serve('base/4');

        $this->assertSame(self::$aTestCase[0], 4);
    }

    /**
     * Test the Hungarian Naming convention of route params
     * as an alternative to the castParams function.
     */
    public function testHungarianCastParams()
    {
        $this->oInstance->route('base/:iId/:bBool', function () {
            $this->response(function ($id, $bool) {
                self::$aTestCase[0] = $id;
                self::$aTestCase[1] = $bool;
            });
        });
        $this->oInstance->serve('base/4/7');

        $this->assertSame(self::$aTestCase[0], 4);
        $this->assertSame(self::$aTestCase[1], true);
    }

    /**
     * Test return of liveRoute function
     */
    public function testLiveRoute()
    {
        $this->oInstance->route('base/param', 'Route Return.');
        $this->oInstance->serve('base/param');
        $this->assertEquals($this->oInstance->liveRoute(), 'base/param');
        $this->assertTrue($this->oInstance->isRoute('base/param'));
    }

    /**
     * Test return of liveRouteParent function
     */
    public function testLiveRouteParent()
    {
        $this->oInstance->route('base/param', 'Route Return.');
        $this->oInstance->serve('base/param');
        $this->assertEquals($this->oInstance->liveRouteParent(), 'base');
        $this->assertTrue($this->oInstance->isRouteParent('base'));
    }

    /**
     * Test Query Strings
     */
    public function testQueryString()
    {
        $_SERVER['QUERY_STRING'] = 'var1=val1&var2=val2';
        $this->assertEquals(gettype($this->oInstance->getQueryString()), 'array');
        $this->assertEquals($this->oInstance->getQueryString('var1'), 'val1');
        $this->assertEquals($this->oInstance->getQueryString('var2'), 'val2');
    }

    /**
     * Test setting Request Method
     */
    public function testSetRequestMethod()
    {
        // Test valid, lowercase method
        $this->assertTrue($this->oInstance->setRequestMethod('post'));

        // Test valid, uppercase method
        $this->assertTrue($this->oInstance->setRequestMethod('DELETE'));

        // Test invalid method
        $this->assertFalse($this->oInstance->setRequestMethod('fake'));
    }

    /**
     * Test getting Request Method
     */
    public function testGetRequestMethod()
    {
        $this->oInstance->setRequestMethod('get');
        $this->assertEquals($this->oInstance->getRequestMethod(), 'GET');
    }

    /**
     * Test return of response checks
     */
    public function testResponseCheckers()
    {
        // Evaluate Get
        $this->oInstance->setRequestMethod('get');
        $this->assertTrue($this->oInstance->isGet());

        // Evaluate Post
        $this->oInstance->setRequestMethod('post');
        $this->assertTrue($this->oInstance->isPost());

        // Evaluate Patch
        $this->oInstance->setRequestMethod('patch');
        $this->assertTrue($this->oInstance->isPatch());

        // Evaluate Delete
        $this->oInstance->setRequestMethod('delete');
        $this->assertTrue($this->oInstance->isDelete());

        // Evaluate Put
        $this->oInstance->setRequestMethod('put');
        $this->assertTrue($this->oInstance->isPut());
    }

    public function testControllerRender(){
        // Flush current buffer
        ob_flush();

        $this->oInstance->route('base', function(){
            $this->controller('tests/controllers/myController.php');
            $this->get('get');
            $this->post('post');
            $this->put('put');
            $this->patch('patch');
            $this->delete('delete');
        });

        ob_start();

        $this->oInstance->setRequestMethod('get');
        $this->oInstance->serve('base');
        $this->assertEquals(ob_get_clean(), 'get response');

        ob_start();

        $this->oInstance->setRequestMethod('post');
        $this->oInstance->serve('base');
        $this->assertEquals(ob_get_clean(), 'post response');

        ob_start();

        $this->oInstance->setRequestMethod('put');
        $this->oInstance->serve('base');
        $this->assertEquals(ob_get_clean(), 'put response');

        ob_start();

        $this->oInstance->setRequestMethod('patch');
        $this->oInstance->serve('base');
        $this->assertEquals(ob_get_clean(), 'patch response');

        ob_start();

        $this->oInstance->setRequestMethod('delete');
        $this->oInstance->serve('base');
        $this->assertEquals(ob_get_clean(), 'delete response');
    }
}
