<?php
/**
 * @category   Netz98
 * @package    Netz98_ModuleCreator
 * @author	   Daniel Nitz <d.nitz@netz98.de>
 * @copyright  Copyright (c) 2008-2009 netz98 new media GmbH (http://www.netz98.de)
 * 			   Credits for blank files go to alistek, Barbanet (contributer), Somesid (contributer) from the community:
 * 			   http://www.magentocommerce.com/wiki/custom_module_with_custom_database_table
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * $Id$
 */

$mageFilename = '../app/Mage.php';

if (!file_exists($mageFilename)) {
    echo $mageFilename." was not found";
    exit;
}

require_once $mageFilename;

Mage::setIsDeveloperMode(true);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Berlin');

umask(0);
Mage::app();


$session = new Netz98_Admin_Model_Session();
$session->start();

if (!empty($_POST)) {
	if(isset($_POST['form']) && $_POST['form'] == 'login') {

		$request = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		$session->login($_POST['login']['username'], $_POST['login']['password'], $request);
	}
}
if(isset($_GET['logout']) && $_GET['logout'] == 'yes') {
	$session->clear();
}
if (!$session->isLoggedIn()) {
	print getHeader()
	    . getLoginBox()
	    . getFooter();
	exit;
}

$root = dirname(__FILE__) . DS;
$shop = null;
define('TEMPLATES_DIR', 'Templates');

//--------------------------------------------------------------

/**
 * Enter description here...
 *
 * @return string
 */
function getLoginBox()
{
	return '
		<div style="width:300px; padding:20px; margin:90px auto !important; background:#f6f6f6;">
			<form method="post" action="'.$_SERVER['PHP_SELF'].'"  id="loginForm">
			    <h2 class="page-head">Log In</h2>
			    <p><small>Please re-enter your Magento Adminstration Credentials.<br/>Only administrators with full permissions will be able to log in.</small></p>
			    <table class="form-list">
			        <tr><td class="label"><label for="username">Username:</label></td><td class="value"><input id="username" name="login[username]" value=""/></td></tr>
			        <tr><td class="label"><label for="password">Password:</label></td><td class="value"><input type="password" id="password" name="login[password]"/></td></tr>
			        <tr><td></td>
			            <td class="value"><button type="submit">Log In</button></td></tr>
			        </table>
			    <input type="hidden" name="form" value="login" />
			</form>
			</div>
	';
}

/**
 * Enter description here...
 *
 * @param string|array $from
 * @param string|array $to
 * @return boolean
 */
function copyBlankoFiles($from, $to, $shop = null)
{
    global $root;

    if (!is_array($from)) {
        $from = array($from);
    }

    if (!is_array($to)) {
        $to = array($to);
    }

    if ($shop === null) {
        $shop = $root . 'new/';
        if (!is_dir($shop)) {
            mkdir($shop);
        }
    }

    if (count($from) !== count($to)) {
        throw new Exception('Count of from -> to files do not match.');
    }

    foreach ($to as $file) {
        $newPath = substr($file, 0, strrpos($file, '/'));
        createFolderPath($newPath, $shop);
    }

    for ($i = 0; $i < count($to); $i++) {
        if (copy($root.$from[$i], $shop.$to[$i]) === false) {
            throw new Exception('Could not copy blanko files.');
        }
    }
    return true;
}

/**
 * Enter description here...
 *
 * @param string|array $paths
 * @return bolean
 */
function createFolderPath($paths, $shop = null)
{
    global $root;

    if (!is_array($paths)) {
        $paths = array($paths);
    }

    if ($shop === null) {
        $shop = $root;
    }

    foreach ($paths as $path) {
        $folders = explode('/', $path);
        $current = '';

        foreach ($folders as $folder) {
            $fp = $current . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($shop.$fp)) {
                //var_dump($shop.$fp);
                if (mkdir($shop.$fp) === false) {

                    throw new Exception('Could not create new path: '. $shop.$fp);
                }
            }
            $current = $fp;
        }
    }
    return true;
}

/**
 * Enter description here...
 *
 * @param array|string $files
 */
function insertCustomVars($files, $shop = null, $formBddTable)
{
    global $root;

    if (!is_array($files)) {
        $files = array($files);
    }

    if ($shop === null) {
        $shop = $root . 'new' . DIRECTORY_SEPARATOR;
    }

    foreach ($files as $file) {
        $handle = fopen ($shop.$file, 'r+');
        $content = '';
        while (!feof($handle)) {
            $content .= fgets($handle);
        }
        fclose($handle);

        $type = strrchr($file, '.');
        switch ($type) {
            case '.xml':
                $content = replaceXml($content);
                break;
            case '.php':
            case '.phtml':
                $content = replacePhp($content, $formBddTable);
                break;
            case '.csv':
            	$content = replaceCsv($formBddTable);
			break;
            default:
                throw new Exception('Unknown file type found: '.$type);
        }
        $handle = fopen ($shop.$file, 'w');
        fputs($handle, $content);
        fclose($handle);
    }
}

/**
 * Enter description here...
 *
 * @param string $content
 * @return string
 */
function replacePhp($content, $formBddTable)
{
    global $vars;


    $search = array(
                    '/<Namespace>/',
                    '/<namespace>/',
                    '/<Module>/',
                    '/<module>/',
                    '/<Model>/',
                    '/<model>/',
                    '/<table>/',
                    '/<table_create>/',
                    '/<table_drop>/',
                    '/<table_pk>/',
                    '/<Adminhtml_Block_Widget_Grid::_prepareColumns>/',
                    '/<Adminhtml_Block_Widget_Tab_Form::_prepareForm>/',
    				'/<Adminhtml_Block_Widget_Grid::_prepareMassaction>/',
    				'/<Adminhtml_Block_Widget_Grid::_prepareColumns-exporttype>/',
    				'/<Module_Model_Module::enumtooptionarray>/'
   					);

    $replace = array(
                    $vars['capNamespace'],
                    $vars['lowNamespace'],
                    $vars['capModule'],
                    $vars['lowModule'],
                    $vars['capModel'],
                    $vars['lowModel'],
                    $vars['bddtable'],
    				getReplace_prepareSQL($vars),
                    $vars['bddtabledrop'],
                    $vars['bddtablepk'],
                    getReplace_prepareColumns($vars),
                    getReplace_prepareTabForms($vars),
    				getReplace_prepareMassaction($vars),
    				getReplace_prepareMassactionExportType($vars),
    				getReplace_enumToOptionArray($vars)
                    );
    return preg_replace($search, $replace, $content);
}


/**
 * Enter description here...
 *
 * @param string $content
 * @return string
 */
function replaceXml($content)
{
	global $vars;

	$search = array(
			'/\[Namespace\]/',
			'/\[namespace\]/',
			'/\[Module\]/',
			'/\[module\]/',
			'/\[Model\]/',
			'/\[model\]/',
			'/\[table\]/',
	);

	$replace = array(
			$vars['capNamespace'],
			$vars['lowNamespace'],
			$vars['capModule'],
			$vars['lowModule'],
			$vars['capModel'],
			$vars['lowModel'],
			$vars['bddtable'],
	);

	return preg_replace($search, $replace, $content);
}

/**
 * Enter description here...
 *
 * @param string $content
 * @return string
 */
function replaceCsv($formBddTable)
{
	global $vars;

	$content='';

	foreach($vars['bddtablecols'] as $colId=>$colVal)
    {
    	if(!empty($colVal['label_from_bdd']))
			$content.="\"".$colVal['label_from_bdd']."\",\"".$colVal['label_from_bdd']."\"\n";
	}

	return $content;

}



function getReplace_prepareColumns($vars)
{
    $replacement= array();
    foreach($vars['bddtablecols'] as $colId=>$colVal)
    {
        if( !empty($colVal['Key']) and  $colVal['Key']=='PRI' )
            continue;

        if(substr($colId,0,3)=='is_')
        {
        	$replacement[$colId]= "\$this->addColumn('".$colId."',
        											 array('header'=> Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
        			'index'     => '".$colId."',
        			'type'      => 'options',
        			'options'   => Mage::getSingleton('".$vars['lowNamespace']."_".$vars['lowModule']."/status')->getOptionArray(),
      ));";
        }elseif($colId=='country'){
            $replacement[$colId]= "\$this->addColumn('".$colId."', array('header'    => Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
            							  'align'     =>'left',
            							  'index'     => '".$colId."',
            							  'type'      => 'country',
            ));";
        }elseif($colId=='store_id'){
                $replacement[$colId]= "\$this->addColumn('".$colId."', array('header'    => Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
                'align'     =>'left',
                'index'     => '".$colId."',
                'type' => 'store',
                'store_view'=> true,
                ));";
        }elseif(substr($colId,-3)=='_id'){
            //dont display column
        }else{
        $replacement[$colId]= "\$this->addColumn('".$colId."', array('header'    => Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
          								  'align'     =>'left',
          								  'index'     => '".$colId."',
      ));";
        }


    }
    return implode(PHP_EOL.PHP_EOL.'      ', $replacement);
}

function getReplace_prepareTabForms($vars)
{
	$issetDate=false;
    $replacement= array();
    foreach($vars['bddtablecols'] as $colId=>$colVal)
    {
        if( !empty($colVal['Key']) and  $colVal['Key']=='PRI' )
            continue;

        if (substr($colId,0,3)=='is_') { //----Si c'est un booléan
        	$replacement[$colId]= "\$fieldset->addField('".$colId."',
        	 'select', array(
        			'name'  	=> '".$colId."',
        			'label' 	=> Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
        			'class' 	=> 'input-select',
        			'options'	=> Mage::getSingleton('".$vars['lowNamespace']."_".$vars['lowModule']."/status')->getOptionArray()
        	));";
        } elseif ($colId=='country') {
        	$replacement[$colId]= "\$fieldset->addField('".$colId."',
        	     'select', array(
        	    'name'  	=> '".$colId."',
        	    'label' 	=> Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
        	    'class' 	=> 'input-select',
        	    'values'	=> Mage::getModel('adminhtml/system_config_source_country')->toOptionArray()
        	    ));";
        } elseif ($colId=='store_id') {
        	    $replacement[$colId]= "\$fieldset->addField('".$colId."',
        	    'select', array(
        	    'name'  	=> '".$colId."',
        	    'label' 	=>  Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
        	    'class' 	=> 'input-select',
        	    'values'	=>  Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm()
        	    ));";
        }elseif(array_key_exists('model',$colVal)){ //-------- Si c'est une clé étrangère
        	$model = "Mage::getModel('".$colVal['model']."')->getCollection()->toOptionArray()";

        	$replacement[$colId]= "/*\$fieldset->addField('".$colId."', 'select', array(
        	'name'  	=> '".$colId."',
        	'label' 	=> Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
        	'class' 	=> 'input-select',
        	'options'	=> ".$model."
        	));*/";

        } else{
        	$type=getTypeClear($colVal['Type']);



        	switch ($type['title']){
        		case "date":
        			if(!$issetDate){
	        			$replacement[$colId]= "\$outputFormat = Mage::app()->getLocale()->getDateFormatWithLongYear();";
	        			$issetDate=true;
        			}else{
        			  $replacement[$colId]='';
        			}
        			$replacement[$colId].= "\$fieldset->addField('".$colId."', 'date', array(
				        'label'     => Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
		        		'name'      =>    '".$colId."',
		        		'time'      =>    true,
		        		'format'    =>    \$outputFormat,
		        		'image'     =>    \$this->getSkinUrl('images/grid-cal.gif')
				      ));";
        		break;
        		case "enum":
        			$replacement[$colId]= "\$fieldset->addField('".$colId."', 'select', array(
				          'label'     => Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
				          'required'  => false,
				          'name'      => '".$colId."',
				          'class' 	=> 'input-select',
				          'values'    => Mage::getModel('".$vars['lowNamespace']."_".$vars['lowModule']."/".$vars['lowModel']."')->enumToOptionArray('".$colId."')
				      ));";
        		break;
        		default:
        			$replacement[$colId]= "\$fieldset->addField('".$colId."', 'text', array(
				          'label'     => Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('".$colVal['label_from_bdd']."'),
				          //'class'     => 'required-entry',
				          //'required'  => true,
				          'required'  => false,
				          'name'      => '".$colId."'
				      ));";

        	}
        }

    }
    return implode(PHP_EOL.PHP_EOL.'      ', $replacement);
}


function getReplace_prepareMassaction($vars)
{

	if($vars['usestatus'])
	{
		$replacement = "\$statuses = Mage::getSingleton('".$vars['lowNamespace']."_".$vars['lowModule']."/status')->getOptionArray();
		array_unshift(\$statuses, array('label'=>'', 'value'=>''));

		\$this->getMassactionBlock()->addItem('status', array(
				'label'=> Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('Change status'),
				'url'  => \$this->getUrl('*/*/massStatus', array('_current'=>true)),
				'additional' => array(
						'visibility' => array(
								'name' => 'status',
								'type' => 'select',
								'class' => 'required-entry',
								'label' => Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('Status'),
								'values' => \$statuses
						)
				)
		));";
		return $replacement;
	}
	return '';
}


function getReplace_prepareMassactionExportType($vars)
{
	if($vars['export'])
	{
		$replacement = "
			\$this->addExportType('*/*/exportCsv', Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('CSV'));
			\$this->addExportType('*/*/exportXml', Mage::helper('".$vars['lowNamespace']."_".$vars['lowModule']."')->__('XML'));
		";
		return $replacement;
	}
	return '';
}

/**
 * Récupération du type, exemple : enum('Male','Femelle')=>ENUM, int(11)=>INT
 *
 * @param string $type
 * @return array
 */

function getTypeClear($type){
	$result=array();

	$result['title'] = preg_replace('#^([a-z]+)(\(.+)#i', '$1', $type);
		if ($result['title']=='enum' and preg_match('#\((.+)\)$#isU', $type)){
			$result['values']=explode(',',preg_replace('#\w+\((.+)\)$#isU', '$1', $type));
		}
		if(preg_match('#\w+\(([0-9]+)\)$#isU', $type, $size))
			$result['size']=$size[1];
		else
			$result['size']='null';


	return $result;
}
/**
 * Récupération du type, exemple : enum('Male','Femelle')=>ENUM, int(11)=>INT
 *
 * @param string $type
 * @return array
 */

function getFormatToBDD($type){
	$title=$type['title'];

	switch ($title){
		case 'int':
			$type['title']='INTEGER';
		break;
		case 'enum':
			$type['title']='VARCHAR';
			$type['size']=255;
		break;
		default:
			$type['title']=strtoupper($title);
	}
	return $type;
}


/**
 * Création fonction dans le model contenant les enum
 *
 * @param array
 * @return string
 */

function getReplace_enumToOptionArray($vars){
	$issetDate=false;
	$replacement= array();
	$i=0;
	$replacement['begin']="public function enumToOptionArray(\$colname){
		\$result=array(";


	foreach($vars['bddtablecols'] as $colId=>$colVal)
	{
		if (preg_match('#enum\((.+)\)$#isU', $colVal['Type'])){
			$enumValues = explode(',',preg_replace('#enum\((.+)\)$#isU', '$1', $colVal['Type']));


			$replacement[$colId]="'".$colId."' =>
					array(
							";
				foreach($enumValues as $k=>$v){
					$replacement[$colId].="array('label'=>".$v.",'value'=>".$v."),
							";
				}
			$replacement[$colId].='),';

			$i++;
		}

    }
    $replacement['end']=');
    return $result[$colname];
	}';
    if($i>0)
    	return implode(PHP_EOL.PHP_EOL.'      ', $replacement);
    else
        return null;
}



/**
 * Création de la couche donnée SQL
 *
 * @param array
 * @return string
 */
function getReplace_prepareSQL($vars){

    $replacement[]="\$installer->getConnection()->dropTable(\$installer->getTable('".$vars['lowNamespace']."_".$vars['lowModule']."/".$vars['lowModel']."'));";
	$replacement[]="\$table = \$installer->getConnection()
	->newTable(\$installer->getTable('".$vars['lowNamespace']."_".$vars['lowModule']."/".$vars['lowModel']."'))";
    foreach($vars['bddtablecols'] as $colId=>$colVal)
    {
    	$type=getTypeClear($colVal['Type']);
    	$formatToBDD=getFormatToBDD($type);


    	if($colVal['Key']=='PRI'){
    		$replacement[]="->addColumn('".$colVal['Field']."', Varien_Db_Ddl_Table::TYPE_".$formatToBDD['title'].", ".$formatToBDD['size'].", array(
		        'identity'  => true,
		        'unsigned'  => true,
		        'nullable'  => false,
		        'primary'   => true,
    		), '".$colVal['label_from_bdd']."')";
    	}else{
    		$replacement[]="->addColumn('".$colVal['Field']."', Varien_Db_Ddl_Table::TYPE_".$formatToBDD['title'].", ".$formatToBDD['size'].", array(
    		'unsigned'  => true,
    		'nullable'  => false,
    		), '".$colVal['label_from_bdd']."')";
    	}

    }

    return implode(PHP_EOL.PHP_EOL.'      ', $replacement);
}
/**
 * Enter description here...
 *
 * @param string $dir
 * @return boolean|string
 */
function checkShopRoot($dir)
{
    $dir = replaceDirSeparator($dir);
    if (substr($dir, strlen($dir) - 1, 1) !== DIRECTORY_SEPARATOR) {
        $dir .= DIRECTORY_SEPARATOR;
    }
    if (is_dir($dir . 'app')) {
        return $dir;
    }
    return false;
}

/**
 * Enter description here...
 *
 * @param string $dir
 * @return string
 */
function replaceDirSeparator($dir)
{
    $search = array('\\\\', '/');
    $dir = str_replace($search, DIRECTORY_SEPARATOR, $dir);

    return $dir;
}
/**
 * Enter description here...
 *
 * @param unknown_type $dir
 * @param unknown_type $module
 * @return boolean
 */
function uninstallModule($dir, $module, $files, $vars)
{
	foreach ($files as $file) {
		@unlink($dir . $file);
	}
    if (is_dir($dir.$module)) {
        $folder = rmRecurse($dir.$module);
        $sql = deleteSql($dir, $module, $vars);
        if ($folder and $sql) {
            return true;
        }
    }
    return false;
}

/**
 * Enter description here...
 *
 * @param unknown_type $dir
 * @return array
 */
function getMagentoDatabaseSettings($dir)
{
    $xml = simplexml_load_file($dir.'app/etc/local.xml', null, LIBXML_NOCDATA);

    $settings = array();
    $settings['dbUser'] = (string)$xml->global->resources->default_setup->connection->username;
    $settings['dbHost'] = (string)$xml->global->resources->default_setup->connection->host;
    $settings['dbPassword'] = (string)$xml->global->resources->default_setup->connection->password;
    $settings['dbName'] = (string)$xml->global->resources->default_setup->connection->dbname;

    return $settings;
}

/**
 * Enter description here...
 *
 * @param unknown_type $dir
 * @param unknown_type $module
 * @return boolean
 */
function deleteSql($dir, $module, $vars)
{
    $settings = getMagentoDatabaseSettings($dir);
    $connection = dbConnect($settings);

    $module = preg_replace('/\/$/', '', $module);
    $module = strtolower(substr(strrchr($module, '/'), 1));

    $tblPrefix = getTablePrefix($dir);

    $sql = "DELETE FROM ".$tblPrefix."core_resource WHERE code = '".$vars['lowNamespace']."_".$module."_setup'";
    $delete = mysql_query($sql);

//     $sql = "DROP TABLE ".$vars['lowNamespace']."_".$module."_".$vars['lowModel'];
//     $drop = mysql_query($sql);

    dbDisconnect($connection);
    if ($delete) {
        return true;
    }
    return false;
}

/**
 * Enter description here...
 *
 * @return unknown
 */
function getTablePrefix($dir)
{
    $xml = simplexml_load_file($dir.'app/etc/local.xml', null, LIBXML_NOCDATA);
    $prefix = (string)$xml->global->resources->db->table_prefix;
    if ($prefix != '') {
        return $prefix.'.';
    }
    return $prefix;
}

/**
 * Enter description here...
 *
 * @param array $settings
 * @return boolean
 */
function dbConnect(array $settings)
{
    $connection = mysql_connect($settings['dbHost'], $settings['dbUser'], $settings['dbPassword']) or die
        ('Could not connect to host.');
    mysql_select_db($settings['dbName']) or die
        ('Database does not exsist.');

    return $connection;
}

/**
 * Enter description here...
 *
 * @param unknown_type $connection
 */
function dbDisconnect($connection)
{
    mysql_close($connection);
}

/**
 * http://de3.php.net/manual/de/function.rmdir.php
 * ornthalas at NOSPAM dot gmail dot com
 *
 * @param string $filepath
 * @return unknown
 */
function rmRecurse($filepath)
{
    if (is_dir($filepath) && !is_link($filepath)) {
        if ($dh = opendir($filepath)) {
            while (($sf = readdir($dh)) !== false) {
                if ($sf == '.' || $sf == '..') {
                    continue;
                }
                if (!rmRecurse($filepath.'/'.$sf)) {
                    throw new Exception($filepath.'/'.$sf.' could not be deleted.');
                }
            }
            closedir($dh);
        }
        return rmdir($filepath);
    }
    return unlink($filepath);
}

/**
 * Enter description here...
 *
 * @param string $folder
 * @return string
 */
function getAvailableTemplates($folder)
{
	$array = array();
	if ($handle = opendir($folder)) {
	    while (false !== ($file = readdir($handle))) {
	    	if(!is_dir($file) && $file !== '.' && $file !== '..' && $file !== '.DS_Store') {
	    		$class = $folder . '_' . $file . '_Config';
	    		$config = new $class;
	    		$array[$file] = $config->getName();
	    	}
	    }
	    closedir($handle);
	}
	return $array;
}

/**
 * Enter description here...
 *
 * @param string $folder
 * @param string $select
 * @return string
 */
function getAvailableTemplatesHTML($folder, $select)
{
	$array = getAvailableTemplates($folder);
	$string = '';
	foreach ($array as $dir => $name) {
		if($select==$dir)
			$string .= '<option value="' . $dir . '" selected="selected">' . $name . '</option>';
		else
			$string .= '<option value="' . $dir . '">' . $name . '</option>';

	}
	return $string;
}

/**
 * TODO: Get available namespaces
 *
 * @param string $select
 * @return string
 */
function getAvailableNamespace($select)
{
	$array = array();
	$string = '';
	foreach ($array as $dir => $name) {
		if($select==$name)
			$string .= '<option id="'.$name.'" value="' . $name . '" selected="selected">' . $name . '</option>';
		else
			$string .= '<option id="'.$name.'" value="' . $name . '">' . $name . '</option>';
	}
	return $string;
}

/**
 * Enter description here...
 *
 * @param string $select
 * @return string
 */
function getTableCreator($schemaBdd, $select){
	$resource = Mage::getSingleton('core/resource');
	$read = $resource->getConnection('core_read');
	$query = "SHOW TABLES FROM ".$schemaBdd." LIKE 'CREATOR_%'";
	$statement = $read->query($query);
	$string = '<option value="">Select table</option>';

	while ($row = $statement->fetch())
	{
		$tableCreator = $row['Tables_in_'.$schemaBdd.' (CREATOR_%)'];
		$table = str_replace("CREATOR_", "", $tableCreator);


		if($select==$tableCreator)
			$string .= '<option value="' . $tableCreator . '" selected="selected">' . $table . '</option>';
		else
			$string .= '<option value="' . $tableCreator . '">' . $table . '</option>';

	}
// 	if( $row = $statement->fetch() and !empty($row['Create Table']) )
// 	{
// 		$string .= '<option value="' . $name . '">' . $name . '</option>';
// 	}
	return $string;
}

/**
 * Enter description here...
 *
 * @param string $select
 * @return string
 */
function getModelForeignKeys($select){

	$request = $select[0]['Create Table'];

	preg_match("#CONSTRAINT.+\n#isU", $request, $result);

	foreach ($result as $k=>$v){
		preg_match("#FOREIGN KEY \(`(\w+)`\)#isU", $v, $idMatch);
		preg_match("#REFERENCES `(\w+)`#isU", $v, $tableMatch);
		$table[] = array('tableForeign'=>$tableMatch[1],'id'=>$idMatch[1]);
	}
	if(isset($table)){
		foreach ($table as $k=>$v){
			$path=explode('_',$v['tableForeign']);
			$id = $v['id'];

			if(count($path)==3)
				$tabModel[$id]=$path[0].'_'.$path[1].'/'.$path[2];
			else //Normalement 2
				$tabModel[$id]=$path[0].'/'.$path[1];

		}

		return $tabModel;
	}
	return array();
}



/**
 * Enter description here...
 *
 * @return string
 */
function getHeader()
{
	return '<html>
            <head>
            	<title>Module Creator</title>
            	<style type="text/css">
            		* {
            			font-family: Arial, Helvetica, Sans-Serif;
            			font-size: 10pt;
            		}
            		body {
            			background-color: #E5E5E5;
            		}
            		#main {
            			width:400px;margin:0px auto;
            			border: 1px solid #0072A6;
            			padding: 20px 30px 20px 30px;
            			background-color: white;
            		}
            		#message {
            			border:1px solid grey;
            			margin: 10px;
            			padding: 10px;
            		}
            		.description {
            			width: 170px;
            			float: left;
            		}
            		.element {
            			clear:both;
            			height:40px;
            		}
            		.annotation {
            			font-size: 8pt;
            			color: grey;
            		}
            		#submit {
            			height: 20px;
            			display:block;
            		}
            		#create {
            			float: left;
            			margin-left: 30px;
            		}
            		#uninstall {
            			float: right;
            			margin-right: 30px;
            		}
            		h1 {
            			font-size: 14pt;
            		}
            		#logout, a {
            			font-size:8pt;
            			color: grey;
            			position:relative;
						right:-183px;
						top:-13px;
            		}
            		.text {
            			width: 230px;
            		}
            		.file {
            			font-size:8pt;
            			color: grey;
            		}
            	</style>
            	<script type="text/javascript">
            		function prepareForm(valTable)
					{
						var valTable = valTable.value;
						var tab = valTable.split("_");
						var namespace = tab[1].charAt(0).toUpperCase()+tab[1].slice(1);

						document.getElementById(namespace).selected = \'selected\';
						document.getElementById(\'module\').value=tab[2];
						document.getElementById(\'model\').value=tab[3];
					}


            	</script
            </head>
            <body>
            	<div id="main">';
}

/**
 * Enter description here...
 *
 * @return string
 */
function getFooter()
{
	return '</div>
         	</body>
      </html>';
}

function clearCache()
{
	global $shop;

	$cacheDir = $shop . 'var/cache/';
	rmRecurse($cacheDir);
}


//--------------------------------------------------------------
$schemaBdd = isset($_POST['bdd_schema']) ? $_POST['bdd_schema'] : 'bdd_shcema_magento';
$formNamespacePost		= isset($_POST['namespace']) ? $_POST['namespace'] : '';
$formNamespace 		= getAvailableNamespace($formNamespacePost);
$formModule 		= isset($_POST['module']) ? $_POST['module'] : '';
$formMagentoRoot 	= isset($_POST['magento_root']) ? replaceDirSeparator($_POST['magento_root']) : substr($root, 0, -15);
$formInterface 		= isset($_POST['interface']) ? $_POST['interface'] : '';
$formTheme 			= isset($_POST['theme']) ? $_POST['theme'] : '';
$formModel          = isset($_POST['model']) ? $_POST['model']   : '';
$formExport         = isset($_POST['export']) ? $_POST['export'] : '0';
$formTemplatesPost  = isset($_POST['template']) ? $_POST['template'] : '';
$formTemplates 		= getAvailableTemplatesHTML(TEMPLATES_DIR, $formTemplatesPost);
$formBddTable  		= isset($_POST['bdd_table']) ? $_POST['bdd_table'] : '';
$formListTable		= getTableCreator($schemaBdd, $formBddTable);

$form = '       <h1>Magento Module Creator</h1>
				<span id="logout"><a href="?logout=yes">Logout</a></span>
                <form name="newmodule" method="POST" action="" />
                	<div class="element">
                		<div class="description">Skeleton Template:<br /><span class="annotation">(you could build your own)</span></div>
                		<select name="template" class="select">
                		' . $formTemplates . '
                		</select>
                	</div>
                	<div class="element">
                		<div class="description">Table:<br /><span class="annotation">(e.g. your table name must be prefix with CREATOR_ to see it in the list)</span></div>
                		<select name="bdd_table" id="bdd_table" class="select" onchange="prepareForm(this);">
                			'.$formListTable.'
                		</select>
                	</div>'.

                	(($formNamespace) ?
                    	'<div class="element">
                    		<div class="description">Namespace:<br /><span class="annotation">(e.g. your Company Name)</span></div>
                    		<select name="namespace" class="select">
                    		' . $formNamespace . '
                    		</select>
                    	</div>'
                   :
                	   '<div class="element">
                    		<div class="description">Namespace:<br /><span class="annotation">(e.g. your Company Name)</span></div>
                    		<input name="namespace" class="text" type="text" length="50" value="' . $formNamespace . '" />
                    	</div>'
        	         ).


                	'<div class="element">
                		<div class="description">Export:<br /><span class="annotation">(gestion des export en liste)</span></div>
                		<select name="export" class="select">
                			<option value="1">Oui</option>
                			<option value="0">Non</option>
                		</select>
                	</div>
                	<div class="element">
                		<div class="description">Module:<br /><span class="annotation">(e.g. Blog, News, Forum)</span></div>
                		<input name="module" id="module" class="text" type="text" length="50" value="' . $formModule . '" />
                	</div>
                	<div class="element">
                		<div class="description">Model:<br /><span class="annotation">(e.g. your table name)</span></div>
                		<input name="model" id="model" class="text" type="text" length="50" value="' . $formModel . '" />
                	</div>
                	<div id="magento_root" class="element">
                		<div class="description">Magento Root Directory:<br /><span class="annotation">(auto detected)</span></div>
                		<input name="magento_root" class="text" type="text" length="255" value="' . $formMagentoRoot . '" />
                	</div>
                	<div id="interface" class="element">
                		<div class="description">Interface:<br /><span class="annotation">(interface, default is \'default\')</span></div>
                		<input name="interface" class="text" type="text" length="100" value="' . $formInterface . '" />
                	</div>
                	<div id="theme" class="element">
                		<div class="description">Theme:<br /><span class="annotation">(theme, default is \'default\')</span></div>
                		<input name="theme" class="text" type="text" length="100" value="' . $formTheme . '" />
                	</div>
                	<div id="submit">
                		<input type="submit" value="create" name="create" id="create" /> <input type="submit" value="uninstall" name="uninstall" id="uninstall" />
                	</div>
                </form>';

if(!empty($_POST)) {
    $namespace = $_POST['namespace'];
    $module = $_POST['module'];
    $interface = $_POST['interface'];
    $theme = $_POST['theme'];

    if ($interface == '') {
        $interface = 'default';
    }

    if ($theme == '') {
        $theme = 'default';
    }

    if ($_POST['magento_root'] != '') {
        if (checkShopRoot($_POST['magento_root']) !== false) {
            $shop = checkShopRoot($_POST['magento_root']);
        } else {
            throw new Exception('This is not a valid Magento install dir: ' . $_POST['magento_root']);
        }
    }

    $vars = array(
	    'template' 		=> $_POST['template'],
    	'capNamespace' 	=> ucfirst($namespace),
	    'lowNamespace' 	=> strtolower($namespace),
	    'capModule' 	=> ucfirst($module),
	    'lowModule' 	=> strtolower($module),
    	'interface'		=> $interface,
    	'theme'			=> $theme,
        'capModel'      => ucfirst($formModel),
        'lowModel'      => strtolower($formModel),
        'bddtable'      => str_replace("CREATOR_", "", $formBddTable),
    	'export'		=> $formExport
    );

    if( !empty($vars['bddtable']) )
    {
    	$vars['bddcolumns']= array();
    	$vars['bddtablepk']=false;
    	$vars['usestatus']=false;

    	$resource = Mage::getSingleton('core/resource');
    	$read = $resource->getConnection('core_read');
    	//$tableName = $resource->getTableName('mycompany_message');


    	$query = "SHOW CREATE TABLE ".$formBddTable;
    	$statement = $read->query($query);
    	$tabModel = getModelForeignKeys($statement->fetchAll());


    	$query = "SHOW FULL FIELDS FROM ".$formBddTable;
    	$statement = $read->query($query);

    	while ($row = $statement->fetch())
    	{

    		$vars['bddtablecols'][$row['Field']]=$row;
    		if( !empty($row['Key']) and  $row['Key']=='PRI')
    			$vars['bddtablepk']=$row['Field'];

    		if(substr($row['Field'],0,3))
    			$vars['usestatus']=true;

    		if( !empty($row['Comment']) )
    		    $vars['bddtablecols'][$row['Field']]['label_from_bdd']= $row['Comment'];
    		else
    		    $vars['bddtablecols'][$row['Field']]['label_from_bdd']= $row['Field'];

    		if(array_key_exists($row['Field'],$tabModel))
    			$vars['bddtablecols'][$row['Field']]['model'] = $tabModel[$row['Field']];

//     		echo "<pre>";
//     		print_r($vars);
//     		echo "</pre>";


    	}
    	if( empty($vars['bddtablepk']) )
    	    throw new Exception('No primary key on table '.$vars['bddtable']);

    	$query = "SHOW CREATE TABLE ".$formBddTable;
    	$statement = $read->query($query);
    	if( $row = $statement->fetch() and !empty($row['Create Table']) )
    	{
    	    $mageTable= '{$installer->getTable(\''.$vars['lowNamespace'].'_'.$vars['lowModule'].'/'.$vars['lowModel'].'\')}';

    	    $vars['bddtabledrop']= 'DROP TABLE IF EXISTS `'.$mageTable.'`';
    		$vars['bddtablecreate']= str_replace($vars['bddtable'],$mageTable,$row['Create Table']);
    	}

    }
   	$class = TEMPLATES_DIR . '_' . $_POST['template'] . '_Config';
    if (class_exists($class)) {
	    $config = new $class;
	    $config->setVars($vars);
    	$fromFiles = $config->getFromFiles();
    	$toFiles = $config->getToFiles();
    } else {
    	throw new Exception('No Config.php found for selected skeleton template: '.$template);
    }

     if (isset($_POST['create'])) {
         if (!empty($module) && !empty($namespace) && !empty($formBddTable)) {
         	clearCache();
            copyBlankoFiles($fromFiles, $toFiles, $shop);
            insertCustomVars($toFiles, $shop, $formBddTable);

            $message = '<div id="message"><p><strong>New Module successfully created!</strong></p>
        		<p><strong>List of created files:</strong></p>';
                 foreach ($toFiles as $file) {
                     $message .= '<p class="file">' . $file . '</p>';
                 }
        		$message .= '</div>';
         } else {
             $message = '<div id="message"><p>Please fill out all required fields.</p></div>';
         }
     }
     if (isset($_POST['uninstall'])) {
     	 $modulePath = 'app/code/local/'.$vars['capNamespace'].'/'.$vars['capModule'].'/';
         if (uninstallModule($shop, $modulePath, $toFiles, $vars) === true) {
         	clearCache();
            $message = '<div id="message"><p><strong>Module successfully uninstalled!</strong></p></div>';
         } else {
             $message = '<div id="message"><p><strong>Couldn\'t find module in Magento installation.</strong></p>
             			<p>After creating a module, you need to run Magento to install all new required tables
             			automatically. Also make sure you deactivate/refresh all Magento caches. Otherwise
             			no new modules will be recognized.</p></div>';
         }
     }

} else {
    $message = '<div id="message">To create a new module, insert Namespace and a Module name (e.g. Blog, Forum, etc.) as well as
    			your design above. If you want it to be installed right away into your Magento, enter your Magento install path.</div>';
}

/*
 * Output
 */
print getHeader()
    . $form
    . $message
    . getFooter();
