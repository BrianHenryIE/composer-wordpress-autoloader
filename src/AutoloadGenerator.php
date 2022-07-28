<?php

namespace ComposerWordPressAutoloader;

use Composer\Composer;
use Composer\Autoload\AutoloadGenerator as ComposerAutoloadGenerator;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\Filesystem;

/**
 * Composer Autoload Generator
 */
class AutoloadGenerator extends ComposerAutoloadGenerator
{
    protected Composer $composer;
    protected bool $devMode = true;

    /**
     * Constructor.
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        parent::__construct($composer->getEventDispatcher(), $io);

        $this->composer = $composer;
    }

    /**
     * @param bool $devMode
     * @return void
     */
    public function setDevMode($devMode = true)
    {
        parent::setDevMode($devMode);

        $this->devMode = (bool) $devMode;
    }

    /**
     * Generate the autoload file.
     *
     * @param boolean $beingInjected Flag if the autoload file is being injected.
     * @param boolean $isDevMode Flag if dev dependencies are being included.
     * @return string
     */
    public function generate(bool $beingInjected, bool $isDevMode = true): string
    {
        $filesystem = new Filesystem();
        $autoloadFileContents = '';

        $basePath = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($this->composer->getConfig()->get('vendor-dir'))));

        $this->setDevMode($isDevMode);

        // Collect all the rules from all the packages.
        $rules = array_merge_recursive(
            $this->collectAutoloaderRules(),
            $this->collectExtraAutoloaderRules(),
        );

        foreach ($rules as $namespace => $paths) {
            // Convert the paths to be relative to the vendor/wordpress-autoload.php file.
            $rules[$namespace] = array_values(array_unique(
                array_map(fn ($path) => $this->getPathCode($filesystem, $basePath, $vendorPath, $path), $paths),
            ));
        }

        $autoloadFileContents = <<<FILEHEADER
<?php
/* Composer WordPress Autoloader @generated by alleyinteractive/composer-wordpress-autoloader */
FILEHEADER;

        // Load the Composer autoloader if this is not being injected.
        if (!$beingInjected) {
            $autoloadFileContents .= "\n\$autoload = require_once __DIR__ . '/autoload.php';\n";
        }

        $autoloadFileContents .= <<<AUTOLOAD


\$vendorDir = __DIR__;
\$baseDir = dirname(\$vendorDir);

\ComposerWordPressAutoloader\AutoloadFactory::registerFromRules(array(

AUTOLOAD;

        foreach ($rules as $namespace => $paths) {
            $autoloadFileContents .= sprintf(
                '    %s => array(%s),',
                var_export($namespace, true),
                implode(', ', $paths),
            ) . PHP_EOL;
            ;
        }

        $autoloadFileContents .= "));\n";

        if (!$beingInjected) {
            $autoloadFileContents .= "\nreturn \$autoload;";
        }

        return $autoloadFileContents . "\n";
    }

    /**
     * Collect the autoloader rules from 'autoload' and 'autoload-dev' to
     * generate rules for.
     *
     * @return array<string, string>
     */
    protected function collectAutoloaderRules(): array
    {
        return $this->parseAutoloads(
            $this->buildPackageMap(
                $this->composer->getInstallationManager(),
                $this->composer->getPackage(),
                $this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages(),
            ),
            $this->composer->getPackage(),
            !$this->devMode,
        )['wordpress'] ?? [];
    }

    /**
     * Collect the autoloader rules registered via 'extra' to generate for.
     *
     * @return array<string, string>
     */
    protected function collectExtraAutoloaderRules(): array
    {
        return $this->parseExtraAutoloads(
            $this->buildPackageMap(
                $this->composer->getInstallationManager(),
                $this->composer->getPackage(),
                $this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages(),
            ),
            $this->composer->getPackage(),
            !$this->devMode,
        )['wordpress'] ?? [];
    }

    /**
     * Compiles an ordered list of namespace => path mappings
     *
     * @param array $packageMap
     * @param PackageInterface $rootPackage
     * @param boolean $filteredDevPackages
     * @return array<string, array<string>>
     */
    public function parseAutoloads(array $packageMap, PackageInterface $rootPackage, $filteredDevPackages = false)
    {
        $rootPackageMap = array_shift($packageMap);

        // Mirroring existing logic in the Composer AutoloadGenerator.
        if (is_array($filteredDevPackages)) {
            $packageMap = array_filter($packageMap, function ($item) use ($filteredDevPackages) {
                return !in_array($item[0]->getName(), $filteredDevPackages, true);
            });
        } elseif ($filteredDevPackages) {
            $packageMap = $this->filterPackageMap($packageMap, $rootPackage);
        }

        if ($filteredDevPackages) {
            $packageMap = $this->filterPackageMap($packageMap, $rootPackage);
        }

        $sortedPackageMap = $this->sortPackageMap($packageMap);
        $sortedPackageMap[] = $rootPackageMap;
        array_unshift($packageMap, $rootPackageMap);

        $wordpress = $this->parseAutoloadsType($sortedPackageMap, 'wordpress', $rootPackage);

        krsort($wordpress);

        return [
            'wordpress' => $wordpress,
        ];
    }

    /**
     * Compiles an ordered list of namespace => path mappings of autoloads defined in the 'extra' part of a package.
     *
     * @param array $packageMap
     * @param PackageInterface $rootPackage
     * @param boolean $filteredDevPackages
     * @return array
     */
    public function parseExtraAutoloads(array $packageMap, PackageInterface $rootPackage, $filteredDevPackages = false)
    {
        $rootPackageMap = array_shift($packageMap);

        // Mirroring existing logic in the Composer AutoloadGenerator.
        if (is_array($filteredDevPackages)) {
            $packageMap = array_filter($packageMap, function ($item) use ($filteredDevPackages) {
                return !in_array($item[0]->getName(), $filteredDevPackages, true);
            });
        } elseif ($filteredDevPackages) {
            $packageMap = $this->filterPackageMap($packageMap, $rootPackage);
        }

        if ($filteredDevPackages) {
            $packageMap = $this->filterPackageMap($packageMap, $rootPackage);
        }

        $sortedPackageMap = $this->sortPackageMap($packageMap);
        $sortedPackageMap[] = $rootPackageMap;
        array_unshift($packageMap, $rootPackageMap);

        return [
          'wordpress' => $this->parseExtraAutoloadsType($sortedPackageMap, 'wordpress', $rootPackage),
        ];
    }

    /**
     * A modified port of the {@see AutoloadGenerator::parseAutoloadsType()} method from Composer.
     *
     * Imports autoload rules from a package's extra path.
     *
     * @param array<int, array{0: PackageInterface, 1: string}> $packageMap
     * @param string $type one of: 'wordpress'
     * @return array<int, string>|array<string, array<string>>|array<string, string>
     */
    protected function parseExtraAutoloadsType(array $packageMap, $type, RootPackageInterface $rootPackage)
    {
        $autoloads = [];

        foreach ($packageMap as $item) {
            [$package, $installPath] = $item;
            $autoload = [
              'wordpress' => $package->getExtra()['wordpress-autoloader']['autoload'] ?? [],
            ];

            // Include autoload dev if we're in dev mode and this is the root package.
            // Non-root package dev dependencies are not loaded.
            if ($this->devMode && $package === $rootPackage) {
                $autoload = array_merge_recursive(
                    $autoload,
                    [
                        'wordpress' => $package->getExtra()['wordpress-autoloader']['autoload-dev'] ?? [],
                    ],
                );
            }

            // Skip misconfigured packages.
            if (!isset($autoload[$type]) || !is_array($autoload[$type])) {
                continue;
            }

            if (null !== $package->getTargetDir() && $package !== $rootPackage) {
                $installPath = substr($installPath, 0, -strlen('/' . $package->getTargetDir()));
            }

            if ($package !== $rootPackage) {
                $installPath = str_replace($rootPackage->getTargetDir(), '', $installPath);
            }

            foreach ($autoload[$type] as $namespace => $paths) {
                foreach ((array) $paths as $path) {
                    $relativePath = empty($installPath) ? (empty($path) ? '.' : $path) : $installPath . '/' . $path;
                    $autoloads[$namespace][] = $relativePath;
                }
            }
        }

        return $autoloads;
    }
}
