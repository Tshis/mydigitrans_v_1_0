<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CurrencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ORM\Table(name: 'currency')]
class Currency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, FundTransfer>
     */
    #[ORM\OneToMany(targetEntity: FundTransfer::class, mappedBy: 'currency')]
    private Collection $fundTransfers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->fundTransfers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, FundTransfer>
     */
    public function getFundTransfers(): Collection
    {
        return $this->fundTransfers;
    }

    public function addFundTransfer(FundTransfer $fundTransfer): static
    {
        if (!$this->fundTransfers->contains($fundTransfer)) {
            $this->fundTransfers->add($fundTransfer);
            $fundTransfer->setCurrency($this);
        }

        return $this;
    }

    public function removeFundTransfer(FundTransfer $fundTransfer): static
    {
        if ($this->fundTransfers->removeElement($fundTransfer)) {
            // set the owning side to null (unless already changed)
            if ($fundTransfer->getCurrency() === $this) {
                $fundTransfer->setCurrency(null);
            }
        }

        return $this;
    }
}
