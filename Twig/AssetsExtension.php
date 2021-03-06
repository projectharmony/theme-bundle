<?php

namespace Harmony\Bundle\ThemeBundle\Twig;

use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Bundle\ThemeBundle\HarmonyThemeBundle;
use Liip\ThemeBundle\ActiveTheme;
use Symfony\Bridge\Twig\Extension\AssetExtension as BridgeAssetExtension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function array_merge;
use function sprintf;

/**
 * Class AssetsExtension
 *
 * @package Harmony\Bundle\ThemeBundle\Twig
 */
class AssetsExtension extends BridgeAssetExtension
{

    /** @var ActiveTheme $activeTheme */
    protected $activeTheme;

    /** @var string $projectDir */
    protected $projectDir;

    /** @var KernelInterface|AbstractKernel $kernel */
    protected $kernel;

    /**
     * AssetsExtension constructor.
     *
     * @param KernelInterface $kernel
     * @param Packages        $packages
     * @param ActiveTheme     $activeTheme
     * @param string          $projectDir
     */
    public function __construct(KernelInterface $kernel, Packages $packages, ActiveTheme $activeTheme,
                                string $projectDir)
    {
        parent::__construct($packages);
        $this->kernel      = $kernel;
        $this->activeTheme = $activeTheme;
        $this->projectDir  = $projectDir;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return array_merge(parent::getFunctions(), [
            new TwigFunction('asset_theme', [$this, 'getAssetUrl'])
        ]);
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return array_merge(parent::getFilters(), [
            new TwigFilter('theme', [$this, 'getThemeUrl'])
        ]);
    }

    /**
     * Returns the public url/path of an asset.
     * If the package used to generate the path is an instance of
     * UrlPackage, you will always get a URL and not a path.
     *
     * @param string $path        A public path
     * @param string $packageName The name of the asset package to use
     *
     * @return string The public path of the asset
     */
    public function getAssetUrl($path, $packageName = null): string
    {
        if (null !== $theme = $this->kernel->getThemes()[$this->activeTheme->getName()] ?? null) {
            $parentTheme = $theme->getParent();

            $assetPath = sprintf('%s/%s/%s', HarmonyThemeBundle::THEMES_DIR, $theme->getShortName(), $path);

            // Asset exists in current active theme
            if (\file_exists(sprintf('%s/public/%s', $this->kernel->getProjectDir(), $assetPath))) {
                return parent::getAssetUrl($assetPath, $packageName);
            } // Has a parent theme
            elseif (null !== $parentTheme && \file_exists(sprintf('%s/public/%s', $this->kernel->getProjectDir(),
                    $parentAssetPath = sprintf('%s/%s/%s', HarmonyThemeBundle::THEMES_DIR, $parentTheme->getShortName(),
                        $path)))) {
                return parent::getAssetUrl($parentAssetPath, $packageName);
            }
        }

        return parent::getAssetUrl($path, $packageName);
    }
}