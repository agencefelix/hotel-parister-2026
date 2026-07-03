<?php

declare(strict_types=1);

namespace App\Service\Content;

use App\Entity\Core\Website;
use App\Model\Core\WebsiteModel;
use App\Service\Interface\CoreLocatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * RecaptchaService.
 *
 * Manage recaptcha security post
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
class RecaptchaService
{
    private const int MIN_FILL_SECONDS = 3;

    private ?Request $request;
    private Session $session;

    /**
     * RecaptchaService constructor.
     */
    public function __construct(
        private readonly CoreLocatorInterface $coreLocator,
        private readonly CryptService $cryptService,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly RateLimiterFactory $frontFormLimiter,
        private readonly string $logDir,
    ) {
        $this->request = $this->requestStack->getCurrentRequest();
        $this->session = new Session();
    }

    /**
     * Check if is valid POST.
     *
     * @throws \Exception|InvalidArgumentException
     */
    public function execute(Website $website, mixed $entity, FormInterface $form, ?string $email = null): bool
    {
        $post = filter_input_array(INPUT_POST)[$form->getName()];
        $formSecurityKey = $entity->getSecurityKey();
        $this->securityKeys($website);

        $logger = new Logger('SPAM');
        $logger->pushHandler(new RotatingFileHandler($this->logDir.'/spams.log', 10, Level::Info));

        /** Rate limit by IP: applies to all front form posts, even without recaptcha */
        $limiter = $this->frontFormLimiter->create($this->request?->getClientIp() ?: 'anonymous');
        if (!$limiter->consume()->isAccepted()) {
            $this->session->getFlashBag()->add('error_form', $this->translator->trans('Trop de tentatives. Veuillez patienter quelques instants et réessayer.', [], 'front_form'));
            $logger->alert('Rate limit exceeded. IP :'.$this->request?->getClientIp());

            return false;
        }

        if (!$entity->isRecaptcha()) {
            return true;
        }

        $websiteModel = WebsiteModel::fromEntity($website, $this->coreLocator);

        if (!empty($post['field_ho']) && empty($post['field_ho_entitled'])) {
            $honeyPost = $this->cryptService->execute($websiteModel, $post['field_ho'], 'd');
            if ($honeyPost && urldecode($honeyPost) == $formSecurityKey && $this->checkMinFillTime($websiteModel, $post, $logger)) {
                return true;
            }
        }

        $this->session->getFlashBag()->add('error_form', $this->translator->trans('Erreur de sécurité !! Rechargez la page et réessayez.', [], 'front_form'));

        if ($email) {
            $logger->alert('Recaptcha security. This email seems to be spam :'.$email);
        } else {
            $logger->alert('Recaptcha security. IP spam :'.$this->request?->getClientIp());
        }

        return false;
    }

    /**
     * Time-trap: reject forms submitted faster than a human can fill them.
     * The timestamp is encrypted server-side at render (RecaptchaType field_ho_time).
     */
    private function checkMinFillTime(WebsiteModel $websiteModel, array $post, Logger $logger): bool
    {
        if (empty($post['field_ho_time'])) {
            $logger->alert('Recaptcha security. Missing field_ho_time. IP :'.$this->request?->getClientIp());

            return false;
        }

        $decrypted = $this->cryptService->execute($websiteModel, (string) $post['field_ho_time'], 'd');
        $timestamp = $decrypted ? intval($decrypted) : 0;

        if ($timestamp <= 0 || (time() - $timestamp) < self::MIN_FILL_SECONDS) {
            $logger->alert('Recaptcha security. Form submitted too fast. IP :'.$this->request?->getClientIp());

            return false;
        }

        return true;
    }

    /**
     * Set security keys if not generated.
     *
     * @throws \Exception
     */
    private function securityKeys(Website $website): void
    {
        $flush = false;
        $api = $website->getApi();
        $securityKey = $api->getSecuritySecretKey();
        $securityIv = $api->getSecuritySecretIv();

        if (!$securityKey) {
            $key = base64_encode(uniqid().password_hash(uniqid(), PASSWORD_BCRYPT).random_bytes(10));
            $api->setSecuritySecretKey(substr(str_shuffle($key), 0, 45));
            $flush = true;
        }

        if (!$securityIv) {
            $key = base64_encode(uniqid().password_hash(uniqid(), PASSWORD_BCRYPT).random_bytes(10));
            $api->setSecuritySecretIv(substr(str_shuffle($key), 0, 45));
            $flush = true;
        }

        if ($flush) {
            $this->entityManager->persist($api);
            $this->entityManager->flush();
        }
    }
}
