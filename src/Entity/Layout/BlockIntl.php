<?php

declare(strict_types=1);

namespace App\Entity\Layout;

use App\Entity\BaseIntl;
use App\Entity\Layout\Page;
use App\Repository\Layout\BlockIntlRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * BlockIntl.
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[ORM\Table(name: 'layout_block_intls')]
#[ORM\Entity(repositoryClass: BlockIntlRepository::class)]
class BlockIntl extends BaseIntl
{
    #[ORM\ManyToOne(targetEntity: Block::class, cascade: ['persist'], inversedBy: 'intls')]
    #[ORM\JoinColumn(onDelete: 'cascade')]
    private ?Block $block = null;

    // Lien secondaire (facultatif) : second CTA sur le même block.
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $targetLinkSecondary = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $targetLabelSecondary = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $targetStyleSecondary = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $newTabSecondary = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $externalLinkSecondary = false;

    #[ORM\ManyToOne(targetEntity: Page::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Page $targetPageSecondary = null;

    public function getBlock(): ?Block
    {
        return $this->block;
    }

    public function setBlock(?Block $block): static
    {
        $this->block = $block;

        return $this;
    }

    public function getTargetLinkSecondary(): ?string
    {
        return $this->targetLinkSecondary;
    }

    public function setTargetLinkSecondary(?string $targetLinkSecondary): static
    {
        $this->targetLinkSecondary = $targetLinkSecondary;

        return $this;
    }

    public function getTargetLabelSecondary(): ?string
    {
        return $this->targetLabelSecondary;
    }

    public function setTargetLabelSecondary(?string $targetLabelSecondary): static
    {
        $this->targetLabelSecondary = $targetLabelSecondary;

        return $this;
    }

    public function getTargetStyleSecondary(): ?string
    {
        return $this->targetStyleSecondary;
    }

    public function setTargetStyleSecondary(?string $targetStyleSecondary): static
    {
        $this->targetStyleSecondary = $targetStyleSecondary;

        return $this;
    }

    public function isNewTabSecondary(): ?bool
    {
        return $this->newTabSecondary;
    }

    public function setNewTabSecondary(bool $newTabSecondary): static
    {
        $this->newTabSecondary = $newTabSecondary;

        return $this;
    }

    public function isExternalLinkSecondary(): ?bool
    {
        return $this->externalLinkSecondary;
    }

    public function setExternalLinkSecondary(bool $externalLinkSecondary): static
    {
        $this->externalLinkSecondary = $externalLinkSecondary;

        return $this;
    }

    public function getTargetPageSecondary(): ?Page
    {
        return $this->targetPageSecondary;
    }

    public function setTargetPageSecondary(?Page $targetPageSecondary): static
    {
        $this->targetPageSecondary = $targetPageSecondary;

        return $this;
    }
}
