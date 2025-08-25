<?php

namespace App\Service;

class TimeService
{
    private const DEFAULT_TIMEZONE = 'Indian/Antananarivo'; // Madagascar GMT+3
    
    /**
     * Crée un objet DateTimeImmutable avec le fuseau horaire par défaut
     */
    public function now(string $timezone = self::DEFAULT_TIMEZONE): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($timezone));
    }
    
    /**
     * Crée un objet DateTime avec le fuseau horaire par défaut
     */
    public function createDateTime(string $datetime = 'now', string $timezone = self::DEFAULT_TIMEZONE): \DateTime
    {
        return new \DateTime($datetime, new \DateTimeZone($timezone));
    }
    
    /**
     * Formate une date pour l'API avec le bon fuseau horaire
     */
    public function formatForApi(\DateTimeInterface $date, string $timezone = self::DEFAULT_TIMEZONE): string
    {
        if ($date instanceof \DateTimeImmutable) {
            $dateInTimezone = $date->setTimezone(new \DateTimeZone($timezone));
        } else {
            $dateInTimezone = clone $date;
            $dateInTimezone->setTimezone(new \DateTimeZone($timezone));
        }
        
        return $dateInTimezone->format(\DateTimeInterface::ATOM);
    }
    
    /**
     * Convertit une date UTC vers le fuseau horaire local
     */
    public function convertFromUtc(\DateTimeInterface $utcDate, string $timezone = self::DEFAULT_TIMEZONE): \DateTimeImmutable
    {
        if ($utcDate instanceof \DateTimeImmutable) {
            return $utcDate->setTimezone(new \DateTimeZone($timezone));
        }
        
        return \DateTimeImmutable::createFromInterface($utcDate)->setTimezone(new \DateTimeZone($timezone));
    }
}
