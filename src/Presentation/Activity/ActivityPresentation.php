<?php

namespace App\Presentation\Activity;

final class ActivityPresentation
{
    /**
     * @return array{key:string,label:string,shortLabel:string,badge:string,iconUrl:?string,iconAlt:string,accent:string,background:string,foreground:string,contextLabel:string}
     */
    public function build(?string $activityName, ?string $contextMode = null): array
    {
        $label = trim((string) $activityName);
        $normalized = $this->normalize($label);
        $lowerLabel = mb_strtolower($label);

        $meta = match (true) {
            str_contains($normalized, 'rocketleague'),
            preg_match('/(^|\s)rl(\s|$)/', $lowerLabel) === 1 => [
                'key' => 'rocket-league',
                'shortLabel' => 'RL',
                'badge' => 'Competitive',
                'iconUrl' => 'https://img.icons8.com/fluency/48/rocket-league.png',
                'accent' => '#f97316',
                'background' => 'linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%)',
                'foreground' => '#9a3412',
            ],
            str_contains($normalized, 'valorant'),
            str_contains($normalized, 'valo') => [
                'key' => 'valorant',
                'shortLabel' => 'VLR',
                'badge' => 'Tactical',
                'iconUrl' => 'https://img.icons8.com/color/48/valorant.png',
                'accent' => '#e11d48',
                'background' => 'linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%)',
                'foreground' => '#9f1239',
            ],
            str_contains($normalized, 'chess'),
            str_contains($normalized, 'echec') => [
                'key' => 'chess',
                'shortLabel' => 'CHS',
                'badge' => 'Mind game',
                'iconUrl' => 'https://img.icons8.com/color/48/chess-com.png',
                'accent' => '#475569',
                'background' => 'linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%)',
                'foreground' => '#334155',
            ],
            str_contains($normalized, 'skyjo') => [
                'key' => 'skyjo',
                'shortLabel' => 'SKY',
                'badge' => 'Party score',
                'iconUrl' => 'https://img.icons8.com/color/48/cards.png',
                'accent' => '#0f766e',
                'background' => 'linear-gradient(135deg, #ecfeff 0%, #ccfbf1 100%)',
                'foreground' => '#115e59',
            ],
            str_contains($normalized, 'belote') => [
                'key' => 'belote',
                'shortLabel' => 'BLT',
                'badge' => 'Card squad',
                'iconUrl' => 'https://img.icons8.com/3d-fluency/94/cards.png',
                'accent' => '#7c3aed',
                'background' => 'linear-gradient(135deg, #faf5ff 0%, #ede9fe 100%)',
                'foreground' => '#6d28d9',
            ],
            str_contains($normalized, 'fortnite') => [
                'key' => 'fortnite',
                'shortLabel' => 'FNT',
                'badge' => 'Battle royale',
                'iconUrl' => 'https://img.icons8.com/color/48/fortnite.png',
                'accent' => '#16a34a',
                'background' => 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)',
                'foreground' => '#166534',
            ],
            str_contains($normalized, 'eafc'),
            str_contains($normalized, 'fifa'),
            str_contains($normalized, 'football') => [
                'key' => 'ea-fc',
                'shortLabel' => 'EFC',
                'badge' => 'Sport',
                'iconUrl' => 'https://img.icons8.com/emoji/48/soccer-ball-emoji.png',
                'accent' => '#15803d',
                'background' => 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)',
                'foreground' => '#166534',
            ],
            str_contains($normalized, 'mariokart') => [
                'key' => 'mario-kart',
                'shortLabel' => 'MK',
                'badge' => 'Party race',
                'iconUrl' => 'https://img.icons8.com/color/48/super-mario.png',
                'accent' => '#ea580c',
                'background' => 'linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%)',
                'foreground' => '#9a3412',
            ],
            str_contains($normalized, 'catan') => [
                'key' => 'catan',
                'shortLabel' => 'CTN',
                'badge' => 'Board game',
                'iconUrl' => 'https://img.icons8.com/color/48/dice.png',
                'accent' => '#b45309',
                'background' => 'linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%)',
                'foreground' => '#92400e',
            ],
            str_contains($normalized, 'uno') => [
                'key' => 'uno',
                'shortLabel' => 'UNO',
                'badge' => 'Card game',
                'iconUrl' => 'https://img.icons8.com/ios/50/uno.png',
                'accent' => '#dc2626',
                'background' => 'linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%)',
                'foreground' => '#991b1b',
            ],
            str_contains($normalized, 'apex') => [
                'key' => 'apex',
                'shortLabel' => 'APX',
                'badge' => 'FPS squad',
                'iconUrl' => 'https://img.icons8.com/color/48/controller.png',
                'accent' => '#334155',
                'background' => 'linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%)',
                'foreground' => '#1e293b',
            ],
            str_contains($normalized, 'counterstrike'),
            str_contains($normalized, 'cs2') => [
                'key' => 'cs2',
                'shortLabel' => 'CS2',
                'badge' => 'Tactical FPS',
                'iconUrl' => 'https://img.icons8.com/nolan/64/counter-strike-global-offensive.png',
                'accent' => '#0369a1',
                'background' => 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)',
                'foreground' => '#0c4a6e',
            ],
            str_contains($normalized, 'overwatch') => [
                'key' => 'overwatch',
                'shortLabel' => 'OW',
                'badge' => 'Hero shooter',
                'iconUrl' => 'https://img.icons8.com/color/48/overwatch--v1.png',
                'accent' => '#ea580c',
                'background' => 'linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%)',
                'foreground' => '#9a3412',
            ],
            str_contains($normalized, 'leagueoflegends'),
            preg_match('/(^|\s)lol(\s|$)/', $lowerLabel) === 1 => [
                'key' => 'league-of-legends',
                'shortLabel' => 'LOL',
                'badge' => 'MOBA',
                'iconUrl' => 'https://img.icons8.com/color/48/controller.png',
                'accent' => '#7c3aed',
                'background' => 'linear-gradient(135deg, #faf5ff 0%, #ede9fe 100%)',
                'foreground' => '#6d28d9',
            ],
            default => [
                'key' => 'generic',
                'shortLabel' => $this->makeShortLabel($label),
                'badge' => 'Activity',
                'iconUrl' => 'https://img.icons8.com/color/48/controller.png',
                'accent' => '#2563eb',
                'background' => 'linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)',
                'foreground' => '#1d4ed8',
            ],
        };

        return [
            'key' => $meta['key'],
            'label' => $label !== '' ? $label : 'Activité',
            'shortLabel' => $meta['shortLabel'],
            'badge' => $meta['badge'],
            'iconUrl' => $meta['iconUrl'],
            'iconAlt' => $label !== '' ? sprintf('Icône %s', $label) : 'Icône activité',
            'accent' => $meta['accent'],
            'background' => $meta['background'],
            'foreground' => $meta['foreground'],
            'contextLabel' => $this->resolveContextLabel($contextMode),
        ];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç', ' '], ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c', ''], $value);

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }

    private function makeShortLabel(string $label): string
    {
        $chunks = preg_split('/[^A-Za-z0-9]+/', trim($label)) ?: [];
        $short = '';

        foreach ($chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }

            $short .= mb_strtoupper(mb_substr($chunk, 0, 1));

            if (mb_strlen($short) >= 3) {
                break;
            }
        }

        return $short !== '' ? mb_substr($short, 0, 3) : 'ACT';
    }

    private function resolveContextLabel(?string $contextMode): string
    {
        return match ($contextMode) {
            'ranking' => 'Classement',
            'duel' => '1v1',
            'duel_equipe' => 'Equipe',
            'groupe_vs_externe' => 'Squad',
            default => 'Mode libre',
        };
    }
}