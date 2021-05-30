<?php

declare(strict_types=1);

namespace AndersBjorkland\InstagramDisplayExtension\Controller;

use AndersBjorkland\InstagramDisplayExtension\Entity\InstagramToken;
use Bolt\Extension\ExtensionController;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Controller extends ExtensionController
{

    /**
     * @Route("/extensions/instagram-display/", name="instagram_authorize")
     */
    public function authorizeApp(Request $request, HttpClientInterface $client, EntityManagerInterface $entityManager): Response
    {
        $appId = $this->getParameter('instagram-app-id');

        $params = $request->query->all();

        if (count($params) === 0) {
            return $this->authorizeUser();
        }

        $tokenCall = "";
        $userId = false;
        $token = false;

        if ($params['code'] !== null && mb_strlen($params['code']) > 0) {
            $code = $params['code'];

            try {
                $tokenCall = $this->getToken($code, $client)->toArray();
                $userId = $tokenCall["user_id"];
                $token = $tokenCall["access_token"];
            } catch (TransportExceptionInterface $e) {
                $tokenCall = ["exception" => $e->getTrace()];
            } catch (ClientExceptionInterface | ServerExceptionInterface | RedirectionExceptionInterface | DecodingExceptionInterface $e) {
                $tokenCall = ["exception" => $e->getTrace(), "explanation" => "Something probably went wrong converting the response to an array."];
            } catch (Exception $e) {
                $tokenCall = ["exception" => $e->getTrace(), "explanation" => "Something is probably off with the response from the api. We may need to update it."];
            }
        }





        $longlastingTokenResponse = false;
        $longlastingToken = false;
        $tokenExpiration = false;

        if ($token !== false) {
            try {
                $longlastingTokenResponse = $this->getLonglastingToken($token, $client)->toArray();
                $longlastingToken = $longlastingTokenResponse['access_token'];
                $tokenExpiration = $longlastingTokenResponse['expires_in'];
            } catch (TransportExceptionInterface $e) {
                $longlastingTokenResponse = [
                    'error' => true,
                    'trace' => $e->getTrace(),
                    'message' => 'Something went wrong when requesting a token.'
                ];
            } catch (ClientExceptionInterface | DecodingExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface $e) {
                $longlastingTokenResponse = [
                    'error' => true,
                    'trace' => $e->getTrace(),
                    'message' => 'Something went wrong when converting response to array.'
                ];
            }
        }

        $instagramToken = false;
        if ($longlastingToken !== false && $tokenExpiration !== false) {
            $instagramToken = new InstagramToken();
            $repository = $this->getDoctrine()->getRepository(InstagramToken::class);
            $tokens = $repository->findAll();

            if (count($tokens) > 0) {
                $instagramToken = $tokens[0];
            }

            $instagramToken->setToken($longlastingToken);
            $instagramToken->setExpiresIn($tokenExpiration);

            $entityManager->persist($instagramToken);
            $entityManager->flush();
        }


        $context = [
            'title' => 'Instagram Display Extension',
            'app_id' => $appId,
            'token' => $token,
            'tokenCall' => $tokenCall,
            'longCall' => $longlastingTokenResponse,
            'tokenEntity' => $instagramToken
        ];

        return $this->render('@instagram-display-extension/page.html.twig', $context);
    }

    protected function authorizeUser(): RedirectResponse
    {
        $appId = $this->getParameter('instagram-app-id');
        $redirectUrl = $this->generateUrl(
            'instagram_authorize',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );


        $url = "https://api.instagram.com/oauth/authorize"
          ."?client_id=$appId"
          ."&redirect_uri=$redirectUrl"
          ."&scope=user_profile"
          ."&response_type=code"
          ."&state=initial";

        return $this->redirect($url);
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function getToken(string $code, HttpClientInterface $client): ResponseInterface
    {
        $appId = $this->getParameter('instagram-app-id');
        $appSecret = $this->getParameter('instagram-app-secret');

        $redirectUrl = $this->generateUrl(
            'instagram_authorize',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $url = "https://api.instagram.com/oauth/access_token";
        $data = [
            "client_id" => $appId,
            "client_secret" => $appSecret,
            "code" => $code,
            "grant_type" => "authorization_code",
            "redirect_uri" => $redirectUrl
        ];

        return $client->request('POST', $url, [
            'body' => $data
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function getLonglastingToken(string $token, HttpClientInterface $client): ResponseInterface
    {
        $appSecret = $this->getParameter('instagram-app-secret');

        $url = "https://graph.instagram.com/access_token"
                  ."?grant_type=ig_exchange_token"
                  ."&client_secret=$appSecret"
                  ."&access_token=$token";

        return $client->request('GET', $url);
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function refreshLonglastingToken(string $token, HttpClientInterface $client): ResponseInterface
    {
        $url = "https://graph.instagram.com/refresh_access_token"
                  ."?grant_type=ig_refresh_token"
                  ."&access_token=$token";

        return $client->request('GET', $url);
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function getEmbeddedPost(HttpClientInterface $client): ResponseInterface
    {
        $token = $this->getParameter('instagram-user-token');

        $url = "https://graph.facebook.com/me"
            ."?fields=media"
            ."&access_token=$token";

        return $client->request('GET', $url);
    }
}
