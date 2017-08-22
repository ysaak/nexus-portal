<?php
define('APPDIR', realpath(__DIR__.'/../'));

require_once APPDIR.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Silex\Application;

use Lcobucci\JWT\Parser;

$app = new Application();

// Debug mode : ON
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => APPDIR.'/views',
));

function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

// Security
$app->before(function (Request $request, Application $app) {
    // ...
    $routeName = $request->get('_route');
    $exceptionRoutes = array('login', 'do-login');
    if (!in_array($routeName, $exceptionRoutes)) {
        // check cookie presence
        if(!$request->cookies->has("portal-token")) {
            return new RedirectResponse($app['url_generator']->generate('login'));
        }

        $token = $request->cookies->get("portal-token");
        if (startsWith($token, 'Bearer ')) {
            $token = substr($token, strlen('Bearer ')+1);
        }


        // Parse token
        $token = (new Parser())->parse((string) $token);

        $user = new stdClass;
        $user->login = $token->getClaim('sub');

        $app['user'] = $user;
        $app['twig']->addGlobal('user', $app['user']);
    }
    else {
        if($request->cookies->has("portal-token")) {
            return new RedirectResponse($app['url_generator']->generate('home'));
        }
    }
});


/*------------------------*/

$app->get('/', function (Request $request, Application $app) {
    //return 'Hello ' . $app['user.login'] . ' !!<br><br>home page<br><a href="'. $app['url_generator']->generate('logout') . '">logout</a>';
    return $app['twig']->render('home.twig', array());
})->bind('home');

$app->get('/login', function (Request $request, Application $app) {
    return $app['twig']->render('login.twig', array());
})->bind('login');

$app->post('/login', function (Request $request, Application $app) {

    $loginRequest = new stdClass;
    $loginRequest->login = "admin";
    $loginRequest->password = "password";

    $response = \Httpful\Request::post("http://localhost:8090/login")
        ->contentType('application/json')
        ->body(json_encode($loginRequest))
        ->send();

    if ($response->code == 200) {
        $cookie = new Cookie('portal-token', $response->headers['authorization']);
        $r = new RedirectResponse($app['url_generator']->generate('home'));
        $r->headers->setCookie($cookie);
        return $r;
    }
    else {
        var_dump($response);
        return "ERROR";
    }
})->bind('do-login');

$app->get('/logout', function (Request $request, Application $app) {
    $r = new RedirectResponse($app['url_generator']->generate('login'));
    $r->headers->clearCookie('portal-token');
    return $r;
})->bind('logout');

// ... definitions

$app->run();
