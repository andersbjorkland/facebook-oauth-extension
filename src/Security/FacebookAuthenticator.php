<?php

declare(strict_types=1);

namespace AndersBjorkland\FacebookOauthExtension\Security;

use Bolt\Entity\User;
use Bolt\Repository\UserAuthTokenRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use UAParser\Parser;

class FacebookAuthenticator extends AbstractGuardAuthenticator
{
    private $em;
    private $router;
    private $client;
    private $session;

    public function __construct(EntityManagerInterface $em, RouterInterface $router, HttpClientInterface $client, SessionInterface $session)
    {
        $this->em = $em;
        $this->router = $router;
        $this->client = $client;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function start(Request $request, ?AuthenticationException $authException = null)
    {
        return new RedirectResponse(
            // might be the site, where users choose their oauth provider
            $this->router->generate('facebook_oauth'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request)
    {
        return $request->attributes->get('_route') === 'facebook_oauth_check';
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(Request $request)
    {
        if ($request->query->get('content') !== null) {
            return $request->query->get('content');
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $params = $credentials;
        $token = $params["access_token"];

        $userVerificationUrl = "https://graph.facebook.com/me?fields=id,email&access_token=$token";

        $response = $this->client->request(
            'GET',
            $userVerificationUrl
        );

        $response = $response->toArray();
        $email = $response['email'];

        $this->session->set('fb_access_token', $token);
        $this->session->set('fb_user_id', $response['id']);

        return $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // Set the user entity up for successful bolt logout
        $user = $token->getUser();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->getUserAuthToken()) {
            $this->em->remove($user->getUserAuthToken());
            $this->em->flush();
        }

        $user->setLastseenAt(new DateTime());
        $user->setLastIp($request->getClientIp());

        /** @var Parser $uaParser */
        $uaParser = Parser::create();

        $parsedUserAgent = $uaParser->parse($request->headers->get('User-Agent'))->toString();
        $sessionLifetime = $request->getSession()->getMetadataBag()->getLifetime();
        $expirationTime = (new DateTime())->modify('+' . $sessionLifetime . ' second');
        $userAuthToken = UserAuthTokenRepository::factory($user, $parsedUserAgent, $expirationTime);
        $user->setUserAuthToken($userAuthToken);

        $this->em->persist($user);
        $this->em->flush();

        // change "app_homepage" to some route in your app
        $targetUrl = $this->router->generate('bolt_dashboard');

        return new RedirectResponse($targetUrl);

        // or, on success, let the request continue to be handled by the controller
        //return null;
    }


    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return true;
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }
}
