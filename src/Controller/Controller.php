<?php

declare(strict_types=1);

namespace AndersBjorkland\InstagramDisplayExtension\Controller;

use AndersBjorkland\InstagramDisplayExtension\Entity\InstagramMedia;
use AndersBjorkland\InstagramDisplayExtension\Entity\InstagramToken;
use AndersBjorkland\InstagramDisplayExtension\Extension;
use AndersBjorkland\InstagramDisplayExtension\Service\FileUploader;
use Bolt\Extension\ExtensionController;
use Bolt\Extension\ExtensionRegistry;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $params = $request->query->all();

        if (count($params) === 0) {
            return $this->authorizeUser();
        }

        $userId = false;
        $token = false;

        if ($params['code'] !== null && mb_strlen($params['code']) > 0) {
            $code = $params['code'];

            try {
                $tokenCall = $this->getToken($code, $client)->toArray();
                $userId = $tokenCall["user_id"];
                $token = $tokenCall["access_token"];
            } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface | RedirectionExceptionInterface | DecodingExceptionInterface | Exception $e) {
            }
        }


        $longlastingToken = false;
        $tokenExpiration = false;

        if ($token !== false) {
            try {
                $longlastingTokenResponse = $this->getLonglastingToken($token, $client)->toArray();
                $longlastingToken = $longlastingTokenResponse['access_token'];
                $tokenExpiration = $longlastingTokenResponse['expires_in'];
            } catch (TransportExceptionInterface $e) {
                $this->addFlash('notice', 'Something went wrong when requesting a token.');
            } catch (ClientExceptionInterface | DecodingExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface $e) {
                $this->addFlash('notice', 'Something went wrong when converting response to array.');
            }
        }

        if ($longlastingToken !== false && $tokenExpiration !== false) {
            $instagramToken = new InstagramToken();
            $repository = $this->getDoctrine()->getRepository(InstagramToken::class);
            $tokens = $repository->findAll();

            if (count($tokens) > 0) {
                $instagramToken = $tokens[0];
            }

            $instagramToken->setToken($longlastingToken);
            $instagramToken->setExpiresIn($tokenExpiration);

            if ($userId !== false) {
                $instagramToken->setInstagramUserId($userId);
            }

            $entityManager->persist($instagramToken);
            $entityManager->flush();

            $this->addFlash('notice', 'Successfully authorized your website to use your Instagram account.');
        }

        return $this->redirectToRoute('bolt_dashboard');
    }

    /**
     * @Route("/extensions/instagram-display/media", name="instagram_media")
     */
    public function getMedia(HttpClientInterface $client, EntityManagerInterface $entityManager, ExtensionRegistry $registry): Response
    {
        $repository = $this->getDoctrine()->getRepository(InstagramToken::class);
        $tokens = $repository->findAll();

        if (count($tokens) > 0) {
            $instagramToken = $tokens[0];
            $media = false;
            $mediaPaths = [];

            // Fetch instagram media
            try {
                $response = $this->getMediaPosts($instagramToken, $client)->toArray();

                $configs = $registry->getExtension(Extension::class)->getConfig();

                if (array_key_exists("data", $response)) {
                    $media = [];
                    $responseData = $response["data"];
                    foreach ($responseData as $dataElement) {
                        array_push($media, $this->getMediaPost($dataElement["id"], $instagramToken, $client)->toArray());
                    }
                }


                $allowVideo = $configs["allow_video"];
                $fileUploader = new FileUploader($registry);


                // Store media
                if (count($media) > 0) {
                    $hasDatabaseTransaction = false;
                    foreach ($media as $mediaElement) {

                        // Check if $media has already been stored.
                        $instagramMedia = $entityManager->getRepository(InstagramMedia::class)
                            ->findOneBy(["instagramId" => $mediaElement["id"]]);

                        if (
                            $instagramMedia === null
                            || ($instagramMedia !== null && !file_exists($instagramMedia->getFilepath()))
                        ) {
                            if (strcmp(strtolower($mediaElement["media_type"]), "video") !== 0 || $allowVideo) {
                                $filePath = $fileUploader->upload($mediaElement);

                                if ($filePath !== false) {
                                    $instagramMedia ?? InstagramMedia::createFromArray($mediaElement);
                                    $instagramMedia->setFilepath($filePath);

                                    $entityManager->persist($instagramMedia);
                                    if (!$hasDatabaseTransaction) {
                                        $hasDatabaseTransaction = true;
                                    }
                                }
                            }
                        }

                        if ($instagramMedia !== null) {
                            array_push($mediaPaths, $instagramMedia->getFilepath());
                            dump([
                                "Media" => $instagramMedia,
                                "Path" => $instagramMedia->getFilepath(),
                                "Exists" => file_exists($instagramMedia->getFilepath())
                            ]);
                        }
                    }
                    $entityManager->flush();
                }

            } catch (TransportExceptionInterface | Exception $e) {
                $response = [
                    "message" => "Something went wrong fetching media posts",
                    "trace" => $e->getTrace()
                ];
            }

            $context = [
                "title" => "Instagram Media Display",
                "media" => $media,
                "message" => "Token is fetched.",
                "response" => $response
            ];
        } else {
            $context = [
                "title" => "Instagram Media Display",
                "media" => false,
                "message" => "No token was stored. Authorize your app."
            ];
        }

        return $this->render('@instagram-display-extension/page.html.twig', $context);
    }

    /**
     * @Route("/extensions/instagram-display/refresh", name="instagram_refresh")
     */
    public function refreshToken(HttpClientInterface $client, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $instagramTokens = $entityManager->getRepository(InstagramToken::class)->findAll();
        if (count($instagramTokens) > 0) {
            $instagramToken = $instagramTokens[0];
            try {
                $response = $this->refreshLonglastingToken($instagramToken->getToken(), $client)->toArray();
                if (key_exists("access_token", $response) && key_exists("expires_in", $response)) {
                    $instagramToken->setToken($response["access_token"]);
                    $instagramToken->setExpiresIn($response["expires_in"]);

                    $entityManager->persist($instagramToken);
                    $entityManager->flush();

                    $currentDate = new DateTime();
                    $interval = $currentDate->diff($instagramToken->getExpiresIn());

                    $response = [
                        "successful" => true,
                        "expiration_date" => $instagramToken->getExpiresIn(),
                        "days_left" => $interval->format('%r%a')
                    ];
                }
            } catch (ClientExceptionInterface
            | DecodingExceptionInterface | RedirectionExceptionInterface
            | ServerExceptionInterface | TransportExceptionInterface $e) {
                $response = [
                    "exception" => $e->getMessage(),
                    "status" => $e->getCode()
                ];
            }
        } else {
            $response = [
                "exception" => "No token is currently stored"
            ];
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/extensions/instagram-display/deauthorize", name="instagram_deauthorize")
     */
    public function deauthorize(EntityManagerInterface $entityManager)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $instagramTokens = $entityManager->getRepository(InstagramToken::class)->findAll();
        if (count($instagramTokens) > 0) {
            $instagramToken = $instagramTokens[0];
            try {
                $entityManager->remove($instagramToken);
                $entityManager->flush();

                $response = [
                    "successful" => true,
                ];

                $this->addFlash('notice', 'Instagram connection from this website has been removed.');

            } catch (ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface $e) {
                $response = [
                    "exception" => $e->getMessage(),
                    "status" => $e->getCode()
                ];
            }
        } else {
            $response = [
                "exception" => "No token is currently stored"
            ];
        }

        return new JsonResponse($response);
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
    protected function getMediaPosts(InstagramToken $instagramToken, HttpClientInterface $client): ResponseInterface
    {
        $instagramUserId = $instagramToken->getInstagramUserId();
        $token = $instagramToken->getToken();
        $url = "https://graph.instagram.com/$instagramUserId"
            ."/media"
            ."?access_token=$token";

        return $client->request('GET', $url);
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function getMediaPost(string $mediaId, InstagramToken $instagramToken, HttpClientInterface $client): ResponseInterface
    {
        $token = $instagramToken->getToken();
        $url = "https://graph.instagram.com/$mediaId"
            ."?fields=media_type,media_url,caption,timestamp,username"
            ."&access_token=$token";

        return $client->request('GET', $url);
    }
}
