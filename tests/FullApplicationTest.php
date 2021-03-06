<?php

use Mockery as m;
use Laravel\Lumen\Application;
use Laravel\Lumen\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class FullApplicationTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testBasicRequest()
    {
        $app = new Application;

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $response = $app->handle($request = Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());

        $this->assertInstanceOf('Illuminate\Http\Request', $request);
    }

    public function testBasicSymfonyRequest()
    {
        $app = new Application;

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $response = $app->handle(SymfonyRequest::create('/', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddRouteMultipleMethodRequest()
    {
        $app = new Application;

        $app->router->addRoute(['GET', 'POST'], '/', function () {
            return response('Hello World');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());

        $response = $app->handle(Request::create('/', 'POST'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testRequestWithParameters()
    {
        $app = new Application;

        $app->router->get('/foo/{bar}/{baz}', function ($bar, $baz) {
            return response($bar.$baz);
        });

        $response = $app->handle($request = Request::create('/foo/1/2', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('12', $response->getContent());

        $this->assertEquals(1, $request->route('bar'));
        $this->assertEquals(2, $request->route('baz'));
    }

    public function testCallbackRouteWithDefaultParameter()
    {
        $app = new Application;
        $app->router->get('/foo-bar/{baz}', function ($baz = 'default-value') {
            return response($baz);
        });

        $response = $app->handle(Request::create('/foo-bar/something', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('something', $response->getContent());
    }

    public function testGlobalMiddleware()
    {
        $app = new Application;

        $app->middleware(['LumenTestMiddleware']);

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());
    }

    public function testRouteMiddleware()
    {
        $app = new Application;

        $app->routeMiddleware(['foo' => 'LumenTestMiddleware', 'passing' => 'LumenTestPlainMiddleware']);

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $app->router->get('/foo', ['middleware' => 'foo', function () {
            return response('Hello World');
        }]);

        $app->router->get('/bar', ['middleware' => ['foo'], function () {
            return response('Hello World');
        }]);

        $app->router->get('/fooBar', ['middleware' => 'passing|foo', function () {
            return response('Hello World');
        }]);

        $response = $app->handle(Request::create('/', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());

        $response = $app->handle(Request::create('/foo', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());

        $response = $app->handle(Request::create('/bar', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());

        $response = $app->handle(Request::create('/fooBar', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());
    }

    public function testGlobalMiddlewareParameters()
    {
        $app = new Application;

        $app->middleware(['LumenTestParameterizedMiddleware:foo,bar']);

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware - foo - bar', $response->getContent());
    }

    public function testRouteMiddlewareParameters()
    {
        $app = new Application;

        $app->routeMiddleware(['foo' => 'LumenTestParameterizedMiddleware', 'passing' => 'LumenTestPlainMiddleware']);

        $app->router->get('/', ['middleware' => 'passing|foo:bar,boom', function () {
            return response('Hello World');
        }]);

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware - bar - boom', $response->getContent());
    }

    public function testWithMiddlewareDisabled()
    {
        $app = new Application;

        $app->middleware(['LumenTestMiddleware']);
        $app->instance('middleware.disable', true);

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testTerminableGlobalMiddleware()
    {
        $app = new Application;

        $app->middleware(['LumenTestTerminateMiddleware']);

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('TERMINATED', $response->getContent());
    }

    public function testTerminateWithMiddlewareDisabled()
    {
        $app = new Application;

        $app->middleware(['LumenTestTerminateMiddleware']);
        $app->instance('middleware.disable', true);

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testNotFoundResponse()
    {
        $app = new Application;
        $app->instance('Illuminate\Contracts\Debug\ExceptionHandler', $mock = m::mock('Laravel\Lumen\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->get('/', function () {
            return response('Hello World');
        });

        $response = $app->handle(Request::create('/foo', 'GET'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMethodNotAllowedResponse()
    {
        $app = new Application;
        $app->instance('Illuminate\Contracts\Debug\ExceptionHandler', $mock = m::mock('Laravel\Lumen\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->post('/', function () {
            return response('Hello World');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testResponsableInterface()
    {
        $app = new Application;

        $app->router->get('/foo/{foo}', function () {
            return new ResponsableResponse;
        });

        $request = Request::create('/foo/999', 'GET');
        $response = $app->handle($request);

        $this->assertEquals(999, $request->route('foo'));
        $this->assertEquals(999, $response->original);
    }

    public function testUncaughtExceptionResponse()
    {
        $app = new Application;
        $app->instance('Illuminate\Contracts\Debug\ExceptionHandler', $mock = m::mock('Laravel\Lumen\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->get('/', function () {
            throw new \RuntimeException('app exception');
        });

        $response = $app->handle(Request::create('/', 'GET'));
        $this->assertInstanceOf('Illuminate\Http\Response', $response);
    }

    public function testGeneratingUrls()
    {
        $app = new Application;
        $app->instance('request', Request::create('http://lumen.laravel.com', 'GET'));
        unset($app->availableBindings['request']);

        $app->router->get('/foo-bar', ['as' => 'foo', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz}/{boom}', ['as' => 'bar', function () {
            //
        }]);

        $this->assertEquals('http://lumen.laravel.com/something', url('something'));
        $this->assertEquals('http://lumen.laravel.com/foo-bar', route('foo'));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1/2', route('bar', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar?baz=1&boom=2', route('foo', ['baz' => 1, 'boom' => 2]));
    }

    public function testGeneratingUrlsForRegexParameters()
    {
        $app = new Application;
        $app->instance('request', Request::create('http://lumen.laravel.com', 'GET'));
        unset($app->availableBindings['request']);

        $app->router->get('/foo-bar', ['as' => 'foo', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz:[0-9]+}/{boom}', ['as' => 'bar', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz:[0-9]+}/{boom:[0-9]+}', ['as' => 'baz', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz:[0-9]{2,5}}', ['as' => 'boom', function () {
            //
        }]);

        $this->assertEquals('http://lumen.laravel.com/something', url('something'));
        $this->assertEquals('http://lumen.laravel.com/foo-bar', route('foo'));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1/2', route('bar', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1/2', route('baz', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/{baz:[0-9]+}/{boom:[0-9]+}?ba=1&bo=2', route('baz', ['ba' => 1, 'bo' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/5', route('boom', ['baz' => 5]));
    }

    public function testRegisterServiceProvider()
    {
        $app = new Application;
        $provider = new LumenTestServiceProvider($app);
        $app->register($provider);

        $this->assertTrue(true);
    }

    public function testApplicationBootsServiceProvidersOnBoot()
    {
        $app = new Application();

        $provider = new LumenBootableTestServiceProvider($app);
        $app->register($provider);

        $this->assertFalse($provider->booted);
        $app->boot();
        $this->assertTrue($provider->booted);
    }

    public function testRegisterServiceProviderAfterBoot()
    {
        $app = new Application();
        $provider = new LumenBootableTestServiceProvider($app);
        $app->boot();
        $app->register($provider);
        $this->assertTrue($provider->booted);
    }

    public function testApplicationBootsOnlyOnce()
    {
        $app = new Application();
        $provider = new class($app) extends \Illuminate\Support\ServiceProvider {
            public $bootCount = 0;

            public function boot()
            {
                $this->bootCount += 1;
            }
        };

        $app->register($provider);
        $app->boot();
        $app->boot();
        $this->assertEquals(1, $provider->bootCount);
    }

    public function testApplicationBootsWhenRequestIsDispatched()
    {
        $app = new Application();
        $provider = new LumenBootableTestServiceProvider($app);
        $app->register($provider);
        $resp = $app->dispatch();
        $this->assertTrue($provider->booted);
    }

    public function testUsingCustomDispatcher()
    {
        $routes = new FastRoute\RouteCollector(new FastRoute\RouteParser\Std, new FastRoute\DataGenerator\GroupCountBased);

        $routes->addRoute('GET', '/', [function () {
            return response('Hello World');
        }]);

        $app = new Application;

        $app->setDispatcher(new FastRoute\Dispatcher\GroupCountBased($routes->getData()));

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testMiddlewareReceiveResponsesEvenWhenStringReturned()
    {
        unset($_SERVER['__middleware.response']);

        $app = new Application;

        $app->routeMiddleware(['foo' => 'LumenTestPlainMiddleware']);

        $app->router->get('/', ['middleware' => 'foo', function () {
            return 'Hello World';
        }]);

        $response = $app->handle(Request::create('/', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
        $this->assertTrue($_SERVER['__middleware.response']);
    }

    public function testBasicControllerDispatching()
    {
        $app = new Application;

        $app->router->get('/show/{id}', 'LumenTestController@show');

        $response = $app->handle(Request::create('/show/25', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('25', $response->getContent());
    }

    public function testBasicControllerDispatchingWithGroup()
    {
        $app = new Application;
        $app->routeMiddleware(['test' => LumenTestMiddleware::class]);

        $app->router->group(['middleware' => 'test'], function ($router) {
            $router->get('/show/{id}', 'LumenTestController@show');
        });

        $response = $app->handle(Request::create('/show/25', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());
    }

    public function testBasicControllerDispatchingWithGroupSuffix()
    {
        $app = new Application;
        $app->routeMiddleware(['test' => LumenTestMiddleware::class]);

        $app->router->group(['suffix' => '.{format:json|xml}'], function ($router) {
            $router->get('/show/{id}', 'LumenTestController@show');
        });

        $response = $app->handle(Request::create('/show/25.xml', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('25', $response->getContent());
    }

    public function testBasicControllerDispatchingWithGroupAndSuffixWithPath()
    {
        $app = new Application;
        $app->routeMiddleware(['test' => LumenTestMiddleware::class]);

        $app->router->group(['suffix' => '/{format:json|xml}'], function ($router) {
            $router->get('/show/{id}', 'LumenTestController@show');
        });

        $response = $app->handle(Request::create('/show/test/json', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('test', $response->getContent());
    }

    public function testBasicControllerDispatchingWithMiddlewareIntercept()
    {
        $app = new Application;
        $app->routeMiddleware(['test' => LumenTestMiddleware::class]);
        $app->router->get('/show/{id}', 'LumenTestControllerWithMiddleware@show');

        $response = $app->handle(Request::create('/show/25', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());
    }

    public function testBasicInvokableActionDispatching()
    {
        $app = new Application;

        $app->router->get('/action/{id}', 'LumenTestAction');

        $response = $app->handle(Request::create('/action/199', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('199', $response->getContent());
    }

    public function testEnvironmentDetection()
    {
        $app = new Application;

        $this->assertEquals('production', $app->environment());
        $this->assertTrue($app->environment('production'));
        $this->assertTrue($app->environment(['production']));
    }

    public function testNamespaceDetection()
    {
        $app = new Application;
        $this->expectException('RuntimeException');
        $app->getNamespace();
    }

    public function testRunningUnitTestsDetection()
    {
        $app = new Application;

        $this->assertFalse($app->runningUnitTests());
    }

    public function testValidationHelpers()
    {
        $app = new Application;

        $app->router->get('/', function (Illuminate\Http\Request $request) {
            $data = $this->validate($request, ['name' => 'required']);

            return $data;
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(422, $response->getStatusCode());

        $response = $app->handle(Request::create('/', 'GET', ['name' => 'Jon']));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($response->getContent(), '{"name":"Jon"}');
    }

    public function testRedirectResponse()
    {
        $app = new Application;

        $app->router->get('/', function (Illuminate\Http\Request $request) {
            return redirect('home');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testRedirectToNamedRoute()
    {
        $app = new Application;

        $app->router->get('login', ['as' => 'login', function (Illuminate\Http\Request $request) {
            return 'login';
        }]);

        $app->router->get('/', function (Illuminate\Http\Request $request) {
            return redirect()->route('login');
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testRequestUser()
    {
        $app = new Application();

        $app['auth']->viaRequest('api', function ($request) {
            return new \Illuminate\Auth\GenericUser(['id' => 1234]);
        });

        $app->router->get('/', function (Illuminate\Http\Request $request) {
            return $request->user()->getAuthIdentifier();
        });

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertSame('1234', $response->getContent());
    }

    public function testCanResolveValidationFactoryFromContract()
    {
        $app = new Application();

        $validator = $app['Illuminate\Contracts\Validation\Factory'];

        $this->assertInstanceOf('Illuminate\Contracts\Validation\Factory', $validator);
    }

    public function testCanMergeUserProvidedFacadesWithDefaultOnes()
    {
        $app = new Application();

        $aliases = [
            UserFacade::class => 'Foo',
        ];

        $app->withFacades(true, $aliases);

        $this->assertTrue(class_exists('Foo'));
    }

    public function testNestedGroupMiddlewaresRequest()
    {
        $app = new Application();

        $app->router->group(['middleware' => 'middleware1'], function ($router) {
            $router->group(['middleware' => 'middleware2|middleware3'], function ($router) {
                $router->get('test', 'LumenTestController@show');
            });
        });

        $route = $app->router->getRoutes()['GET/test'];

        $this->assertEquals([
            'middleware1',
            'middleware2',
            'middleware3',
        ], $route['action']['middleware']);
    }

    public function testNestedGroupNamespaceRequest()
    {
        $app = new Application();

        $app->router->group(['namespace' => 'Hello'], function ($router) {
            $router->group(['namespace' => 'World'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $routes = $app->router->getRoutes();

        $route = $routes['GET/world'];

        $this->assertEquals('Hello\\World\\Class@method', $route['action']['uses']);
    }

    public function testNestedGroupPrefixRequest()
    {
        $app = new Application();

        $app->router->group(['prefix' => 'hello'], function ($router) {
            $router->group(['prefix' => 'world'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/hello/world/world', $routes);
    }

    public function testNestedGroupAsRequest()
    {
        $app = new Application();

        $app->router->group(['as' => 'hello'], function ($router) {
            $router->group(['as' => 'world'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $this->assertArrayHasKey('hello.world', $app->router->namedRoutes);
        $this->assertEquals('/world', $app->router->namedRoutes['hello.world']);
    }
}

class LumenTestService
{
}

class LumenTestServiceProvider extends Illuminate\Support\ServiceProvider
{
    public function register()
    {
    }
}

class LumenBootableTestServiceProvider extends Illuminate\Support\ServiceProvider
{
    public $booted = false;

    public function boot()
    {
        $this->booted = true;
    }
}

class LumenTestController
{
    public function __construct(LumenTestService $service)
    {
        //
    }

    public function show($id)
    {
        return $id;
    }
}

class LumenTestControllerWithMiddleware extends Laravel\Lumen\Routing\Controller
{
    public function __construct(LumenTestService $service)
    {
        $this->middleware('test');
    }

    public function show($id)
    {
        return $id;
    }
}

class LumenTestMiddleware
{
    public function handle($request, $next)
    {
        return response('Middleware');
    }
}

class LumenTestPlainMiddleware
{
    public function handle($request, $next)
    {
        $response = $next($request);
        $_SERVER['__middleware.response'] = $response instanceof Illuminate\Http\Response;

        return $response;
    }
}

class LumenTestParameterizedMiddleware
{
    public function handle($request, $next, $parameter1, $parameter2)
    {
        return response("Middleware - $parameter1 - $parameter2");
    }
}

class LumenTestAction
{
    public function __invoke($id)
    {
        return $id;
    }
}

class UserFacade
{
}

class LumenTestTerminateMiddleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }

    public function terminate($request, Illuminate\Http\Response $response)
    {
        $response->setContent('TERMINATED');
    }
}

class ResponsableResponse implements \Illuminate\Contracts\Support\Responsable
{
    public function toResponse($request)
    {
        return $request->route('foo');
    }
}
