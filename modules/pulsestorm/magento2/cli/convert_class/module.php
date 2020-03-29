<?php
namespace Pulsestorm\Magento2\Cli\Convert_Class;
use function Pulsestorm\Pestle\Importer\pestle_import;
pestle_import('Pulsestorm\Pestle\Library\input');
pestle_import('Pulsestorm\Pestle\Library\output');
pestle_import('Pulsestorm\Magento2\Cli\Library\resolveAlias');
pestle_import('Pulsestorm\Magento2\Cli\Library\getMage1ClassPathFromConfigPathAndMage2ClassName');
pestle_import('\Pulsestorm\Magento2\Cli\Library\convertMageOneClassIntoNamespacedClass');
pestle_import('\Pulsestorm\Magento2\Cli\Library\getDiLinesFromMage2ClassName');

/**
* ALPHA: Partially converts Magento 1 class to Magento 2
* Long
* Description
* @command magento2:convert-class
*/
function pestle_cli($argv)
{
    $type        = input("Which type (model, helper, block)?", 'model');
    $alias       = input("Which alias?", 'pulsestorm_helloworld/observer_newsletter');    
    $path_config = input("Which config.xml?", 'app/code/community/Pulsestorm/Helloworld/etc/config.xml');
    
    $config      = simplexml_load_file($path_config);
    $class       = resolveAlias($alias, $config, $type);
    // output($class);
    $mage_1_path = getMage1ClassPathFromConfigPathAndMage2ClassName($path_config, $class);
    $mage_2_path = str_replace(['/core','/community','/local'], '', $mage_1_path);
    
    
    output('');
    output("New Class Path");
    output('-----------------------');
    output($mage_2_path);
    output('');
    
    output("New Class Content");
    output('-----------------------');
    output(convertMageOneClassIntoNamespacedClass($mage_1_path));
    output('');
    
    output("DI Lines");
    output('-----------------------');
    output(implode("\n", getDiLinesFromMage2ClassName($class)));
    output('');    
}
