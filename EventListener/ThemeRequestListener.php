<?php

declare(strict_types=1);

namespace Harmony\Bundle\ThemeBundle\EventListener;

use Exception;
use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Bundle\RoutingBundle\Component\Routing\RouteCollectionBuilder;
use Harmony\Bundle\ThemeBundle\Exception\NoActiveThemeException;
use LogicException;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use function explode;
use function is_dir;

/**
 * Class ThemeRequestListener
 *
 * @package Harmony\Bundle\ThemeBundle\EventListener
 */
class ThemeRequestListener
{

    /**
     * @var string
     */
    protected $newTheme;

    /** @var KernelInterface $kernel */
    protected $kernel;

    /** @var TranslatorInterface|DataCollectorTranslator|Translator $translator */
    protected $translator;

    /** @var RouteCollectionBuilder $builder */
    protected $builder;

    /** @var string|null $defaultTheme */
    protected $defaultTheme;

    /**
     * @param KernelInterface|AbstractKernel              $kernel
     * @param TranslatorInterface|DataCollectorTranslator $translator
     * @param RouteCollectionBuilder                      $builder
     * @param string|null                                 $defaultTheme
     */
    public function __construct(KernelInterface $kernel, TranslatorInterface $translator,
                                RouteCollectionBuilder $builder, string $defaultTheme = null)
    {
        $this->kernel       = $kernel;
        $this->translator   = $translator;
        $this->builder      = $builder;
        $this->defaultTheme = $defaultTheme;
    }

    /**
     * @param GetResponseEvent $event
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST === $event->getRequestType()) {
            /**
             * Throw exception if no theme set by default.
             * This can occur only for routes inside the `main` slot.
             * In that case, exception will not be throw in the admin area.
             */
            if (null === $this->defaultTheme && $this->builder->hasRoute($event->getRequest()->get('_route'))) {
                throw new NoActiveThemeException('You must enable a theme to be set has an active theme.');
            }

            if ((null !== $theme = $this->kernel->getThemes()[$this->defaultTheme] ?? null) &&
                $this->translator instanceof DataCollectorTranslator) {

                $finder = (new Finder())->files();
                // Parent need to be loaded first
                if (null !== $theme->getParent() &&
                    is_dir($parentTransPath = $theme->getParent()->getPath() . '/translations')) {
                    $finder->in($parentTransPath);
                }
                // Child (current) theme, will override translations
                if (is_dir($transPath = $theme->getPath() . '/translations')) {
                    $finder->in($transPath);
                }

                try {
                    /** @var SplFileInfo $file */
                    foreach ($finder as $file) {
                        [$domain, $locale] = explode('.', $file->getBasename(), 3);
                        switch ($file->getExtension()) {
                            case 'php':
                                $this->translator->addResource('php', (string)$file, $locale, $domain);
                                break;
                            case 'xlf':
                            case 'xliff':
                                $this->translator->addResource('xlf', (string)$file, $locale, $domain);
                                break;
                            case 'yaml':
                                $this->translator->addResource('yaml', (string)$file, $locale, $domain);
                                break;
                        }
                    }
                }
                catch (LogicException $e) {
                }
            }
        }
    }
}