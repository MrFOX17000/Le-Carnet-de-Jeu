<?php

namespace App\Domain\Activity;

/**
 * Définit le mode de confrontation d'une activité.
 *
 * - ranking         : chaque participant a un score individuel (Skyjo, Catan, Mario Kart, Uno…)
 * - duel            : deux joueurs identifiés s'affrontent en 1v1 (Échecs, Tennis, Ping-pong…)
 * - duel_equipe     : deux équipes nommées de membres (Belote 2v2, Pétanque, Volley interne…)
 * - groupe_vs_ext   : le groupe forme une équipe contre des adversaires extérieurs (Valorant, RL, Football…)
 */
enum ContextMode: string
{
    case RANKING = 'ranking';
    case DUEL = 'duel';
    case DUEL_EQUIPE = 'duel_equipe';
    case GROUPE_VS_EXTERNE = 'groupe_vs_externe';

    public function label(): string
    {
        return match ($this) {
            self::RANKING        => 'Classement libre (Skyjo, Catan, Mario Kart, Uno…)',
            self::DUEL           => 'Duel 1v1 (Échecs, Tennis, Ping-pong, Jeux de cartes…)',
            self::DUEL_EQUIPE    => 'Match d\'équipes internes (Belote 2v2, Pétanque, Volley…)',
            self::GROUPE_VS_EXTERNE => 'Groupe vs Adversaires extérieurs (Valorant, RL, Football…)',
        };
    }

    /** Renvoie true si l'activité utilise un résultat de type match (deux camps). */
    public function isMatchBased(): bool
    {
        return $this !== self::RANKING;
    }
}
