<?php

declare(strict_types=1);

namespace App\Form\EventListener\Seo;

use App\Entity\Seo\Url;
use App\Form\EventListener\BaseListener;
use Symfony\Component\Form\FormEvent;

/**
 * UrlListener.
 *
 * Listen Url Form attribute
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
class UrlListener extends BaseListener
{
    /**
     * preSetData.
     */
    public function preSetData(FormEvent $event): void
    {
        $entity = $event->getData();
        foreach ($this->locales as $locale) {
            $exist = false;
            foreach ($entity->getUrls() as $url) {
                if ($url->getLocale() === $locale) {
                    $exist = true;
                }
            }
            if (!$exist && empty($entity->getId()) && $locale === $this->defaultLocale
                || !$exist && $entity->getId()) {
                $url = new Url();
                $url->setLocale($locale);
                /* Sans website l'URL est invisible des requêtes front (u.website = :website) :
                   la page devient introuvable dans cette locale. */
                $url->setWebsite($this->website->entity);
                $entity->addUrl($url);
            }
        }
    }
}
