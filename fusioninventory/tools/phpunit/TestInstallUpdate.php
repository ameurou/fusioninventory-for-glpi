<?php

/*
 *  You can run this file with arg dbread to use current DB and not write DB tests like
 * phpunit TestInstallUpdate.php dbread
 */

define('PHPUnit_MAIN_METHOD', 'Plugins_Fusioninventory_TestInstallUpdate::main');

if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', '../../../..');

   require_once GLPI_ROOT."/inc/includes.php";
   $_SESSION['glpi_use_mode'] = 2;
   $_SESSION['glpiactiveprofile']['id'] = 4;

   ini_set('display_errors','On');
   error_reporting(E_ALL | E_STRICT);
   set_error_handler("userErrorHandler");

   // Backup present DB
//   include_once("inc/backup.php");
//   backupMySQL();

   $_SESSION["glpilanguage"] = 'fr_FR';

   // Install
//   include_once("inc/installation.php");
//   installGLPI();

//   loadLanguage();
//   include_once(GLPI_ROOT."/locales/fr_FR.php");
//   $CFG_GLPI["root_doc"] = GLPI_ROOT;
}
include_once('emulatoragent.php');

/**
 * Test class for MyFile.
 * Generated by PHPUnit on 2010-08-06 at 12:05:09.
 */
class Plugins_Fusioninventory_TestInstallUpdate extends PHPUnit_Framework_TestCase {

    public static function main() {
        require_once 'PHPUnit/TextUI/TestRunner.php';

        $suite  = new PHPUnit_Framework_TestSuite('Plugins_Fusioninventory_TestInstallUpdate');
        $result = PHPUnit_TextUI_TestRunner::run($suite);
    }

    
    function verifyDBNice($pluginname) {
       global $DB;
       
       $comparaisonSQLFile = "plugin_".$pluginname."-0.80+1.1-empty.sql";
       // See http://joefreeman.co.uk/blog/2009/07/php-script-to-compare-mysql-database-schemas/
       
       $file_content = file_get_contents("../../../".$pluginname."/install/mysql/".$comparaisonSQLFile);
       $a_lines = explode("\n", $file_content);
       
       $a_tables_ref = array();
       $current_table = '';
       foreach ($a_lines as $line) {
          if (strstr($line, "CREATE TABLE ")
                  OR strstr($line, "CREATE VIEW")) {
             $matches = array();
             preg_match("/`(.*)`/", $line, $matches);
             $current_table = $matches[1];
          } else {
             if (preg_match("/^`/", trim($line))) {
                $s_line = explode("`", $line);
                $s_type = explode("COMMENT", $s_line[2]);
                $s_type[0] = trim($s_type[0]);
                $s_type[0] = str_replace(" COLLATE utf8_unicode_ci", "", $s_type[0]);
                $s_type[0] = str_replace(" CHARACTER SET utf8", "", $s_type[0]);
                $a_tables_ref[$current_table][$s_line[1]] = str_replace(",", "", $s_type[0]);
             }
          }
       }
       if (isset($a_tables_ref['glpi_plugin_fusinvdeploy_tasks'])) {
          unset($a_tables_ref['glpi_plugin_fusinvdeploy_tasks']);
       }
       if (isset($a_tables_ref['glpi_plugin_fusinvdeploy_taskjobs'])) {
          unset($a_tables_ref['glpi_plugin_fusinvdeploy_taskjobs']);
       }
       
      // * Get tables from MySQL
      $a_tables_db = array();
      $a_tables = array();
      // SHOW TABLES;
      $query = "SHOW TABLES";
      $result = $DB->query($query);
      while ($data=$DB->fetch_array($result)) {
         if ((strstr($data[0], "tracker")
                 OR strstr($data[0], $pluginname))
             AND(!strstr($data[0], "glpi_plugin_fusinvinventory_pcidevices"))
             AND(!strstr($data[0], "glpi_plugin_fusinvinventory_pcivendors"))
             AND(!strstr($data[0], "glpi_plugin_fusinvinventory_usbdevices"))
             AND(!strstr($data[0], "glpi_plugin_fusinvinventory_usbvendors"))
             AND($data[0] != 'glpi_plugin_fusinvdeploy_tasks')
             AND($data[0] != 'glpi_plugin_fusinvdeploy_taskjobs')){
            $data[0] = str_replace(" COLLATE utf8_unicode_ci", "", $data[0]);
            $data[0] = str_replace("( ", "(", $data[0]);
            $data[0] = str_replace(" )", ")", $data[0]);
            $a_tables[] = $data[0];
         }
      }
      
      foreach($a_tables as $table) {
         $query = "SHOW COLUMNS FROM ".$table;
         $result = $DB->query($query);
         while ($data=$DB->fetch_array($result)) {
            $construct = $data['Type'];
//            if ($data['Type'] == 'text') {
//               $construct .= ' COLLATE utf8_unicode_ci';
//            }
            if ($data['Type'] == 'text') {
               if ($data['Null'] == 'NO') {
                  $construct .= ' NOT NULL';
               } else {
                  $construct .= ' DEFAULT NULL';
               }
            } else if ($data['Type'] == 'longtext') {
               if ($data['Null'] == 'NO') {
                  $construct .= ' NOT NULL';
               } else {
                  $construct .= ' DEFAULT NULL';
               }
            } else {
               if ((strstr($data['Type'], "char")
                       OR $data['Type'] == 'datetime'
                       OR strstr($data['Type'], "int"))
                       AND $data['Null'] == 'YES'
                       AND $data['Default'] == '') {
                  $construct .= ' DEFAULT NULL';
               } else {               
                  if ($data['Null'] == 'YES') {
                     $construct .= ' NULL';
                  } else {
                     $construct .= ' NOT NULL';
                  }
                  if ($data['Extra'] == 'auto_increment') {
                     $construct .= ' AUTO_INCREMENT';
                  } else {
//                     if ($data['Type'] != 'datetime') {
                        $construct .= " DEFAULT '".$data['Default']."'";
//                     }
                  }
               }
            }
            $a_tables_db[$table][$data['Field']] = $construct;
         }         
      }
      
       // Compare
      $tables_toremove = array_diff_assoc($a_tables_db, $a_tables_ref);
      $tables_toadd = array_diff_assoc($a_tables_ref, $a_tables_db);
       
      // See tables missing or to delete
      $this->assertEquals(count($tables_toadd), 0, 'Tables missing '.print_r($tables_toadd));
      $this->assertEquals(count($tables_toremove), 0, 'Tables to delete '.print_r($tables_toremove));
      
      // See if fields are same
      foreach ($a_tables_db as $table=>$data) {
         if (isset($a_tables_ref[$table])) {
            $fields_toremove = array_diff_assoc($data, $a_tables_ref[$table]);
            $fields_toadd = array_diff_assoc($a_tables_ref[$table], $data);
            echo "======= DB ============== Ref =======> ".$table."\n";
            
            print_r($data);
            print_r($a_tables_ref[$table]);
            
            // See tables missing or to delete
            $this->assertEquals(count($fields_toadd), 0, 'Fields missing/not good in '.$table.' '.print_r($fields_toadd));
            $this->assertEquals(count($fields_toremove), 0, 'Fields to delete in '.$table.' '.print_r($fields_toremove));
            
         }         
      }
      
      /*
       * Check if all modules registered
       */
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules` 
         WHERE `modulename`='WAKEONLAN'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'WAKEONLAN module not registered');
      
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules` 
         WHERE `modulename`='INVENTORY'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'INVENTORY module not registered');
      
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules` 
         WHERE `modulename`='ESX'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'ESX module not registered');
      
      $query = "SELECT `url` FROM `glpi_plugin_fusioninventory_agentmodules` 
         WHERE `modulename`='ESX'";
      $result = $DB->query($query);
      while ($data=$DB->fetch_array($result)) {
         $url = 0;
         if (!empty($data['url'])
                 AND strstr($data['url'], "http")
                 AND strstr($data['url'], "/esx")) {
            $url = 1;
         }
         $this->assertEquals($url, 1, 'ESX module url not right');
      }
      
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules` 
         WHERE `modulename`='SNMPQUERY'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'SNMPQUERY module not registered');
      
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules` 
         WHERE `modulename`='NETDISCOVERY'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'NETDISCOVERY module not registered');
      
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules` 
         WHERE `modulename`='DEPLOY'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'DEPLOY module not registered');
      
      
      /*
       * Verify in taskjob definition PluginFusinvsnmpIPRange not exist
       */
      $query = "SELECT * FROM `glpi_plugin_fusioninventory_taskjobs`";
      $result = $DB->query($query);
      while ($data=$DB->fetch_array($result)) {
         $snmprangeip = 0;
         if (strstr($data['definition'], "PluginFusinvsnmpIPRange")) {
            $snmprangeip = 1;
         }
         $this->assertEquals($snmprangeip, 0, 'Have some "PluginFusinvsnmpIPRange" items in taskjob definition');
      }
      
      /*
       * Verify cron created
       */
      $crontask = new CronTask();
      $this->assertTrue($crontask->getFromDBbyName('PluginFusioninventoryTaskjob', 'taskscheduler') , 
              'Cron taskscheduler not created');
      $this->assertTrue($crontask->getFromDBbyName('PluginFusioninventoryTaskjobstatus', 'cleantaskjob') , 
              'Cron cleantaskjob not created');
      $this->assertTrue($crontask->getFromDBbyName('PluginFusinvsnmpNetworkPortLog', 'cleannetworkportlogs') , 
              'Cron cleannetworkportlogs not created');
      
      
      // TODO : test glpi_displaypreferences, rules, bookmark...
    }
    
    
   function testDB() {
      global $DB;

      if (isset($_SERVER['argv'][2]) 
              AND $_SERVER['argv'][2] == 'dbread') {
         // Not write DB, so use current DB 
      } else {
         $query = "SHOW TABLES";
         $result = $DB->query($query);
         while ($data=$DB->fetch_array($result)) {
            if (strstr($data[0], "tracker")
                    OR strstr($data[0], "fusi")) {
               $DB->query("DROP TABLE ".$data[0]);
            }
         }
      
         // ** Insert in DB
         $version = "2.3.3";
         $version = "2.1.3";
         $DB_file = GLPI_ROOT ."/plugins/fusioninventory/tools/phpunit/dbupdate/i-".$version.".sql";
         $DBf_handle = fopen($DB_file, "rt");
         $sql_query = fread($DBf_handle, filesize($DB_file));
         fclose($DBf_handle);
         foreach ( explode(";\n", "$sql_query") as $sql_line) {
            if (get_magic_quotes_runtime()) $sql_line=stripslashes_deep($sql_line);
            if (!empty($sql_line)) $DB->query($sql_line);
         }
      }
       
      passthru("cd .. && /usr/local/bin/php -f cli_install.php");
       
      $this->verifyDBNice("fusioninventory");
      echo "***********************************************************\n";
      echo "***********************************************************\n";
      $this->verifyDBNice("fusinvinventory");
      echo "***********************************************************\n";
      echo "***********************************************************\n";
      $this->verifyDBNice("fusinvsnmp");
      echo "***********************************************************\n";
      echo "***********************************************************\n";
      $this->verifyDBNice("fusinvdeploy");
   }
    
    
}

// Call Plugins_Fusioninventory_Discovery_Newdevices::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == 'Plugins_Fusioninventory_TestInstallUpdate::main') {
    Plugins_Fusioninventory_TestInstallUpdate::main();

}

?>