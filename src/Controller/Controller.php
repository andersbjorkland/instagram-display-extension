<?php

declare(strict_types=1);

namespace AndersBjorkland\InstagramDisplayExtension\Controller;

use AndersBjorkland\InstagramDisplayExtension\Entity\InstagramMedia;
use AndersBjorkland\InstagramDisplayExtension\Entity\InstagramToken;
use AndersBjorkland\InstagramDisplayExtension\Extension;
use AndersBjorkland\InstagramDisplayExtension\Service\FileUploader;
use Bolt\Configuration\Config;
use Bolt\Extension\ExtensionController;
use Bolt\Extension\ExtensionRegistry;
use Bolt\Utils\ThumbnailHelper;
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
     * @var InstagramToken|object|null
     */
    protected $token;

    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;


    protected $registry;

    /**
     * Controller constructor.
     */
    public function __construct(Config $config, HttpClientInterface $client, EntityManagerInterface $entityManager, ExtensionRegistry $registry)
    {
        parent::__construct($config);
        $this->client = $client;
        $this->entityManager = $entityManager;
        $this->registry = $registry;

        $token = null;
        $tokens = $entityManager->getRepository(InstagramToken::class)->findAll();
        if (count($tokens) > 0) {
            $token = $tokens[0];
        }
        $this->token = $token;
    }


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

        $instagramToken = null;

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
        } else {
            $this->addFlash('error', 'Something went wrong. Check that you have correct Instagram App ID and Secret entered as environment variables INSTAGRAM_APP_ID and INSTAGRAM_APP_SECRET.');
        }

        return $this->redirectToRoute('bolt_dashboard');
    }

    public function getMediaContent(Request $request, EntityManagerInterface $entityManager): array
    {
        $parameters = $request->query->all();

        $cursorRequest = "";

        $allowedQueryParameters = ["direction", "cursor"];
        $allowedDirectionValues = ["before", "after"];
        $parametersAreCorrect = true;

        if (count($parameters) > 0) {
            foreach($allowedQueryParameters as $allowedParameter) {
                if (!array_key_exists($allowedParameter, $parameters)) {
                    $parametersAreCorrect = false;
                }
            }
        } else {
            $parametersAreCorrect = false;
        }

        if ($parametersAreCorrect) {
            $direction = $parameters["direction"];
            $cursor = $parameters["cursor"];

            $cursorRequest = "$direction=$cursor";
        }



        $media = false;
        $mediaEntities = [];
        $context = [];

        // Fetch instagram media
        try {
            $response = $this->getMediaPosts($cursorRequest)->toArray();

            /**
             * @var Extension
             */
            $extension = $this->registry->getExtension(Extension::class);
            $configs = $extension->getConfig();

            if (array_key_exists("data", $response)) {
                $media = [];
                $responseData = $response["data"];
                foreach ($responseData as $dataElement) {
                    array_push($media, $dataElement);
                }
            }

            if (array_key_exists("paging", $response)) {
                $paging = $response["paging"];
                $pagingResult = [];

                if (array_key_exists("cursors", $paging)) {
                    if (array_key_exists("before", $paging["cursors"]) && array_key_exists("previous", $paging)) {
                        $pagingResult["previous"] = $paging["cursors"]["before"];
                    }

                    if (array_key_exists("after", $paging["cursors"]) && array_key_exists("next", $paging)) {
                        $pagingResult["next"] = $paging["cursors"]["after"];
                    }
                }

                if (count($pagingResult) > 0) {
                    $context["paging"] = $pagingResult;
                }
            }


            $allowVideo = $configs["allow_video"];
            $storeVideo = $configs["store_video"];
            $fileUploader = new FileUploader($configs);


            // Store media
            if (count($media) > 0) {
                $hasDatabaseTransaction = false;
                foreach ($media as $mediaElement) {

                    // Check if $media has already been stored.
                    $instagramMedia = $entityManager->getRepository(InstagramMedia::class)
                        ->findOneBy(["instagramId" => $mediaElement["id"]]);

                    if (
                        $instagramMedia === null
                        || ($instagramMedia->getFilepath() && !file_exists($instagramMedia->getFilepath()))
                    ) {
                        if (strcmp(strtolower($mediaElement["media_type"]), "video") !== 0 || $allowVideo) {

                            $filePath = false;
                            if (strcmp(strtolower($mediaElement["media_type"]), "video") !== 0 || $storeVideo) {
                                $filePath = $fileUploader->upload($mediaElement);
                            }

                            $instagramMedia = $instagramMedia ?? InstagramMedia::createFromArray($mediaElement);

                            if ($filePath !== false) {
                                $instagramMedia->setFilepath($filePath);
                            }

                            $entityManager->persist($instagramMedia);
                            if (!$hasDatabaseTransaction) {
                                $hasDatabaseTransaction = true;
                            }
                        }
                    } else {
                        $tempMedia = InstagramMedia::createFromArray($mediaElement);
                        if (strcmp($instagramMedia->getInstagramUrl(), $tempMedia->getInstagramUrl()) !== 0) {
                            $instagramMedia->updateInstagramMedia($tempMedia);
                            $entityManager->persist($instagramMedia);
                        }
                    }

                    if ($instagramMedia !== null) {
                        if (strcmp(strtolower($instagramMedia->getMediaType()), "video") !== 0 || $allowVideo) {
                            array_push($mediaEntities, $instagramMedia);
                        }
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

        $context["title"] = "Instagram Media Display";
        $context["media"] = $mediaEntities;
        $context["message"] = "Token is fetched.";
        $context["response"] = $response;

        return $context;
    }

    /**
     * @Route("/extensions/instagram-display/media/async", name="instagram_media_async")
     */
    public function getAsyncMedia(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {

        $context = $this->getMediaContent($request, $entityManager);

        $parsedResponse = [];

        if (array_key_exists("media", $context)) {
            $mediaItems = $context["media"];
            $media = [];

            /**
             * @var Extension
             */
            $extension = $this->registry->getExtension(Extension::class);
            $configs = $extension->getConfig();
            $thumbnailWidth = $configs->get('thumbnail_width');
            $thumbnailHeight = $configs->get('thumbnail_height');

            $thumbnailWidth = strcmp(strtolower("" . $thumbnailWidth), "null") === 0 ? null : $thumbnailWidth;
            $thumbnailHeight = strcmp(strtolower("" . $thumbnailHeight), "null") === 0 ? null : $thumbnailHeight;

            $cropping = 'c';
            if ($thumbnailWidth === null || $thumbnailHeight === null) {
                $cropping = null;
            }

            $parsedResponse["video_width"] = $thumbnailWidth;
            $parsedResponse["video_height"] = $thumbnailHeight;
            
            
            $parsedResponse["icon_color"] = $configs->get('icon_color');
            $parsedResponse["overlay_color"] = $configs->get('overlay_color');
            $parsedResponse["link_color"] = $configs->get('link_color');
            $parsedResponse["default_style"] = $configs->get('default_style');

            $showFollow = $configs->get('show_follow_on_instagram');

            $instagramUsername = null;

            foreach ($mediaItems as $mediaEntity) {
                if ($mediaEntity instanceof InstagramMedia) {

                    if ($instagramUsername === null && $mediaEntity->getInstagramUsername() && $showFollow) {
                        $followClassname = $configs->get('follow_classname');
                        $instagramUsername = $mediaEntity->getInstagramUsername();
                        $parsedResponse["instagram_follow"] = [
                            "url" => "https://www.instagram.com/$instagramUsername/", 
                            "username" => $instagramUsername,
                            "classname" => $followClassname
                        ];
                    }

                    
                    if ($thumbnailWidth === null && $thumbnailHeight === null ) {
                        $thumbnailPath = $mediaEntity->getFilepath();
                    } else {
                        $thumbnailPath = (new ThumbnailHelper($this->getBoltConfig()))->path(str_replace("files/", "", $mediaEntity->getFilepath()), $thumbnailWidth, $thumbnailHeight, null, null, $cropping);
                    }

                    $mediaContent = [
                        "media_type" => $mediaEntity->getMediaType(),
                        "filepath" => $mediaEntity->getFilepath(),
                        "caption" => $mediaEntity->getCaption(),
                        "instagram_url" => $mediaEntity->getInstagramUrl(),
                        "instagram_username" => $mediaEntity->getInstagramUsername(),
                        "permalink" => $mediaEntity->getPermalink(),
                        "thumbnail" => $thumbnailPath
                    ];
                    array_push($media, $mediaContent);
                }
            }

            if (count($media) > 0) {
                $parsedResponse["media"] = $media;
            }

        }

        if (array_key_exists("paging", $context)) {
            if ($configs->get('show_pagination_controls')) {
                $parsedResponse["paging"] = $context["paging"];
            }
        }

        return new JsonResponse($parsedResponse);
    }

    /**
     * @Route("/extensions/instagram-display/media", name="instagram_media")
     */
    public function getMedia(Request $request, EntityManagerInterface $entityManager): Response
    {

        $context = $this->getMediaContent($request, $entityManager);

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
    protected function getMediaPosts(string $cursor = null): ResponseInterface
    {
        $instagramToken = $this->token;
        $client = $this->client;

        $instagramUserId = $instagramToken->getInstagramUserId();
        $token = $instagramToken->getToken();

        /**
         * @var Extension
         */
        $extension = $this->registry->getExtension(Extension::class);
        $configs = $extension->getConfig();
        $limit = $configs->get('results_per_page');

        $cursorQuery = '';
        if ($cursor !== null) {
            $cursorQuery = "&$cursor";
        }

        $fields = "id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username";

        $url = "https://graph.instagram.com/$instagramUserId"
            ."/media"
            ."?access_token=$token"
            ."&limit=$limit"
            ."&fields=$fields"
            . $cursorQuery
        ;

        return $client->request('GET', $url);
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function getMediaPost(string $mediaId): ResponseInterface
    {


        $token = $this->token->getToken();
        $url = "https://graph.instagram.com/$mediaId"
            ."?fields=media_type,media_url,caption,timestamp,username"
            ."&access_token=$token";

        return $this->client->request('GET', $url);
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function getInstagramUsername(): ResponseInterface
    {


        $token = $this->token->getToken();
        $url = "https://graph.instagram.com/me"
            ."?fields=username"
            ."&access_token=$token";

        return $this->client->request('GET', $url);
    }
}
