<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerSdk\Integrator\Executor;

use RuntimeException;
use SprykerSdk\Integrator\Dependency\Console\InputOutputInterface;
use SprykerSdk\Integrator\Manifest\ManifestReader;
use SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface;
use SprykerSdk\Integrator\SprykerLock\SprykerLockReader;
use SprykerSdk\Integrator\SprykerLock\SprykerLockWriter;

class ManifestExecutor implements ManifestExecutorInterface
{
    /**
     * @var \SprykerSdk\Integrator\SprykerLock\SprykerLockReader
     */
    protected $sprykerLockReader;

    /**
     * @var \SprykerSdk\Integrator\Manifest\ManifestReader
     */
    protected $manifestReader;

    /**
     * @var array<\SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface>
     */
    protected $manifestExecutors;

    /**
     * @var \SprykerSdk\Integrator\SprykerLock\SprykerLockWriter
     */
    protected $sprykerLockWriter;

    /**
     * @param \SprykerSdk\Integrator\SprykerLock\SprykerLockReader $sprykerLockReader
     * @param \SprykerSdk\Integrator\Manifest\ManifestReader $manifestReader
     * @param \SprykerSdk\Integrator\SprykerLock\SprykerLockWriter $sprykerLockWriter
     * @param \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface[] $manifestExecutors
     */
    public function __construct(
        SprykerLockReader $sprykerLockReader,
        ManifestReader $manifestReader,
        SprykerLockWriter $sprykerLockWriter,
        array $manifestExecutors
    ) {
        $this->sprykerLockReader = $sprykerLockReader;
        $this->manifestReader = $manifestReader;
        $this->manifestExecutors = $manifestExecutors;
        $this->sprykerLockWriter = $sprykerLockWriter;
    }

    /**
     * @param array<\SprykerSdk\Integrator\Transfer\ModuleTransfer> $moduleTransfers
     * @param \SprykerSdk\Integrator\Dependency\Console\InputOutputInterface $inputOutput
     * @param bool $isDry
     *
     * @return int
     */
    public function runModuleManifestExecution(array $moduleTransfers, InputOutputInterface $inputOutput, bool $isDry): int
    {
        $this->assertModuleData($moduleTransfers);

        $manifests = $this->manifestReader->readManifests($moduleTransfers);

        $lockedModules = $this->sprykerLockReader->getLockFileData();

        $unappliedManifests = $this->findUnappliedManifests($manifests, $lockedModules);

        if (!$unappliedManifests) {
            return 0;
        }

        if (!$inputOutput->confirm('There are unapplied manifests found for your modules. Do you want to apply them?')) {
            return 0;
        }

        foreach ($unappliedManifests as $moduleName => $moduleManifests) {
            foreach ($moduleManifests as $manifestType => $unappliedManifestByType) {
                $manifestExecutor = $this->resolveExecutor($manifestType);

                foreach ($unappliedManifestByType as $manifestHash => $unappliedManifest) {
                    if ($manifestExecutor->apply($unappliedManifest, $moduleName, $inputOutput, $isDry)) {
                        $lockedModules[$moduleName][$manifestType][$manifestHash] = $unappliedManifest;
                    }
                }
            }
        }
        if ($isDry) {
            return 0;
        }

        return $this->sprykerLockWriter->storeLock($lockedModules);
    }

    /**
     * @param array<string, array<string, array<string>>> $manifests
     * @param array<string, array<string, array<string>>> $lockedModules
     *
     * @return array<string, array<string, array<string, array<string>>>>
     */
    protected function findUnappliedManifests(array $manifests, array $lockedModules): array
    {
        $unappliedManifests = [];
        foreach ($manifests as $moduleName => $manifestList) {
            foreach ($manifestList as $manifestType => $moduleManifests) {
                foreach ($moduleManifests as $moduleManifest) {
                    $manifestHash = sha1(json_encode($moduleManifest) . $manifestType . $moduleName);
                    if (isset($lockedModules[$moduleName][$manifestType][$manifestHash])) {
                        continue;
                    }

                    $unappliedManifests[$moduleName][$manifestType][$manifestHash] = $moduleManifest;
                }
            }
        }

        return $unappliedManifests;
    }

    /**
     * @param string $manifestType
     *
     * @throws \RuntimeException
     *
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    protected function resolveExecutor(string $manifestType): ManifestStrategyInterface
    {
        foreach ($this->manifestExecutors as $manifestExecutor) {
            if ($manifestType === $manifestExecutor->getType()) {
                return $manifestExecutor;
            }
        }

        throw new RuntimeException("Executor $manifestType not found");
    }

    /**
     * @param array<\SprykerSdk\Integrator\Transfer\ModuleTransfer> $moduleTransfers
     *
     * @return void
     */
    protected function assertModuleData(array $moduleTransfers): void
    {
        foreach ($moduleTransfers as $moduleTransfer) {
            $moduleTransfer->requireName()
                ->requireNameDashed()
                ->requireOrganization()
                ->getOrganization()
                ->requireNameDashed()
                ->requireName();
        }
    }
}
