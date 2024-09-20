<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Auth0Controller extends AbstractController
{
    public string $auth0_domain;
    public string $clientId;
    public string $clientSecret;

    public function __construct()
    {
        $this->auth0_domain = $_ENV["AUTH0_DOMAIN"];
        $this->clientId = $_ENV["AUTH0_CLIENT_ID"];
        $this->clientSecret = $_ENV["AUTH0_CLIENT_SECRET"];
    }

    #[Route('/', name: 'app_auth0_home')]
    public function index(LoggerInterface $logger): RedirectResponse
    {
        $authUrl = "https://$this->auth0_domain/authorize";
        $logger->info("AuthUrl: $authUrl");

        $scope = "openid profile email";
        $responseType = "code";
        $state = bin2hex(random_bytes(16));
        $callback = $this->generateUrl('app_auth0_callback', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        // Redirect user to Auth0 login page
        $target = "$authUrl?" .
            "response_type=$responseType&" .
            "client_id=$this->clientId&" .
            "redirect_uri=$callback&" .
            "scope=$scope&" .
            "state=$state";
        return new RedirectResponse($target);
    }

    /**
     */
    #[Route('/callback', name: 'app_auth0_callback')]
    public function callback(LoggerInterface $logger): JsonResponse
    {
        $logger->info("IN CALLBACK");
        // Not actually used ;)
        $redirectUri = $this->generateUrl('app_auth0_all_done', array(), UrlGeneratorInterface::ABSOLUTE_URL);
        try {
            $code = $_GET['code'];
        } catch (\Exception $e) {
            return new JsonResponse($_GET);
        }

        $tokenUrl = "https://$this->auth0_domain/oauth/token";
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($tokenUrl, false, $context);
        $responseData = json_decode($response, true);

        $idToken = $responseData['id_token'];
        $jwtParts = explode('.', $idToken);
        $claims_payload = self::urlsafeB64Decode($jwtParts[1]);
        $claims = json_decode($claims_payload, true);

        $data = [
            'auth0domain' => $this->auth0_domain,
        ];
        if ($claims) {
            $data['claims'] = $claims;
        } else {
            $data["idToken"] = $idToken;
            $data["claims_payload"] = $claims_payload;
        }

        return new JsonResponse($data);
    }

    #[Route('/logout', name: 'app_auth0_logout')]
    public function logout(LoggerInterface $logger): JsonResponse
    {
        $authUrl = "https://$this->auth0_domain/logout";
        $callback = $this->generateUrl('app_auth0_all_done', array(), UrlGeneratorInterface::ABSOLUTE_URL);
        // Redirect user to Auth0 login page
        $url = "$authUrl?" .
            "client_id=$this->clientId&" .
            "redirect_uri=$callback";
        $response = file_get_contents($url, false);
        return new JsonResponse(["all" => $response]);
    }

    #[Route('/logout', name: 'app_auth0_all_done')]
    public function allDone(LoggerInterface $logger): JsonResponse
    {
        return new JsonResponse(["all" => ""]);
    }

    public static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
