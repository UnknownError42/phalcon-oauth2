<?php

namespace App\Module\OAuth2\Repository;

use App\Module\OAuth2\Library\OAuthHelper;
use App\Module\OAuth2\Library\Utils;
use App\Module\OAuth2\Model\AccessToken;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

/**
 * Class AccessTokenRepository
 * @package App\Module\OAuth2\Repository
 */
class AccessTokenRepository extends Repository implements AccessTokenRepositoryInterface
{
    use Utils, OAuthHelper;

    /**
     * Model class name for the concrete implementation
     *
     * @return string
     */
    public function modelName() {
        return AccessToken::class;
    }

    /**
     * Create a new access token
     *
     * @param ClientEntityInterface                                 $clientEntity
     * @param \League\OAuth2\Server\Entities\ScopeEntityInterface[] $scopes
     * @param mixed                                                 $userIdentifier
     *
     * @return AccessTokenEntityInterface
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null) {
        $accessToken = new AccessToken();
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        $accessToken->setUserIdentifier($userIdentifier);
        $accessToken->setClient($clientEntity);
        return $accessToken;
    }

    /**
     * Persists a new access token to permanent storage.
     *
     * @param AccessTokenEntityInterface $accessTokenEntity
     *
     * @throws UniqueTokenIdentifierConstraintViolationException
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity) {
        $accessToken = $accessTokenEntity->getIdentifier();
        if ($this->findOne(['access_token' => $accessToken])) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }

        $this->create([
            'access_token' => $accessToken,
            'expires'      => $this->formatDateTime($accessTokenEntity->getExpiryDateTime()),
            'scope'        => implode(SCOPE_DELIMITER_STRING, $this->getScopeNamesFromAccessToken($accessTokenEntity)),
            'client_id'    => $accessTokenEntity->getClient()->getIdentifier(),
            'user_id'      => $accessTokenEntity->getUserIdentifier(),
            'revoked'      => 0,
        ]);
    }

    /**
     * Revoke an access token.
     *
     * @param string $tokenId
     */
    public function revokeAccessToken($tokenId) {
        $this->update(['access_token' => $tokenId], ['revoked' => 1]);
    }

    /**
     * Check if the access token has been revoked.
     *
     * @param string $tokenId
     *
     * @return bool Return true if this token has been revoked
     */
    public function isAccessTokenRevoked($tokenId) {
        if ($result = $this->findOne(['access_token' => $tokenId])) {
            return (int)$result->revoked === 1;
        }

        return true;
    }
}
