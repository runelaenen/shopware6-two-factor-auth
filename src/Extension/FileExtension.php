<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Extension;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[AutoconfigureTag(name: 'twig.extension')]
class FileExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(service: 'shopware.filesystem.asset')]
        private readonly FilesystemOperator $operator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('rl2fa_main_js', $this->pathToBundleMainJs(...)),
        ];
    }

    public function pathToBundleMainJs(string $bundle, string $type = 'administration'): ?string
    {
        try {
            $content = $this->operator->read(\sprintf(
                '/bundles/%s/%s/.vite/manifest.json',
                strtolower($bundle),
                strtolower($type)
            ));
            $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            return $type . '/' . ($content['main.js']['file'] ?? '');
        } catch (\Throwable) {
            return null;
        }
    }
}
