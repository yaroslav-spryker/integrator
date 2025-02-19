<?php

declare(strict_types=1);

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerSdk\Integrator;

use PhpParser\BuilderFactory;
use PhpParser\Lexer;
use PhpParser\Lexer\Emulative;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\Parser\Php7;
use SprykerSdk\Integrator\Builder\ClassGenerator\ClassGenerator;
use SprykerSdk\Integrator\Builder\ClassGenerator\ClassGeneratorInterface;
use SprykerSdk\Integrator\Builder\ClassLoader\ClassLoader;
use SprykerSdk\Integrator\Builder\ClassLoader\ClassLoaderInterface;
use SprykerSdk\Integrator\Builder\ClassModifier\ClassConstantModifier;
use SprykerSdk\Integrator\Builder\ClassModifier\ClassConstantModifierInterface;
use SprykerSdk\Integrator\Builder\ClassModifier\ClassInstanceClassModifier;
use SprykerSdk\Integrator\Builder\ClassModifier\ClassInstanceClassModifierInterface;
use SprykerSdk\Integrator\Builder\ClassModifier\ClassListModifier;
use SprykerSdk\Integrator\Builder\ClassModifier\ClassListModifierInterface;
use SprykerSdk\Integrator\Builder\ClassModifier\CommonClassModifier;
use SprykerSdk\Integrator\Builder\ClassModifier\CommonClassModifierInterface;
use SprykerSdk\Integrator\Builder\ClassModifier\GlueRelationshipModifier;
use SprykerSdk\Integrator\Builder\ClassModifier\GlueRelationshipModifierInterface;
use SprykerSdk\Integrator\Builder\ClassResolver\ClassResolver;
use SprykerSdk\Integrator\Builder\ClassResolver\ClassResolverInterface;
use SprykerSdk\Integrator\Builder\ClassWriter\ClassFileWriter;
use SprykerSdk\Integrator\Builder\ClassWriter\ClassFileWriterInterface;
use SprykerSdk\Integrator\Builder\Checker\ClassMethodChecker;
use SprykerSdk\Integrator\Builder\Checker\ClassMethodCheckerInterface;
use SprykerSdk\Integrator\Builder\Finder\ClassNodeFinder;
use SprykerSdk\Integrator\Builder\Finder\ClassNodeFinderInterface;
use SprykerSdk\Integrator\Builder\Printer\ClassDiffPrinter;
use SprykerSdk\Integrator\Builder\Printer\ClassDiffPrinterInterface;
use SprykerSdk\Integrator\Builder\Printer\ClassPrinter;
use SprykerSdk\Integrator\Composer\ComposerLockReader;
use SprykerSdk\Integrator\Composer\ComposerLockReaderInterface;
use SprykerSdk\Integrator\Executor\ManifestExecutor;
use SprykerSdk\Integrator\Executor\ManifestExecutorInterface;
use SprykerSdk\Integrator\Helper\ClassHelper;
use SprykerSdk\Integrator\Helper\ClassHelperInterface;
use SprykerSdk\Integrator\Manifest\ManifestReader;
use SprykerSdk\Integrator\Manifest\ManifestReaderInterface;
use SprykerSdk\Integrator\ManifestStrategy\ConfigureEnvManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\ConfigureModuleManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\CopyModuleFileManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\ExecuteConsoleManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface;
use SprykerSdk\Integrator\ManifestStrategy\UnwireGlueRelationshipManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\UnwirePluginManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\UnwireWidgetManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\WireGlueRelationshipManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\WirePluginManifestStrategy;
use SprykerSdk\Integrator\ManifestStrategy\WireWidgetManifestStrategy;
use SprykerSdk\Integrator\ModuleFinder\ModuleFinderFacade;
use SprykerSdk\Integrator\ModuleFinder\ModuleFinderFacadeInterface;
use SprykerSdk\Integrator\SprykerLock\SprykerLockReader;
use SprykerSdk\Integrator\SprykerLock\SprykerLockReaderInterface;
use SprykerSdk\Integrator\SprykerLock\SprykerLockWriter;
use SprykerSdk\Integrator\SprykerLock\SprykerLockWriterInterface;

class IntegratorFactory
{
    /**
     * @return \SprykerSdk\Integrator\IntegratorConfig
     */
    protected function getConfig(): IntegratorConfig
    {
        return IntegratorConfig::getInstance();
    }

    /**
     * @return \SprykerSdk\Integrator\Executor\ManifestExecutorInterface
     */
    public function creatManifestExecutor(): ManifestExecutorInterface
    {
        return new ManifestExecutor(
            $this->createSprykerLockReader(),
            $this->createManifestReader(),
            $this->createSprykerLockWriter(),
            $this->getManifestExecutorStrategies()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\SprykerLock\SprykerLockReaderInterface
     */
    public function createSprykerLockReader(): SprykerLockReaderInterface
    {
        return new SprykerLockReader($this->getConfig());
    }

    /**
     * @return \SprykerSdk\Integrator\SprykerLock\SprykerLockWriterInterface
     */
    public function createSprykerLockWriter(): SprykerLockWriterInterface
    {
        return new SprykerLockWriter($this->getConfig());
    }

    /**
     * @return \SprykerSdk\Integrator\Composer\ComposerLockReaderInterface
     */
    public function createComposerLockReader(): ComposerLockReaderInterface
    {
        return new ComposerLockReader($this->getConfig());
    }

    /**
     * @return \SprykerSdk\Integrator\Manifest\ManifestReaderInterface
     */
    public function createManifestReader(): ManifestReaderInterface
    {
        return new ManifestReader($this->createComposerLockReader(), $this->getConfig());
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createWirePluginManifestStrategy(): ManifestStrategyInterface
    {
        return new WirePluginManifestStrategy(
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createUnwirePluginManifestStrategy(): ManifestStrategyInterface
    {
        return new UnwirePluginManifestStrategy(
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createWireWidgetManifestStrategy(): ManifestStrategyInterface
    {
        return new WireWidgetManifestStrategy(
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createUnwireWidgetManifestStrategy(): ManifestStrategyInterface
    {
        return new UnwireWidgetManifestStrategy(
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createConfigureModuleManifestStrategy(): ManifestStrategyInterface
    {
        return new ConfigureModuleManifestStrategy(
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createCopyFileManifestStrategy(): ManifestStrategyInterface
    {
        return new CopyModuleFileManifestStrategy(
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createConfigureEnvManifestStrategy(): ManifestStrategyInterface
    {
        return new ConfigureEnvManifestStrategy(
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createWireGlueRelationshipManifestStrategy(): ManifestStrategyInterface
    {
        return new WireGlueRelationshipManifestStrategy($this->getConfig());
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createUnwireGlueRelationshipManifestStrategy(): ManifestStrategyInterface
    {
        return new UnwireGlueRelationshipManifestStrategy($this->getConfig());
    }

    /**
     * @return \SprykerSdk\Integrator\ManifestStrategy\ManifestStrategyInterface
     */
    public function createExecuteConsoleManifestStrategy(): ManifestStrategyInterface
    {
        return new ExecuteConsoleManifestStrategy(
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassWriter\ClassFileWriterInterface
     */
    public function createClassFileWriter(): ClassFileWriterInterface
    {
        return new ClassFileWriter($this->createClassPrinter());
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\Printer\ClassDiffPrinterInterface
     */
    public function createClassDiffPrinter(): ClassDiffPrinterInterface
    {
        return new ClassDiffPrinter($this->createClassPrinter());
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\Printer\ClassPrinter
     */
    public function createClassPrinter(): ClassPrinter
    {
        return new ClassPrinter();
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassResolver\ClassResolverInterface
     */
    public function createClassResolver(): ClassResolverInterface
    {
        return new ClassResolver($this->createClassLoader(), $this->createClassGenerator());
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassLoader\ClassLoaderInterface
     */
    public function createClassLoader(): ClassLoaderInterface
    {
        $lexer = $this->createPhpParserLexer();

        return new ClassLoader(
            $this->createPhpParserParser($lexer),
            $lexer
        );
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassGenerator\ClassGeneratorInterface
     */
    public function createClassGenerator(): ClassGeneratorInterface
    {
        return new ClassGenerator(
            $this->createClassLoader(),
            $this->createClassHelper(),
            $this->createClassBuilderFactory(),
            $this->getConfig()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassModifier\ClassInstanceClassModifierInterface
     */
    public function createClassInstanceClassModifier(): ClassInstanceClassModifierInterface
    {
        return new ClassInstanceClassModifier(
            $this->createCommonClassModifier(),
            $this->createClassNodeFinder(),
            $this->createClassMethodChecker()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassModifier\CommonClassModifierInterface
     */
    public function createCommonClassModifier(): CommonClassModifierInterface
    {
        return new CommonClassModifier(
            $this->createClassNodeFinder(),
            $this->createClassMethodChecker()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassModifier\ClassListModifierInterface
     */
    public function createClassListModifier(): ClassListModifierInterface
    {
        return new ClassListModifier(
            $this->getPhpParserNodeTraverser(),
            $this->createCommonClassModifier(),
            $this->createClassNodeFinder()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassModifier\ClassConstantModifierInterface
     */
    public function createClassConstantModifier(): ClassConstantModifierInterface
    {
        return new ClassConstantModifier(
            $this->createClassNodeFinder()
        );
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\ClassModifier\GlueRelationshipModifierInterface
     */
    public function createGlueRelationshipModifier(): GlueRelationshipModifierInterface
    {
        return new GlueRelationshipModifier(
            $this->getPhpParserNodeTraverser(),
            $this->createCommonClassModifier(),
            $this->createClassNodeFinder(),
            $this->createClassHelper(),
            $this->createClassBuilderFactory()
        );
    }

    /**
     * @return \PhpParser\NodeFinder
     */
    public function createPhpParserNodeFinder(): NodeFinder
    {
        return new NodeFinder();
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\Finder\ClassNodeFinderInterface
     */
    public function createClassNodeFinder(): ClassNodeFinderInterface
    {
        return new ClassNodeFinder();
    }

    /**
     * @return \SprykerSdk\Integrator\Builder\Checker\ClassMethodCheckerInterface
     */
    public function createClassMethodChecker(): ClassMethodCheckerInterface
    {
        return new ClassMethodChecker();
    }

    /**
     * @return \PhpParser\BuilderFactory
     */
    public function createClassBuilderFactory(): BuilderFactory
    {
        return new BuilderFactory();
    }

    /**
     * @return \SprykerSdk\Integrator\Helper\ClassHelperInterface;
     */
    public function createClassHelper(): ClassHelperInterface
    {
        return new ClassHelper();
    }

    /**
     * @return \PhpParser\Parser
     */
    public function createPhpParserParser(?Lexer $lexer = null): Parser
    {
        if (!$lexer) {
            $lexer = $this->createPhpParserLexer();
        }

        return new Php7($lexer);
    }

    /**
     * @return \PhpParser\NodeTraverser
     */
    public function getPhpParserNodeTraverser(): NodeTraverser
    {
        return new NodeTraverser();
    }

    /**
     * @return \PhpParser\Lexer
     */
    public function createPhpParserLexer(): Lexer
    {
        return new Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
            ],
        ]);
    }

    /**
     * @return \SprykerSdk\Integrator\ModuleFinder\ModuleFinderFacadeInterface
     */
    public function getModuleFinderFacade(): ModuleFinderFacadeInterface
    {
        return new ModuleFinderFacade();
    }

    /**
     * @return array<\SprykerSdk\Integrator\\ManifestStrategy\ManifestStrategyInterface>
     */
    public function getManifestExecutorStrategies(): array
    {
        return [
            $this->createWirePluginManifestStrategy(),
            $this->createUnwirePluginManifestStrategy(),
            $this->createWireWidgetManifestStrategy(),
            $this->createUnwireWidgetManifestStrategy(),
            $this->createConfigureModuleManifestStrategy(),
            $this->createCopyFileManifestStrategy(),
            $this->createConfigureEnvManifestStrategy(),
            $this->createWireGlueRelationshipManifestStrategy(),
            $this->createUnwireGlueRelationshipManifestStrategy(),
            $this->createExecuteConsoleManifestStrategy(),
        ];
    }
}
