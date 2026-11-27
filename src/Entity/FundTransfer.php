<?php

namespace App\Entity;

use App\Enum\FundTransferDestinationType;
use App\Enum\FundTransferStatus;
use App\Repository\FundTransferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FundTransferRepository::class)]
class FundTransfer
{

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = FundTransferStatus::Pending;
    }



    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'outgoingTransfers')]
    private ?Branch $branch_source = null;

    #[ORM\ManyToOne(inversedBy: 'incomingTransfers')]
    private ?Branch $branch_destination = null;

    #[ORM\Column(enumType: FundTransferDestinationType::class)]
    private ?FundTransferDestinationType $destinationType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bank_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bank_reference = null;

    #[ORM\Column(enumType: FundTransferStatus::class)]
    private ?FundTransferStatus $status = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $amount = null;

    #[ORM\ManyToOne(inversedBy: 'fundTransfers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    #[ORM\ManyToOne(inversedBy: 'outgoingTransfers')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(inversedBy: 'incomingTransfers')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $recievedBy = null;

    #[ORM\Column(length: 255)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $recievedAt = null;

    #[ORM\ManyToOne(inversedBy: 'fundTransfers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Agency $agency = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranchSource(): ?Branch
    {
        return $this->branch_source;
    }

    public function setBranchSource(?Branch $branch_source): static
    {
        $this->branch_source = $branch_source;

        return $this;
    }

    public function getBranchDestination(): ?Branch
    {
        return $this->branch_destination;
    }

    public function setBranchDestination(?Branch $branch_destination): static
    {
        $this->branch_destination = $branch_destination;

        return $this;
    }

    public function getDestinationType(): ?FundTransferDestinationType
    {
        return $this->destinationType;
    }

    public function setDestinationType(FundTransferDestinationType $destinationType): static
    {
        $this->destinationType = $destinationType;

        return $this;
    }

    public function getBankName(): ?string
    {
        return $this->bank_name;
    }

    public function setBankName(?string $bank_name): static
    {
        $this->bank_name = $bank_name;

        return $this;
    }

    public function getBankReference(): ?string
    {
        return $this->bank_reference;
    }

    public function setBankReference(?string $bank_reference): static
    {
        $this->bank_reference = $bank_reference;

        return $this;
    }

    public function getStatus(): ?FundTransferStatus
    {
        return $this->status;
    }

    public function setStatus(FundTransferStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }


    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getRecievedBy(): ?User
    {
        return $this->recievedBy;
    }

    public function setRecievedBy(?User $recievedBy): static
    {
        $this->recievedBy = $recievedBy;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRecievedAt(): ?\DateTimeImmutable
    {
        return $this->recievedAt;
    }

    public function setRecievedAt(?\DateTimeImmutable $recievedAt): static
    {
        $this->recievedAt = $recievedAt;

        return $this;
    }

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(?Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }
}
