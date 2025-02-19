<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerSdk\Integrator\Builder\ClassResolver;

use SprykerSdk\Integrator\Builder\ClassGenerator\ClassGenerator;
use SprykerSdk\Integrator\Builder\ClassLoader\ClassLoader;
use SprykerSdk\Integrator\Transfer\ClassInformationTransfer;

class ClassResolver implements ClassResolverInterface
{
    /**
     * @var \SprykerSdk\Integrator\Builder\ClassLoader\ClassLoader
     */
    protected $classLoader;

    /**
     * @var \SprykerSdk\Integrator\Builder\ClassGenerator\ClassGenerator
     */
    protected $classGenerator;

    /**
     * @var \SprykerSdk\Integrator\Transfer\ClassInformationTransfer[]
     */
    protected static $generatedClassList = [];

    /**
     * @param \SprykerSdk\Integrator\Builder\ClassLoader\ClassLoader $classLoader
     * @param \SprykerSdk\Integrator\Builder\ClassGenerator\ClassGenerator $classGenerator
     */
    public function __construct(
        ClassLoader $classLoader,
        ClassGenerator $classGenerator
    ) {
        $this->classLoader = $classLoader;
        $this->classGenerator = $classGenerator;
    }

    /**
     * @param string $targetClassName
     * @param string $customOrganisation
     *
     * @return \SprykerSdk\Integrator\Transfer\ClassInformationTransfer
     */
    public function resolveClass(string $targetClassName, string $customOrganisation = ''): ClassInformationTransfer
    {
        $resolvedClassName = $targetClassName;
        if ($customOrganisation) {
            $resolvedClassName = preg_replace("/(\w+)/", $customOrganisation, $targetClassName, 1);
        }

        if (!isset(static::$generatedClassList[$resolvedClassName])) {
            if (class_exists($resolvedClassName)) {
                $classInformationTransfer = $this->classLoader->loadClass($resolvedClassName);
            } else {
                $classInformationTransfer = $this->classGenerator->generateClass($resolvedClassName, $targetClassName);
            }
            static::$generatedClassList[$resolvedClassName] = $classInformationTransfer;
        }

        return static::$generatedClassList[$resolvedClassName];
    }
}
