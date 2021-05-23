<?php

declare(strict_types=1);

namespace AndersBjorkland\FacebookOauthExtension\Controller;

use Bolt\Extension\ExtensionController;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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

        $url = "https://www.facebook.com/v10.0/dialog/oauth?"
                  ."client_id=$appId"
                  ."&redirect_uri=$redirectUrl"
                  ."&state={st=initial}"
                  ."&scope=email";

        $state = "";
        $resultContext = [];

        if (count($params) === 0) {
            return $this->redirect($url);
        } else {
            // This is triggered when facebook login has redirected back to this controller.
            $stateQuery = str_replace(['{', '}'], '', $params["state"]);
            parse_str($stateQuery, $state);

            if ($params["code"] !== null && strlen($params["code"]) > 0) {
                $result = $this->verifyToken($params["code"], $client);

                try {
                    $resultContext = [
                        'statusCode' => $result->getStatusCode(),
                        'headers' => $result->getHeaders(),
                        'content' => $result->toArray(),
                        'exception' => false
                    ];
                } catch (Exception $e) {
                    $resultContext = [
                        'statusCode' => $e->getCode(),
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTrace()
                    ];
                }

                return $this->redirectToRoute('facebook_oauth_check', $resultContext);
            }

        }

        $context = [
            'title' => 'Facebook Oauth Extension',
            'state' => $state,
            'resultContext' => $resultContext
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
            'title' => 'Facebook Oauth Extension'
        ];

        return $this->render('@facebook-oauth-extension/page.html.twig', $context);
    }

    protected function verifyToken(string $code, HttpClientInterface $client): ResponseInterface
    {

        $appId = $this->getParameter('facebook-app-id');
        $redirectUrl = $this->generateUrl(
            $this->getParameter('facebook-redirect-url'),
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $appSecret = $this->getParameter('facebook-app-secret');

        $url = "https://graph.facebook.com/v10.0/oauth/access_token?"
                ."client_id=$appId"
                ."&redirect_uri=$redirectUrl"
                ."&client_secret=$appSecret"
                ."&code=$code";

        $response = $client->request(
            'GET',
            $url
        );
        return $response;
    }
}
