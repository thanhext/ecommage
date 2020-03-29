<?php
namespace Pulsestorm\Magento2\Cli\Path_From_Class;
use function Pulsestorm\Pestle\Importer\pestle_import;
pestle_import('Pulsestorm\Pestle\Library\input');
pestle_import('Pulsestorm\Pestle\Library\output');
pestle_import('Pulsestorm\Magento2\Cli\Library\getBaseMagentoDir');
pestle_import('Pulsestorm\Magento2\Cli\Library\createClassFilePath');
function getPathFromClass($class, $baseMagentoDir=false)
{
    return createClassFilePath($class, $baseMagentoDir);
}

/**
* Turns a PHP class into a Magento 2 path
* Long
* Description
* @command magento2:path-from-class
*/
function pestle_cli($argv)
{
    $class = input('Enter Class: ', 'Pulsestorm\Helloworld\Model\ConfigSourceProductIdentifierMode');
    output(getPathFromClass($class));
}
