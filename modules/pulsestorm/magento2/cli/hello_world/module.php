<?php
namespace Pulsestorm\Magento2\Cli\Hello_World;
use function Pulsestorm\Pestle\Importer\pestle_import;

pestle_import('Pulsestorm\Pestle\Library\output');

/**
* A Hello World command.  Hello World!
*
* @command hello-world
* @option service Which branch of the service
*/
function pestle_cli($argv, $options)
{
    $person = 'Sailor';
    if(array_key_exists('service', $options))
    {
        if($options['service'] === 'army')
        {
            $person = 'Soldier';
        }
        
    }
    output("Hello $person");
}
