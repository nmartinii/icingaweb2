<?php
/* Icinga Web 2 | (c) 2013 Icinga Development Team | GPLv2+ */

namespace Icinga\Clicommands;

use Icinga\Application\Version;
use Icinga\Application\Icinga;
use Icinga\Cli\Loader;
use Icinga\Cli\Command;

/**
 * Shows version for IcingaWeb2 and modules
 *
 * The version command shows version numbers for IcingaWeb2, loaded modules and also PHP.
 *
 * Usage: icingacli --version
 */
class VersionCommand extends Command
{
    protected $defaultActionName = 'show';
    protected $moduleName;
    protected $modules;
    /**
     * Shows version for IcingaWeb2 and modules
     *
     * The version command shows version numbers for IcingaWeb2, PHP and the loaded modules.
     *
     * Usage: icingacli --version
     */
    public function showAction()
    {
        $getVersion = Version::get();
        printf("%-14s %-9s \n", 'Icinga Web 2', $getVersion['appVersion']);
        printf("%-14s %-9s \n", 'PHP-Version', PHP_VERSION);

        //Git Commit if there is one
        if (isset($getVersion['gitCommitID'])) {
            printf("%-14s %-9s \n", 'GitCommitID', $getVersion['gitCommitID']);
        } else {
            echo "No GitCommitID found\n";
        }

        $modules = Icinga::app()->getModuleManager()->loadEnabledModules()->getLoadedModules();

        $maxLength = 0;
        foreach ($modules as $module) {
            $length = strlen($module->getName());
            if ($length > $maxLength) {
                $maxLength = $length;
            }
        }

        $space = '  ';
        printf("%-${maxLength}s ${space} %-9s \n", 'MODULE', 'VERSION');
        foreach ($modules as $module) {
            printf("%-${maxLength}s ${space} %-9s \n", $module->getName(), $module->getVersion());
        }
    }

}
