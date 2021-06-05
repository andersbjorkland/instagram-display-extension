<?php

namespace AndersBjorkland\InstagramDisplayExtension\Entity;

use AndersBjorkland\InstagramDisplayExtension\Repository\InstagramTokenRepository;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=InstagramTokenRepository::class)
 */
class InstagramToken
{
    //public const TYPE = 'instagram-token';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $token;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $expiresIn;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $instagramUserId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getExpiresIn(): ?DateTimeInterface
    {
        return $this->expiresIn;
    }

    public function setExpiresIn(?int $expiresIn): self
    {
        $interval = new DateInterval('PT' . $expiresIn . 'S');

        $date = new DateTime();
        $date->add($interval); // adds seconds to current time.

        $this->expiresIn = $date;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getInstagramUserId()
    {
        return $this->instagramUserId;
    }

    /**
     * @param mixed $instagramUserId
     */
    public function setInstagramUserId($instagramUserId): void
    {
        $this->instagramUserId = $instagramUserId;
    }
}
