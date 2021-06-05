<?php

declare(strict_types=1);

namespace AndersBjorkland\InstagramDisplayExtension;

use AndersBjorkland\InstagramDisplayExtension\Entity\InstagramToken;
use Bolt\Widget\BaseWidget;
use Bolt\Widget\CacheAwareInterface;
use Bolt\Widget\CacheTrait;
use Bolt\Widget\Injector\AdditionalTarget;
use Bolt\Widget\Injector\RequestZone;
use Bolt\Widget\StopwatchAwareInterface;
use Bolt\Widget\StopwatchTrait;
use Bolt\Widget\TwigAwareInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class ReferenceWidget extends BaseWidget implements TwigAwareInterface, CacheAwareInterface, StopwatchAwareInterface
{
    use CacheTrait;
    use StopwatchTrait;

    protected $name;
    protected $target;
    protected $priority;
    protected $template;
    protected $zone;
    protected $cacheDuration;
    protected $entityManager;
    protected $instagramToken;

    /**
     * ReferenceWidget constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->name = 'AndersBjorkland InstagramDisplayExtension';
        $this->target = AdditionalTarget::WIDGET_BACK_DASHBOARD_ASIDE_TOP;
        $this->priority = 0;
        $this->template = '@instagram-display-extension/widget.html.twig';
        $this->zone = RequestZone::BACKEND;
        $this->cacheDuration = -1800;
        $this->entityManager = $entityManager;

        $repository = $this->entityManager->getRepository(InstagramToken::class);
        $tokenEntities = $repository->findAll();
        $tokenEntity = null;
        if (count($tokenEntities) > 0) {
            $tokenEntity = $tokenEntities[0];
        }
        $this->instagramToken = $tokenEntity;
    }

    public function getIsConnected(): bool
    {
        return $this->instagramToken !== null;
    }

    public function getInstagramToken(): InstagramToken
    {
        return $this->instagramToken;
    }

    public function getDaysLeft(): ?string
    {
        $currentDate = new DateTime();
        $interval = $currentDate->diff($this->instagramToken->getExpiresIn());

        return $interval->format('%r%a');
    }

    /**
     * Returns if token has expired. Will return true if no date is set.
     * @return bool
     */
    public function getHasExpired(): bool
    {
        $currentDate = new DateTime();
        if ($this->instagramToken === null) {
            return true;
        }
        return $currentDate > $this->instagramToken->getExpiresIn();
    }


}
