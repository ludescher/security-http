<?php

namespace Symfony\Component\Security\Http\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * The RememberMe *listener* creates and deletes remember me cookies.
 *
 * Upon login success or failure and support for remember me
 * in the firewall and authenticator, this listener will create
 * a remember me cookie.
 * Upon login failure, all remember me cookies are removed.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.1
 */
class RememberMeListener implements EventSubscriberInterface
{
    private $rememberMeServices;
    private $providerKey;
    private $logger;

    public function __construct(RememberMeServicesInterface $rememberMeServices, string $providerKey, ?LoggerInterface $logger = null)
    {
        $this->rememberMeServices = $rememberMeServices;
        $this->providerKey = $providerKey;
        $this->logger = $logger;
    }


    public function onSuccessfulLogin(LoginSuccessEvent $event): void
    {
        if (!$this->isRememberMeEnabled($event->getAuthenticator(), $event->getProviderKey())) {
            return;
        }

        $this->rememberMeServices->loginSuccess($event->getRequest(), $event->getResponse(), $event->getAuthenticatedToken());
    }

    public function onFailedLogin(LoginFailureEvent $event): void
    {
        if (!$this->isRememberMeEnabled($event->getAuthenticator(), $event->getProviderKey())) {
            return;
        }

        $this->rememberMeServices->loginFail($event->getRequest(), $event->getException());
    }

    private function isRememberMeEnabled(AuthenticatorInterface $authenticator, string $providerKey): bool
    {
        if ($providerKey !== $this->providerKey) {
            // This listener is created for a different firewall.
            return false;
        }

        if (!$authenticator instanceof RememberMeAuthenticatorInterface || !$authenticator->supportsRememberMe()) {
            if (null !== $this->logger) {
                $this->logger->debug('Remember me skipped: your authenticator does not support it.', ['authenticator' => \get_class($authenticator)]);
            }

            return false;
        }

        return true;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onSuccessfulLogin',
            LoginFailureEvent::class => 'onFailedLogin',
        ];
    }
}