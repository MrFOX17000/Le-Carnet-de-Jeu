<?php

namespace App\Twig;

use App\Presentation\Activity\ActivityPresentation;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ActivityPresentationExtension extends AbstractExtension
{
    public function __construct(private readonly ActivityPresentation $activityPresentation)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('activity_meta', $this->activityPresentation->build(...)),
        ];
    }
}