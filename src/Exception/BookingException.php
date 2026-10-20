<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception personnalisée pour centraliser et isoler les erreurs du tunnel de réservation 
 * (Gérée par le BookingService et interceptée par le LinesController).
 */
final class BookingException extends \Exception
{
    /**
     * Permet d'instancier une exception métier avec un message obligatoire.
     */
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
