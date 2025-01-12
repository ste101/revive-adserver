<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

/**
 * OpenX Schema Management Utility
 */

require_once '../../../../init.php';

if (array_key_exists('btn_changeset_archive', $_POST)) {
    header('Location: oxSchema-archive.php');
    exit;
}

if (array_key_exists('clear_cookies', $_POST)) {
    setcookie('schemaPath', '');
    setcookie('schemaFile', '');
} elseif (array_key_exists('xml_file', $_REQUEST) && (!empty($_REQUEST['xml_file']))) {
    $schemaPath = dirname($_REQUEST['xml_file']);
    if (!empty($schemaPath)) {
        $schemaPath .= DIRECTORY_SEPARATOR;
    }
    $schemaFile = basename($_REQUEST['xml_file']);
    if ($schemaFile == $_COOKIE['schemaFile']) {
        $schemaPath = $_COOKIE['schemaPath'];
    }
    $_POST['table_edit'] = '';
} elseif (array_key_exists('schemaFile', $_COOKIE) && (!empty($_COOKIE['schemaFile']))) {
    $schemaPath = $_COOKIE['schemaPath'];
    $schemaFile = $_COOKIE['schemaFile'];
}
if (empty($schemaPath) || empty($schemaFile)) {
    $schemaPath = '';
    $schemaFile = 'tables_core.xml';
}
setcookie('schemaPath', $schemaPath);
setcookie('schemaFile', $schemaFile);

require_once 'lib/oxSchema.inc.php';
global $oSchema;
$oSchema = new openXSchemaEditor($schemaFile, '', $schemaPath);

require_once 'lib/oxAjax.inc.php';

if (array_key_exists('btn_copy_final', $_POST)) {
    $oSchema->createTransitional();
} elseif (array_key_exists('btn_schema_new', $_POST)) {
    $oSchema->createNew($_POST['new_schema_name']);
} elseif (array_key_exists('btn_delete_trans', $_POST)) {
    $oSchema->deleteTransitional();
} elseif (array_key_exists('btn_compare_schemas', $_REQUEST)) {
    setcookie('changesetFile', '');
    if ($oSchema->createChangeset($oSchema->changes_trans, $_POST['comments'])) {
        setcookie('changesetFile', $oSchema->changes_trans);
        header('Pragma: no-cache');
        header('Cache-Control: private, max-age=0, no-cache');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-Type: application/xhtml+xml; charset=ISO-8859-1');
        readfile($oSchema->changes_trans);
        exit();
    }
} elseif (array_key_exists('btn_changeset_delete', $_POST)) {
    $oSchema->deleteChangesTrans();
} elseif (array_key_exists('btn_commit_final', $_POST)) {
    $oSchema->commitFinal($_POST['comments'], $_POST['version']);
}

$oSchema->setWorkingFiles();

if (array_key_exists('table_edit', $_POST) && $_POST['table_edit']) {
    $table = $_POST['table_edit'];

    if (array_key_exists('btn_field_save', $_POST) && $_POST['field_name']) {
        $field_name_old = $_POST['field_name'];
        $field_name_new = $_POST['fld_new_name'];
        $field_type_old = $_POST['field_type'];
        $field_type_new = $_POST['fld_new_type'];
        $oSchema->fieldSave($table, $field_name_old, $field_name_new, $field_type_old, $field_type_new);
    } elseif (array_key_exists('btn_field_add', $_POST) && $_POST['field_add']) {
        $field_name = $_POST['field_add'];
        $dd_field_name = $_POST['sel_field_add'];
        $oSchema->fieldAdd($table, $field_name, $dd_field_name);
    } elseif (array_key_exists('btn_field_del', $_POST) && $_POST['field_name']) {
        $field = $_POST['field_name'];
        $oSchema->fieldDelete($table, $field);
    } elseif (array_key_exists('btn_index_del', $_POST) && $_POST['index_name']) {
        $index = $_POST['index_name'];
        $oSchema->indexDelete($table, $index);
    } elseif (array_key_exists('btn_index_add', $_POST) && $_POST['idx_fld_add'] && $_POST['index_add']) {
        $index_fields = $_POST['idx_fld_add'];
        $index_name = $_POST['index_add'];
        $sort_desc = $_POST['idx_fld_desc'];
        $unique = $_POST['idx_unique'];
        $primary = $_POST['idx_primary'];
        $oSchema->indexAdd($table, $index_name, $index_fields, $primary, $unique, $sort_desc);
    } elseif (array_key_exists('btn_index_save', $_POST) && $_POST['index_name']) {
        $index_name = $_POST['index_name'];
        $index_no = $_POST['index_no'];
        $index_def = $_POST['idx'][$index_no];
        $oSchema->indexSave($table, $index_name, $index_def);
    } elseif (array_key_exists('btn_link_del', $_POST) && $_POST['link_name']) {
        $link_name = $_POST['link_name'];
        $oSchema->linkDelete($table, $link_name);
    } elseif (array_key_exists('btn_link_add', $_POST) && $_POST['link_add'] && $_POST['link_add_target']) {
        $link_add = $_POST['link_add'];
        $link_add_target = $_POST['link_add_target'];
        $oSchema->linkAdd($table, $link_add, $link_add_target);
    } elseif (array_key_exists('btn_table_save', $_POST) && $_POST['tbl_new_name']) {
        $table_name_new = $_POST['tbl_new_name'];
        $ok = $oSchema->tableSave($table, $table_name_new);
        if ($ok) {
            $table = $table_name_new;
        }
    } elseif (array_key_exists('btn_table_delete', $_POST)) {
        $oSchema->tableDelete($table);
        unset($table);
    } elseif (array_key_exists('btn_table_cancel', $_POST)) {
        $table = '';
    }
} elseif (array_key_exists('btn_table_new', $_POST) && $_POST['new_table_name']) {
    if (array_key_exists('new_table_name', $_POST)) {
        $table = $_POST['new_table_name'];
        $oSchema->tableNew($table);
        unset($table);
    }
} elseif (array_key_exists('btn_table_edit', $_POST)) {
    $table = $_POST['btn_table_edit'];
}

if (!$table) {
    header('Content-Type: application/xhtml+xml; charset=ISO-8859-1');
    readfile($oSchema->working_file_schema);
    exit();
} else {
    $oSchema->parseWorkingDefinitionFile();
    $aDD_definition = $oSchema->aDD_definition;
    $aDB_definition = $oSchema->aDB_definition;
    $aTbl_definition = $oSchema->aDB_definition['tables'][$table];
    $aLinks = $oSchema->readForeignKeys($table);
    $aTbl_links = $aLinks[$table];
    $aLink_targets = $oSchema->getLinkTargets();

    include 'templates/schema_edit.html';
    exit();
}


/* using XSLT class to display the xml inside html
    $xmlfile = $oSchema->working_file_schema;
    $xslfile = 'xsl/mdb2_schema.xsl';


    // Load the XML source
    $xml = new DOMDocument;
    $ok = $xml->load($xmlfile);

    $xsl = new DOMDocument;
    $ok = $xsl->load($xslfile);
    // Configure the transformer
    $proc = new XSLTProcessor;
    $ok = $proc->hasExsltSupport();
    $proc->importStyleSheet($xsl); // attach the xsl rules
    $i = $proc->transformToUri($xml, MAX_PATH.'/var/templates_compiled/schema.html');

    $xslt = file_get_contents(MAX_PATH.'/var/templates_compiled/schema.html');

    $oTpl->assign('xslt', $xslt);*/
