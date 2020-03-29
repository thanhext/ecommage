<?php
namespace Pulsestorm\Magento2\Cli\Library;
use ReflectionFunction;
use Exception;
use DomDocument;
use function Pulsestorm\Pestle\Importer\pestle_import;
pestle_import('Pulsestorm\Pestle\Library\isAboveRoot');
pestle_import('Pulsestorm\Pestle\Library\output');
pestle_import('Pulsestorm\Pestle\Library\exitWithErrorMessage');
pestle_import('Pulsestorm\Pestle\Library\inputOrIndex');
pestle_import('Pulsestorm\Pestle\Library\writeStringToFile');
pestle_import('Pulsestorm\Pestle\Library\input');
pestle_import('Pulsestorm\Pestle\Library\bail');
pestle_import('Pulsestorm\Pestle\Library\getClassFromDeclaration');
pestle_import('Pulsestorm\Pestle\Library\getExtendsFromDeclaration');
pestle_import('Pulsestorm\Pestle\Library\getNewClassDeclaration');
pestle_import('Pulsestorm\Cli\Code_Generation\createClassTemplate');
pestle_import('Pulsestorm\Xml_Library\addSchemaToXmlString');
pestle_import('Pulsestorm\Xml_Library\getXmlNamespaceFromPrefix');
pestle_import('Pulsestorm\Pestle\Config\loadConfig');
pestle_import('Pulsestorm\Pestle\Config\saveConfig');

function getAppCodePath() {
    return 'app/code';
}

function getModuleAutoloaderPathFromComposerFile($module_name, $path_composer) {
    if(!file_exists($path_composer)) {
        throw new Exception("Could not find $path_composer");
    }

    $composer = json_decode(file_get_contents($path_composer));

    $parts = explode('_', $module_name);
    $moduleClassPrefix = $parts[0] . '\\' . $parts[1] . '\\';
    $autoloadPath = false;
    if(!isset($composer->autoload) || !isset($composer->autoload->{'psr-4'})) {
        throw new Exception("No psr-4 autoload section in $path_composer");
    }
    foreach($composer->autoload->{'psr-4'} as $prefix=>$path) {
        if($prefix === $moduleClassPrefix) {
            $autoloadPath = $path;
        }
    }
    // if blank path, use ./
    $autoloadPath = trim($autoloadPath) ? $autoloadPath : './';

    return rtrim($autoloadPath, '/');
}

/**
 * if module is part of the package-folders config, then use the
 * configured value.  Also, ensure that the folder we're pointing
 * is actually part of the Magento system we're in
 */
function getModuleInformationFolderWhenConfigured($path_magento_base, $configured_path, $module_name) {
    if(strpos($path_magento_base, $configured_path)) {
        $message = "Configured Path is not in Magento folder\n" .
            "Path: ".$configured_path."\n" .
            "Magento Path: ".$path_magento_base."\n\n";
        throw new Exception($message);
    }

    // find composer autoload path
    $pathComposer = $configured_path . '/composer.json';
    $autoloadPath = getModuleAutoloaderPathFromComposerFile($module_name, $pathComposer);

    if(!$autoloadPath) {
        throw new Exception("Could not find autoload path in $pathComposer");
    }

    return rtrim(preg_replace(
        '%' . $path_magento_base . '/%', '', $configured_path, 1
    ), '/');
}

function getModuleInformation($module_name, $path_magento_base=false)
{
    $path_magento_base = $path_magento_base ? $path_magento_base : getBaseMagentoDir();

    list($vendor, $name) = explode('_', $module_name);
    $information = [
        'vendor'        => $vendor,
        'short_name'    => $name,
        'name'          => $module_name,
    ];


    // we need to check the configuration for a path.  If it exists, then
    // we need to return a different `folder` value.
    $config = loadConfig('package-folders');

    if(isset($config->{$module_name})) {
        $folderPackageBase = getModuleInformationFolderWhenConfigured(
            $path_magento_base, $config->{$module_name}, $module_name);

        $pathComposer = $config->{$module_name} . '/composer.json';
        $information['folder_relative'] = $folderPackageBase . '/' .
            getModuleAutoloaderPathFromComposerFile($module_name, $pathComposer);
        $information['folder_package_relative'] = $folderPackageBase;

    } else {
        $information['folder_relative']  = getAppCodePath() . "/$vendor/$name";
        $information['folder_package_relative'] = $information['folder_relative'];
    }

    $information['folder']           =
        $path_magento_base . "/" . $information['folder_relative'];
    $information['folder_package']           =
        $path_magento_base . "/" . $information['folder_package_relative'];
    return (object) $information;
}

function getBaseModuleDir($module_name)
{
    $path = getModuleInformation($module_name)->folder;
    if(!file_exists($path))
    {
        exitWithErrorMessage("No such path: $path" . "\n" .
            "Please use magento2:generate:module to create module first");
        // throw new Exception("No such path: $path");
    }
    return $path;
}

function askForModuleAndReturnInfo($argv, $index=0)
{
    $module_name = inputOrIndex(
        "Which module?",
        'Magento_Catalog', $argv, $index);
    return getModuleInformation($module_name);
}

function getRelativeModulePath($packageName, $moduleName, $magentoBase=false) {
    $information = getModuleInformation(
        implode('_', [$packageName, $moduleName]), $magentoBase);

    return $information->folder_relative;
}

function getFullModulePath($packageName, $moduleName, $magentoBase=false) {
    $information = getModuleInformation(
        implode('_', [$packageName, $moduleName]), $magentoBase);

    return $information->folder;
}

function askForModuleAndReturnFolder($argv)
{
    $module_folder = inputOrIndex(
        "Which module?",
        'Magento_Catalog', $argv, 0);
    list($package, $vendor) = explode('_', $module_folder);
    return getFullModulePath($package, $vendor);
}

function getBaseMagentoDir($path=false)
{
    if($path && isAboveRoot($path))
    {
        output("Could not find base Magento directory");
        exit;
    }

    $path = $path ? $path : getcwd();
    if(file_exists($path . '/app/etc/di.xml'))
    {
        return realpath($path);
    }
    return getBaseMagentoDir($path . '/..');
    // return $path;
}

function getModuleBaseDir($module, $baseMagentoDir=false)
{
    list($package, $module) = explode('_', $module);
    return getFullModulePath($package, $module, $baseMagentoDir);
}

function getModuleConfigDir($module)
{
    return implode('/', [
        getModuleBaseDir($module),
        'etc']);
}

function initilizeModuleConfig($module, $file, $xsd)
{
    $path = implode('/', [
        getModuleConfigDir($module),
        $file]);

    if(file_exists($path))
    {
        return $path;
    }

    $xml = addSchemaToXmlString('<config></config>', $xsd);
    $xml = simplexml_load_string($xml);

    if(!is_dir(dirname($path)))
    {
        mkdir(dirname($path), 0777, true);
    }
    writeStringToFile($path, $xml->asXml());

    return $path;
}

function getSimpleTreeFromSystemXmlFile($path)
{
    $tree = [];
    $xml = simplexml_load_file($path);
    foreach($xml->system->section as $section)
    {
        $section_name        = (string) $section['id'];
        $tree[$section_name] = [];

        foreach($section->group as $group)
        {
            $group_name = (string) $group['id'];
            $tree[$section_name][$group_name] = [];
            foreach($group->field as $field)
            {
                $tree[$section_name][$group_name][] = (string) $field['id'];
            }
        }
    }
    return $tree;
}

function createClassFilePath($model_class_name, $baseMagentoDir=false) {
    $baseMagentoDir = $baseMagentoDir ? $baseMagentoDir : getBaseMagentoDir();
    $parts = explode('\\', $model_class_name);
    $information = getModuleInformation(
        implode('_', [array_shift($parts), array_shift($parts)]), $baseMagentoDir);
    $path = $information->folder . '/' . implode('/', $parts) . '.php';
    return $path;
}

function createClassFile($model_name, $contents)
{
    $path = createClassFilePath($model_name);
    if(file_exists($path))
    {
        output($path, "\n" . 'File already exists, skipping');
        return;
    }
    if(!is_dir(dirname($path)))
    {
        mkdir(dirname($path), 0755, true);
    }
    file_put_contents($path, $contents);
}

function resolveAlias($alias, $config, $type='models')
{
    if($type[strlen($type)-1] !== 's')
    {
        $type .='s';
    }
    if(strpos($alias, '/') === false)
    {
        return $alias;
    }
    list($group, $model) = explode('/', $alias);
    $prefix = (string)$config->global->{$type}->{$group}->class;

    $model = str_replace('_', ' ', $model);
    $model = ucwords($model);
    $model = str_replace(' ', '_', $model);

    $mage1 = $prefix . '_' . $model;
    return str_replace('_','\\',$mage1);
}

function convertObserverTreeScoped($config, $xml)
{
    $xml_new = simplexml_load_string('<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd"></config>');
    if(!$config->events)
    {
        return $xml_new;
    }

    foreach($config->events->children() as $event)
    {
        $event_name = modifyEventNameToConvertFromMage1ToMage2($event->getName());
        $event_xml  = $xml_new->addChild('event');
        $event_xml->addAttribute('name',$event_name);

        foreach($event->observers->children() as $observer)
        {
            //<observer name="check_theme_is_assigned" instance="Magento\Theme\Model\Observer" method="checkThemeIsAssigned" />
            //shared = false
            $observer_xml = $event_xml->addChild('observer');
            $observer_xml->addAttribute('name', $observer->getName());
            $observer_xml->addAttribute('instance', resolveAlias((string) $observer->{'class'}, $xml));
            $observer_xml->addAttribute('method', (string) $observer->method);
            if( (string) $observer->type === 'model')
            {
                $observer_xml->addAttribute('shared','false');
            }
        }
    }

    return $xml_new;
}

function modifyEventNameToConvertFromMage1ToMage2NoAdminhtml($name)
{
    $parts = explode('_', $name);
    $parts = array_filter($parts, function($part){
        return $part !== 'adminhtml';
    });
    return implode('_', $parts);
}

function modifyEventNameToConvertFromMage1ToMage2($name)
{
    $name = modifyEventNameToConvertFromMage1ToMage2NoAdminhtml($name);
    return $name;
}

function getMage1ClassPathFromConfigPathAndMage2ClassName($path, $class)
{
    $path_from_pool = $path;
    $pools = ['community','core','local'];
    foreach($pools as $pool)
    {
        $path_from_pool = preg_replace('%^.*app/code/'.$pool.'/%','',$path_from_pool);
    }

    $parts_mage_2 = explode('\\',$class);
    $mage2_vendor = $parts_mage_2[0];
    $mage2_module = $parts_mage_2[1];

    $parts_mage_1 = explode('/', $path_from_pool);
    $mage1_vendor = $parts_mage_1[0];
    $mage1_module = $parts_mage_1[1];

    if( ($mage1_vendor !== $mage2_vendor) || $mage1_module !== $mage2_module)
    {
        throw new Exception('Config and alias do not appear to match');
    }

    $path_from_pool_parts = explode('/',$path);
    $new = [];
    for($i=0;$i<count($path_from_pool_parts);$i++)
    {
        $part = $path_from_pool_parts[$i];

        if($part === $mage1_vendor && $path_from_pool_parts[$i+1] == $mage1_module)
        {
            $new[] = str_replace('\\','/',$class) . '.php';
            break;
        }
        $new[] = $part;
    }

    return implode('/',$new);
}

function getVariableNameFromNamespacedClass($class)
{
    $parts = explode('\\', $class);
    $parts = array_slice($parts, 2);

    $var = implode('', $parts);

    if($var)
    {
        $var[0] = strToLower($var);
    }

    return '$' . $var;
}

function getDiLinesFromMage2ClassName($class, $var=false)
{
    if(!$var)
    {
        $var  = getVariableNameFromNamespacedClass($class);
    }
    $parameter  = '\\' . trim($class,'\\') . ' ' . $var . ',';
    $property   = 'protected ' . $var . ';';
    $assignment = '$this->' . ltrim($var, '$') . ' = ' . $var . ';';

    $lines = $parameter;

    return [
        'property' =>$property,
        'parameter'=>$parameter,
        'assignment'=>$assignment
    ];
}

function getKnownClassMap()
{
    return ['Mage\Core\Helper\Abstract'=>'Magento\Framework\App\Helper\AbstractHelper'];
}

function getKnownClassesMappedToNewClass($return)
{
    $full_class = $return['namespace'] . '\\' . $return['class'];
    $map = getKnownClassMap();
    // echo $full_class,"\n";
    if(!array_key_exists($full_class, $map))
    {
        return $return;
    }

    $parts = explode('\\', $map[$full_class]);

    $return = [
        'class'     =>array_pop($parts),
        'namespace' =>implode('\\',$parts),

    ];
    return $return;
}

function getNamespaceAndClassDeclarationFromMage1Class($class, $extends='')
{
    $parts = explode('_', $class);
    $return = [
        'class'     =>array_pop($parts),
        'namespace' =>implode('\\',$parts),

    ];

    $return = getKnownClassesMappedToNewClass($return);

    $return['full_class'] = $return['namespace'] . '\\' . $return['class'];
    return $return;
}

function convertMageOneClassIntoNamespacedClass($path_mage1)
{
    $text = file_get_contents($path_mage1);
    preg_match('%class.+?(extends)?.+?\{%', $text, $m);
    if(count($m) === 0)
    {
        throw new Exception("Could not extract class declaration");
    }
    $declaration = $m[0];
    if(strpos($declaration, 'implements'))
    {
        throw new Exception("Can't handle implements yet, but should be easy to add");
    }
    $class   = getNamespaceAndClassDeclarationFromMage1Class(
        getClassFromDeclaration($declaration));
    $extends = getNamespaceAndClassDeclarationFromMage1Class(
        getExtendsFromDeclaration($declaration));

    $declaration_new = getNewClassDeclaration($class, $extends);

    $text = str_replace($declaration, $declaration_new, $text);
    return $text;
}

function inputModuleName()
{
    return input("Which module?", 'Packagename_Vendorname');
}

function addSpecificChild($childNodeName, $node, $name, $type, $text=false)
{
    $namespace = getXmlNamespaceFromPrefix($node, 'xsi');
    $child = $node->addChild($childNodeName);
    $child->addAttribute('name',$name);
    $child->addAttribute('xsi:type',$type,$namespace);
    if($text)
    {
        $child[0] = $text;
    }
    return $child;
}

function addArgument($node, $name, $type, $text=false)
{
    return addSpecificChild('argument', $node, $name, $type, $text);
}

function addItem($node, $name, $type, $text=false)
{
    return addSpecificChild('item', $node, $name, $type, $text);
}

function validateAs($xml, $type)
{
    if($xml->getName() !== $type)
    {
        output("Not a <$type/> node, looks like a <{$xml->getName()}/> node, bailing.");
        exit;
    }

}

function validateAsListing($xml)
{
    return validateAs($xml, 'listing');
}

function getOrCreateColumnsNode($xml)
{
    $columns = $xml->columns;
    if(!$columns)
    {
        $columns = $xml->addChild('columns');
    }
    return $columns;
}

/**
* Not a command, just library functions
* @command library
*/
function pestle_cli($argv)
{
}
