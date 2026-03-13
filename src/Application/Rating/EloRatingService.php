<?php

namespace App\Application\Rating;

final class EloRatingService
{
    /**
     * @param array<int, array{playedAt:\DateTimeImmutable, homeKey:string, awayKey:string, homeScore:int, awayScore:int}> $events
     * @return array<string, int>
     */
    public function calculateFromEvents(array $events, int $baseRating = 1000, float $kFactor = 24.0, ?string $activityName = null, ?string $contextMode = null): array
    {
        usort($events, static fn (array $left, array $right): int => $left['playedAt'] <=> $right['playedAt']);

        $profile = $this->resolveProfile($baseRating, $kFactor, $activityName, $contextMode);
        $ratings = [];
        $gamesPlayed = [];

        foreach ($events as $event) {
            $homeKey = $event['homeKey'];
            $awayKey = $event['awayKey'];

            $homeRating = $ratings[$homeKey] ?? (float) $profile['baseRating'];
            $awayRating = $ratings[$awayKey] ?? (float) $profile['baseRating'];

            $expectedHome = 1 / (1 + pow(10, ($awayRating - $homeRating) / 400));
            $expectedAway = 1 / (1 + pow(10, ($homeRating - $awayRating) / 400));

            if ($event['homeScore'] > $event['awayScore']) {
                $scoreHome = 1.0;
                $scoreAway = 0.0;
            } elseif ($event['homeScore'] < $event['awayScore']) {
                $scoreHome = 0.0;
                $scoreAway = 1.0;
            } else {
                $scoreHome = 0.5;
                $scoreAway = 0.5;
            }

            $margin = abs($event['homeScore'] - $event['awayScore']);
            $homeK = $this->resolveAdaptiveK((int) ($gamesPlayed[$homeKey] ?? 0), $homeRating, $profile);
            $awayK = $this->resolveAdaptiveK((int) ($gamesPlayed[$awayKey] ?? 0), $awayRating, $profile);
            $marginMultiplier = $this->resolveMarginMultiplier($margin, $profile);
            $homeUpset = 1 + abs($scoreHome - $expectedHome) * $profile['upsetFactor'];
            $awayUpset = 1 + abs($scoreAway - $expectedAway) * $profile['upsetFactor'];

            if ($scoreHome === 0.5) {
                $marginMultiplier *= $profile['drawMultiplier'];
            }

            $homeRating += $homeK * $marginMultiplier * $homeUpset * ($scoreHome - $expectedHome);
            $awayRating += $awayK * $marginMultiplier * $awayUpset * ($scoreAway - $expectedAway);

            $ratings[$homeKey] = $homeRating;
            $ratings[$awayKey] = $awayRating;
            $gamesPlayed[$homeKey] = (int) ($gamesPlayed[$homeKey] ?? 0) + 1;
            $gamesPlayed[$awayKey] = (int) ($gamesPlayed[$awayKey] ?? 0) + 1;
        }

        return array_map(static fn (float $rating): int => (int) round($rating), $ratings);
    }

    /**
     * @return array{baseRating:int,kFactor:float,minK:float,maxK:float,placementMatches:int,placementBoost:float,marginFactor:float,marginCap:float,upsetFactor:float,drawMultiplier:float}
     */
    private function resolveProfile(int $baseRating, float $kFactor, ?string $activityName, ?string $contextMode): array
    {
        $normalizedName = $this->normalize((string) $activityName);
        $lowerLabel = mb_strtolower(trim((string) $activityName));

        $profile = [
            'baseRating' => $baseRating,
            'kFactor' => $kFactor,
            'minK' => 18.0,
            'maxK' => 36.0,
            'placementMatches' => 8,
            'placementBoost' => 1.22,
            'marginFactor' => 0.08,
            'marginCap' => 0.35,
            'upsetFactor' => 0.32,
            'drawMultiplier' => 0.88,
        ];

        if ($contextMode === 'groupe_vs_externe') {
            $profile['kFactor'] = 30.0;
            $profile['maxK'] = 42.0;
            $profile['marginFactor'] = 0.09;
        }

        if ($contextMode === 'duel_equipe') {
            $profile['kFactor'] = 28.0;
            $profile['maxK'] = 38.0;
            $profile['marginFactor'] = 0.085;
        }

        if ($contextMode === 'duel') {
            $profile['kFactor'] = 26.0;
            $profile['maxK'] = 34.0;
            $profile['drawMultiplier'] = 0.94;
        }

        if (str_contains($normalizedName, 'rocketleague') || preg_match('/(^|\s)rl(\s|$)/', $lowerLabel) === 1) {
            $profile['kFactor'] = 32.0;
            $profile['maxK'] = 44.0;
            $profile['placementMatches'] = 10;
            $profile['placementBoost'] = 1.28;
            $profile['marginFactor'] = 0.065;
            $profile['marginCap'] = 0.26;
            $profile['upsetFactor'] = 0.38;
        }

        if (str_contains($normalizedName, 'valorant') || str_contains($normalizedName, 'valo')) {
            $profile['kFactor'] = 30.0;
            $profile['maxK'] = 40.0;
            $profile['placementMatches'] = 9;
            $profile['placementBoost'] = 1.25;
            $profile['marginFactor'] = 0.05;
            $profile['marginCap'] = 0.2;
            $profile['upsetFactor'] = 0.42;
        }

        if (str_contains($normalizedName, 'chess') || str_contains($normalizedName, 'echec')) {
            $profile['kFactor'] = 24.0;
            $profile['minK'] = 16.0;
            $profile['maxK'] = 30.0;
            $profile['marginFactor'] = 0.025;
            $profile['marginCap'] = 0.08;
            $profile['drawMultiplier'] = 1.0;
        }

        return $profile;
    }

    /**
     * @param array{baseRating:int,kFactor:float,minK:float,maxK:float,placementMatches:int,placementBoost:float,marginFactor:float,marginCap:float,upsetFactor:float,drawMultiplier:float} $profile
     */
    private function resolveAdaptiveK(int $gamesPlayed, float $rating, array $profile): float
    {
        $k = $profile['kFactor'];

        if ($gamesPlayed < $profile['placementMatches']) {
            $k *= $profile['placementBoost'];
        } elseif ($gamesPlayed >= 25) {
            $k *= 0.92;
        }

        $distanceFromBase = abs($rating - $profile['baseRating']);
        if ($distanceFromBase >= 220) {
            $k *= 0.92;
        }

        return max($profile['minK'], min($profile['maxK'], $k));
    }

    /**
     * @param array{baseRating:int,kFactor:float,minK:float,maxK:float,placementMatches:int,placementBoost:float,marginFactor:float,marginCap:float,upsetFactor:float,drawMultiplier:float} $profile
     */
    private function resolveMarginMultiplier(int $margin, array $profile): float
    {
        if ($margin <= 0) {
            return 1.0;
        }

        return 1.0 + min($profile['marginCap'], sqrt((float) $margin) * $profile['marginFactor']);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c'], $value);

        return preg_replace('/[^a-z0-9\s]/', '', $value) ?? '';
    }
}