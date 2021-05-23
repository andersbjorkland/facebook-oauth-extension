<?php

declare(strict_types=1);

namespace AndersBjorkland\FacebookOauthExtension\Controller;

use Bolt\Extension\ExtensionController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class Controller extends ExtensionController
{
    /**
     * @Route("/extensions/facebook-oauth", name="facebook_oauth")
     */
    public function index(Request $request, HttpClientInterface $client): Response
    {
        $params = $request->query->all();

        $appId = $this->getParameter('facebook-app-id');
        $redirectUrl = $this->generateUrl(
            'facebook_oauth',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $url = 'https://www.facebook.com/v10.0/dialog/oauth?'
                  . "client_id=${appId}"
                  . "&redirect_uri=${redirectUrl}"
                  . '&state={st=initial}'
                  . '&scope=email';

        $state = '';
        $resultContext = [];

        if (count($params) === 0) {
            return $this->redirect($url);
        }
        // This is triggered when facebook login has redirected back to this controller.
        $stateQuery = str_replace(['{', '}'], '', $params['state']);
        parse_str($stateQuery, $state);

        if ($params['code'] !== null && mb_strlen($params['code']) > 0) {

            try {
                $result = $this->verifyToken($params['code'], $client);
                $resultContext = [
                    'statusCode' => $result->getStatusCode(),
                    'headers' => $result->getHeaders(),
                    'content' => $result->toArray(),
                    'exception' => false,
                ];
            } catch (Throwable $e) {
                $resultContext = [
                    'statusCode' => $e->getCode(),
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ];
            }

            return $this->redirectToRoute('facebook_oauth_check', $resultContext);
        }

        $context = [
            'title' => 'Facebook Oauth Extension',
            'state' => $state,
            'resultContext' => $resultContext,
        ];

        return $this->render('@facebook-oauth-extension/page.html.twig', $context);
    }

    /**
     * @Route("/extensions/facebook-oauth/check", name="facebook_oauth_check")
     */
    public function check(): Response
    {
        // Dummy content if the FacebookAuthenticator is activated.

        $context = [
            'title' => 'Facebook Oauth Extension',
        ];

        return $this->render('@facebook-oauth-extension/page.html.twig', $context);
    }

    /**
     * @Route("/extensions/facebook-oauth/revoke", name="facebook_oauth_revoke")
     */
    public function revokeLogin(HttpClientInterface $client, SessionInterface $session): RedirectResponse
    {
        try {
            $token = $session->get('fb_access_token');
            $userId = $session->get('fb_user_id');
            $result = $client->request('DELETE', "https://graph.facebook.com/v10.0/${userId}/permissions?access_token=${token}");
            if ($result) {
                $session->remove('fb_access_token');
                $session->remove('fb_user_id');
                $this->addFlash('notice', 'You Facebook authentication was successfully revoked.');
            } else {
                $this->addFlash('notice', 'You Facebook authentication was not revoked.');
            }
        } catch (Throwable $e) {
            $this->addFlash('warning', 'Something went wrong revoking Facebook access.');
        }

        return $this->redirectToRoute('bolt_dashboard');
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function verifyToken(string $code, HttpClientInterface $client): ResponseInterface
    {
        $appId = $this->getParameter('facebook-app-id');
        $redirectUrl = $this->generateUrl(
            $this->getParameter('facebook-redirect-url'),
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $appSecret = $this->getParameter('facebook-app-secret');

        $url = 'https://graph.facebook.com/v10.0/oauth/access_token?'
                . "client_id=${appId}"
                . "&redirect_uri=${redirectUrl}"
                . "&client_secret=${appSecret}"
                . "&code=${code}";


        return $client->request(
            'GET',
            $url
        );

    }
}
