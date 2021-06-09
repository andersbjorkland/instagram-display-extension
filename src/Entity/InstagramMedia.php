<?php

namespace AndersBjorkland\InstagramDisplayExtension\Entity;

use AndersBjorkland\InstagramDisplayExtension\Exceptions\MissingArrayKeyException;
use AndersBjorkland\InstagramDisplayExtension\Repository\InstagramMediaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=InstagramMediaRepository::class)
 */
class InstagramMedia
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $instagramId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $mediaType;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $caption;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $timestamp;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $filepath;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $instagramUrl;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstagramId(): ?string
    {
        return $this->instagramId;
    }

    public function setInstagramId(string $instagramId): self
    {
        $this->instagramId = $instagramId;

        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->mediaType;
    }

    public function setMediaType(string $mediaType): self
    {
        $this->mediaType = $mediaType;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;

        return $this;
    }

    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }

    public function setTimestamp(string $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getFilepath(): ?string
    {
        return $this->filepath;
    }

    public function setFilepath(?string $filepath): self
    {
        $this->filepath = $filepath;

        return $this;
    }

    /**
     * createFormArray creates a InstagramMedia entity from an array with keys "id", "media_type", "caption", "timestamp".
     * This function is useful if you have a response array from api-endpoint containing theses keys.
     * @throws MissingArrayKeyException
     */
    public static function createFromArray(array $mediaArray): self
    {
        $requiredKeys = ["id", "media_type", "caption", "timestamp", "media_url"];

        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $mediaArray)) {
                throw new MissingArrayKeyException("Missing the array key: $requiredKey");
            }
        }
        $media = new InstagramMedia();
        $media
            ->setInstagramId($mediaArray["id"])
            ->setMediaType($mediaArray["media_type"])
            ->setCaption($mediaArray["caption"])
            ->setTimestamp($mediaArray["timestamp"])
            ->setInstagramUrl($mediaArray["media_url"])
        ;

        return $media;
    }

    /**
     * @return mixed
     */
    public function getInstagramUrl()
    {
        return $this->instagramUrl;
    }

    /**
     * @param mixed $instagramUrl
     */
    public function setInstagramUrl($instagramUrl): void
    {
        $this->instagramUrl = $instagramUrl;
    }
}
