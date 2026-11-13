<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création, modification ou renouvellement d'un document légal d'un bus.
 */
final class BusDocumentData
{
    #[Assert\NotBlank(message: 'Sélectionnez le véhicule concerné.', groups: ['create'])]
    public ?string $busCode = null;

    #[Assert\NotBlank(message: 'Indiquez le type de document légal.', groups: ['create', 'edit'])]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le type est trop court.', maxMessage: 'Le type est trop long.', groups: ['create', 'edit'])]
    public ?string $type = null;

    #[Assert\Length(max: 100, maxMessage: 'La référence est trop longue (100 caractères maximum).')]
    public ?string $referenceNumber = null;

    #[Assert\NotNull(message: 'Indiquez la date d\'émission.')]
    public ?\DateTimeImmutable $issuedAt = null;

    #[Assert\NotNull(message: 'Indiquez la date d\'expiration.')]
    #[Assert\GreaterThan(
        propertyPath: 'issuedAt',
        message: 'La date d\'expiration doit être postérieure à la date d\'émission.'
    )]
    public ?\DateTimeImmutable $expiredAt = null;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        maxSizeMessage: 'Le fichier dépasse la taille maximale autorisée (5 Mo).',
        mimeTypesMessage: 'Format non autorisé. Seuls les fichiers PDF, JPG, PNG et WEBP sont acceptés.'
    )]
    public ?UploadedFile $documentFile = null;
}
