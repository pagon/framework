<?php
/**
 * CacheTest.php.
 */

namespace Pagon;


class CacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var App
     */
    protected $app;

    public function setUp()
    {
        $this->app = App::create(array(
            'cache' => array(
                'user' => array(
                    'type' => 'file',
                )
            )
        ));
        ob_start();
        $this->app->run();
        ob_end_clean();
    }

    public function testFactory()
    {
        $file = Cache::create('file');

        $this->assertInstanceOf('Pagon\Cache\File', $file);
    }

    public function testFactoryNonExists()
    {
        $this->setExpectedException('InvalidArgumentException');
        $none = Cache::create('none');
    }

    public function testDispense()
    {
        $user_cache = Cache::dispense('cache.user');

        $this->assertInstanceOf('Pagon\Cache\File', $user_cache);

        $try_new_cache = Cache::dispense('cache.user');

        $this->assertEquals($try_new_cache, $user_cache);
    }

    public function testDispenseNonExists()
    {
        $this->setExpectedException('InvalidArgumentException');
        $user_cache = Cache::dispense('cache.user1');
    }
}
