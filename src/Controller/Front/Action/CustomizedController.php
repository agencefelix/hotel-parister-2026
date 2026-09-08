<?php

declare(strict_types=1);

namespace App\Controller\Front\Action;

use App\Controller\Front\FrontController;
use App\Entity\Layout\Block;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CustomizedController.
 *
 * Customized renders or actions
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[Route('/front/customized/action', schemes: '%protocol%')]
class CustomizedController extends FrontController
{
    /**
     * Booking D-Edge widget.
     *
     * @throws \Exception
     */
    public function booking(?Block $block = null): Response
    {
        $website = $this->getWebsite();
        $websiteTemplate = $website->configuration->template;

        return $this->render('front/'.$websiteTemplate.'/actions/customized/booking.html.twig', [
            'websiteTemplate' => $websiteTemplate,
            'website' => $website,
            'block' => $block,
        ]);
    }

    /**
     * Bons cadeaux : widget Bonkdo.
     *
     * @throws \Exception
     */
    public function gifts(?Block $block = null): Response
    {
        $website = $this->getWebsite();
        $websiteTemplate = $website->configuration->template;

        return $this->render('front/'.$websiteTemplate.'/actions/customized/gifts.html.twig', [
            'websiteTemplate' => $websiteTemplate,
            'website' => $website,
            'block' => $block,
        ]);
    }
}
