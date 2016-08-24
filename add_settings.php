<?php
/*
 * =============================================================================== *
 * Software Version:           Loginza for SMF 2.x                                 *
 * Copyright 2010 by:          Digger                                              *
 * Support, News, Updates at:  http://mysmf.ru                                     *
 * ********************************************************************************
*/

$smcFunc['db_add_column']('{db_prefix}members', array('name' => 'loginza_provider', 'type' => 'TEXT', 'default' => ''));
$smcFunc['db_add_column']('{db_prefix}members', array('name' => 'loginza_identity', 'type' => 'TEXT', 'default' => ''));

?>
