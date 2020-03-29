<?php
namespace Pulsestorm\Magento2\Cli\Generate\Acl;
use function Pulsestorm\Pestle\Importer\pestle_import;
pestle_import('Pulsestorm\Pestle\Library\output');
pestle_import('Pulsestorm\Magento2\Cli\Library\getBaseModuleDir');
pestle_import('Pulsestorm\Magento2\Cli\Xml_Template\getBlankXml');
pestle_import('Pulsestorm\Xml_Library\formatXmlString');
pestle_import('Pulsestorm\Pestle\Library\writeStringToFile');
pestle_import('Pulsestorm\Xml_Library\simpleXmlAddNodesXpath');

/**
* Generates Magento 2 acl.xml
*
* Wrapped by magento2:foo:baz version of command
*
* @command generate-acl
* @argument module_name Which Module? [Pulsestorm_HelloWorld]
* @argument rule_ids Rule IDs? [<$module_name$>::top,<$module_name$>::config,]
*/
function pestle_cli($argv)
{
    extract($argv);    
    $rule_ids = explode(',', $rule_ids);
    $rule_ids = array_filter($rule_ids);
    
    $path = getBaseModuleDir($module_name) . '/etc/acl.xml';
    if(!file_exists($path))
    {
        $xml = simplexml_load_string(getBlankXml('acl'));
        writeStringToFile($path, $xml->asXml());
    }    
    $xml = simplexml_load_file($path);
    
    $xpath = 'acl/resources/resource[@id=Magento_Backend::admin]';
    
    foreach($rule_ids as $id)
    {        
        $id = trim($id);
        $xpath .= '/resource[@id='.$id.',@title=TITLE HERE FOR]';
    }
    simpleXmlAddNodesXpath($xml,$xpath);
    
    writeStringToFile($path, formatXmlString($xml->asXml()));
    output("Created $path");
}

function exported_pestle_cli($argv)
{
    return pestle_cli($argv);
}