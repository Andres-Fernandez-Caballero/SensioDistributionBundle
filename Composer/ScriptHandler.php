<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sensio\Bundle\DistributionBundle\Composer;

use Symfony\Component\ClassLoader\ClassCollectionLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Composer\Script\Event;
use Composer\Util\ProcessExecutor;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class ScriptHandler
{
    /**
     * Composer variables are declared static so that an event could update
     * a composer.json and set new options, making them immediately available
     * to forthcoming listeners.
     */
    protected static $options = array(
        'symfony-app-dir' => 'app',
        'symfony-web-dir' => 'web',
        'symfony-assets-install' => 'hard',
        'symfony-cache-warmup' => false,
    );

    /**
     * Returns options from the composer instance.
     *
     * @param Event $event
     * @return array
     */
    protected static function getOptions(Event $event)
    {
        $options = array_merge(static::$options, $event->getComposer()->getPackage()->getExtra());

        return $options;
    }

    /**
     * Asks if the new directory structure should be used, installs the structure if needed.
     *
     * @param Event $event
     */
    public static function defineDirectoryStructure(Event $event)
    {
        $options = static::getOptions($event);

        if (!getenv('SENSIOLABS_ENABLE_NEW_DIRECTORY_STRUCTURE') || !$event->getIO()->askConfirmation('Would you like to use Symfony 3 directory structure? [y/N] ', false)) {
            return;
        }

        $rootDir = getcwd();
        $appDir = $options['symfony-app-dir'];
        $webDir = $options['symfony-web-dir'];
        $binDir = static::$options['symfony-bin-dir'] = 'bin';
        $varDir = static::$options['symfony-var-dir'] = 'var';

        static::updateDirectoryStructure($event, $rootDir, $appDir, $binDir, $varDir, $webDir);
    }

    // El resto de los métodos (buildBootstrap, hasDirectory, prepareDeploymentTarget, etc.) siguen aquí...

    protected static function executeCommand(Event $event, $consoleDir, $cmd, $timeout = 300)
    {
        $php = ProcessExecutor::escape(static::getPhp(false));
        $phpArgs = implode(' ', array_map([ProcessExecutor::class, 'escape'], static::getPhpArguments()));
        $console = ProcessExecutor::escape($consoleDir.'/console');
        if ($event->getIO()->isDecorated()) {
            $console .= ' --ansi';
        }

        $process = new Process([$php, $phpArgs, $console, $cmd], null, null, null, $timeout);
        $process->run(function ($type, $buffer) use ($event) {
            $event->getIO()->write($buffer, false);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                "An error occurred when executing the \"%s\" command:\n\n%s\n\n%s",
                ProcessExecutor::escape($cmd),
                self::removeDecoration($process->getOutput()),
                self::removeDecoration($process->getErrorOutput())
            ));
        }
    }

    protected static function getPhp($includeArgs = true)
    {
        $phpFinder = new PhpExecutableFinder();
        $phpPath = $phpFinder->find($includeArgs);

        if (false === $phpPath) {
            throw new \RuntimeException('Unable to find PHP binary.');
        }

        return $phpPath;
    }

    protected static function getPhpArguments()
    {
        $phpFinder = new PhpExecutableFinder();
        return $phpFinder->findArguments();
    }

    protected static function removeDecoration($str)
    {
        return preg_replace('/\033\[[^m]*m/', '', $str);
    }
}
