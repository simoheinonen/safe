<?php

namespace Safe;

use Safe\PhpStanFunctions\PhpStanFunctionMapReader;

class Scanner
{
    /*
     * @var string
     */
    private $path;
    /**
     * @var array
     */
    private $excludedModules;

    /**
     * Scanner constructor.
     * @param string $_path
     * @param string[] $excludedModules
     */
    public function __construct(string $_path, array $excludedModules)
    {
        $this->path = $_path;
        $this->excludedModules = $excludedModules;
    }

    /**
     * @return string[]
     */
    public function getPaths(): array
    {
        $incompletePaths = glob($this->path, GLOB_ONLYDIR);

        $paths = [];
        foreach ($incompletePaths as $incompletePath) {
            $paths = array_merge($paths, glob($incompletePath.'/functions/*.xml'));
        }
        return $paths;
    }

    private $ignoredFunctions;

    /**
     * Returns the list of functions that must be ignored.
     * @return string[]
     */
    private function getIgnoredFunctions(): array
    {
        if ($this->ignoredFunctions === null) {
            $this->ignoredFunctions = require __DIR__.'/../config/ignoredFunctions.php';
        }
        return $this->ignoredFunctions;
    }

    private $ignoredModules;

    /**
     * Returns the list of modules that must be ignored.
     * @return string[]
     */
    private function getIgnoredModules(): array
    {
        if ($this->ignoredModules === null) {
            $this->ignoredModules = require __DIR__.'/../config/ignoredModules.php';
        }
        return $this->ignoredModules;
    }

    /**
     * @return mixed[]
     */
    public function getMethods(): array
    {
        $functions = [];
        $overloadedFunctions = [];
        $paths = $this->getPaths();
        $phpStanFunctionMapReader = new PhpStanFunctionMapReader();
        $ignoredFunctions = $this->getIgnoredFunctions();
        $ignoredFunctions = \array_combine($ignoredFunctions, $ignoredFunctions);
        $ignoredModules = $this->getIgnoredModules();
        $ignoredModules = \array_combine($ignoredModules, $ignoredModules);
        foreach ($paths as $path) {
            $module = \basename(\dirname($path, 2));
            if (\in_array($module, $this->excludedModules)) {
                continue;
            }

            $docPage = new DocPage($path);
            if ($docPage->detectFalsyFunction()) {
                $functionObjects = $docPage->getMethodSynopsis();
                if (count($functionObjects) > 1) {
                    $overloadedFunctions = array_merge($overloadedFunctions, \array_map(function ($functionObject) {
                        return $functionObject->methodname->__toString();
                    }, $functionObjects));
                    $overloadedFunctions = \array_filter($overloadedFunctions, function (string $functionName) use ($ignoredFunctions) {
                        return !isset($ignoredFunctions[$functionName]);
                    });
                    continue;
                }
                $rootEntity = $docPage->loadAndResolveFile();
                foreach ($functionObjects as $functionObject) {
                    $function = new Method($functionObject, $rootEntity, $docPage->getModule(), $phpStanFunctionMapReader);
                    if (isset($ignoredFunctions[$function->getFunctionName()])) {
                        continue;
                    }
                    if (isset($ignoredModules[lcfirst($function->getModuleName())])) {
                        continue;
                    }
                    $functions[] = $function;
                }
            }
        }

        return [
            'functions' => $functions,
            'overloadedFunctions' => \array_unique($overloadedFunctions)
        ];
    }
}
