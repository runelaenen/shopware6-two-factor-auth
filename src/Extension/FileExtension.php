<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Extension;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[AutoconfigureTag(name: 'twig.extension')]
class FileExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(service: 'shopware.filesystem.public')]
        private readonly FilesystemOperator $operator,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('main_js', $this->pathToBundleMainJs(...)),
        ];
    }

    public function pathToBundleMainJs(string $bundle, string $type = 'administration'): ?string
    {
        try {
            $content = $this->operator->read(sprintf(
                '/bundles/%s/%s/.vite/manifest.json',
                strtolower($bundle),
                strtolower($type)
            ));

            return $type . '/' . json_decode($content, true)['main.js']['file'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
