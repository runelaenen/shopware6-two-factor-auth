<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Extension;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[AutoconfigureTag(name: 'twig.extension')]
class FileExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('main_js', [$this, 'pathToBundleMainJs']),
        ];
    }

    public function pathToBundleMainJs(string $bundle, string $type = 'administration'): ?string
    {
        try {
            $path = sprintf(
                '%s/public/bundles/%s/%s/.vite/manifest.json',
                $this->projectDir,
                strtolower($bundle),
                strtolower($type)
            );

            return $type . '/' . json_decode(file_get_contents($path), true)['main.js']['file'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
