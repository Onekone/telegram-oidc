<?php

namespace Onekone\TelegramSocialite;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    /**
     * Unique Provider Identifier.
     */
    public const IDENTIFIER = 'TELEGRAM';

    protected $usesPKCE = true;

    protected $scopeSeparator = ' ';

    protected $scopes = [
        'openid',
        'profile'
    ];

    protected function getAuthUrl($state)
    {
        return $this->getConfig('base_url','https://oauth.telegram.org')."/auth?".http_build_query($this->getCodeFields($state));
    }

    protected function getTokenUrl()
    {
        return $this->getConfig('base_url','https://oauth.telegram.org')."/token";
    }

    protected function getUserByToken($token)
    {
        return $this->getConfig('base_url','https://oauth.telegram.org')."/token";
    }

    public static function additionalConfigKeys()
    {
        return [
            'scopes',
            'base_url'
        ];
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['id'] ?? null,
            'nickname' => $user['preferred_username'] ?? null,
            'name'     => $user['name'] ?? null,
            'email'    => null,
            'avatar'   => $user['picture'] ?? null,
        ]);
    }

    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        if (!$code = $this->request->input('code')) {
            throw new \InvalidArgumentException('Request lacks query param `code`');
        }

        $tokenResponse = $this->getAccessTokenResponse($code);

        if ($tokenResponse['error'] ?? false) {
            throw new \InvalidArgumentException('Response from Telegram says there\'s an error: '.$tokenResponse['error']);
        }

        if (!($idToken = $tokenResponse['id_token'] ?? false)) {
            throw new \InvalidArgumentException('Response from Telegram lacks JWT Token');
        }

        // Decrypt JWT token
        $payload = $this->decodeJWT(
            $idToken,
            $code
        );

        $this->user = $this->mapUserToObject((array)$payload);

        return $this->user->setToken($tokenResponse['access_token'])
            ->setExpiresIn($tokenResponse['expires_in']);
    }

    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => ['Accept' => 'application/json'],
            RequestOptions::AUTH => [
                $this->clientId,
                $this->clientSecret,
            ],
            RequestOptions::FORM_PARAMS => array_merge(
                $this->getTokenFields($code),
                [
                    'grant_type' => 'authorization_code',
                ]
            ),
        ]);

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    protected function decodeJWT($jwt)
    {
        try {
            [$jwt_header, $jwt_payload, $jwt_signature] = explode(".", $jwt);
            $payload = json_decode($this->base64url_decode($jwt_payload));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('JWT: Failed to parse.', 401);
        }

        if ($payload->iss != "https://oauth.telegram.org") {
            throw new \InvalidArgumentException('JWT: Token is issued by wrong domain');
        }
        $t = time();
        if ($payload->exp < $t) {
            throw new \InvalidArgumentException('JWT: Token has expired. Was until '.$payload->exp.', currently '.$t.' (off by '.$t-$payload->exp.'s.)');
        }
        if ($payload->aud > $this->clientId) {
            throw new \InvalidArgumentException('JWT: Token is issued for wrong bot');
        }

        return $payload;
    }

    protected function getCodeVerifier()
    {
        return Str::random(96);
    }
}