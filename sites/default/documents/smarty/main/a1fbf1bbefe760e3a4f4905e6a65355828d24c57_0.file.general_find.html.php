<?php
/* Smarty version 4.3.4, created on 2025-03-17 08:16:54
  from '/var/www/emr.carepointinfusion.com/templates/patient_finder/general_find.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_67d812b62df378_51959026',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'a1fbf1bbefe760e3a4f4905e6a65355828d24c57' => 
    array (
      0 => '/var/www/emr.carepointinfusion.com/templates/patient_finder/general_find.html',
      1 => 1700108885,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_67d812b62df378_51959026 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/emr.carepointinfusion.com/library/smarty/plugins/function.xlt.php','function'=>'smarty_function_xlt',),1=>array('file'=>'/var/www/emr.carepointinfusion.com/library/smarty/plugins/function.xla.php','function'=>'smarty_function_xla',),));
?>
<html>
<head>



 <style>
<!--
td {
	font-size:12pt;
	font-family:helvetica;
}
.small {
	font-size: 9pt;
	font-family: "Helvetica", sans-serif;
	text-decoration: none;
}
.small:hover {
	text-decoration: underline;
}
li{
	font-size:11pt;
	font-family: "Helvetica", sans-serif;
	margin-left: 15px;
}
a {
	font-size:11pt;
	font-family: "Helvetica", sans-serif;
}
-->
</style>

<link rel="stylesheet" href="<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['css_header'];?>
">
</head>
<body class="body_top">
<form name="patientfinder" method="post" action="<?php echo $_smarty_tpl->tpl_vars['FORM_ACTION']->value;?>
" onsubmit="return top.restoreSession()">
<table>
	<tr>
		<td><?php echo smarty_function_xlt(array('t'=>'Name'),$_smarty_tpl);?>
</td>
		<td>
			<input type="text" size="40" name="searchstring" value=""/>
		</td>
	</tr>
	<tr>
		<td>
			<input type="submit" value="<?php echo smarty_function_xla(array('t'=>'Search'),$_smarty_tpl);?>
"/>
		</td>
	</tr>
</table>
<input type="hidden" name="process" value="<?php echo attr($_smarty_tpl->tpl_vars['PROCESS']->value);?>
" />
<input type="hidden" name="pid" value="<?php echo attr($_smarty_tpl->tpl_vars['hidden_ispid']->value);?>
" />
</form>
<table>
<?php if (!empty($_smarty_tpl->tpl_vars['result_set']->value) && count($_smarty_tpl->tpl_vars['result_set']->value) > 0) {?>
	<tr>
		<td><?php echo smarty_function_xlt(array('t'=>'Results Found For Search'),$_smarty_tpl);?>
 '<?php echo text($_smarty_tpl->tpl_vars['search_string']->value);?>
'</td>
	</tr>
	<tr>
		<td><?php echo smarty_function_xlt(array('t'=>'Name'),$_smarty_tpl);?>
</td><td><?php echo smarty_function_xlt(array('t'=>'DOB'),$_smarty_tpl);?>
</td><td><?php echo smarty_function_xlt(array('t'=>'Patient ID'),$_smarty_tpl);?>
</td>
<?php }
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['result_set']->value, 'result', false, NULL, 'search_results', array (
));
$_smarty_tpl->tpl_vars['result']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['result']->value) {
$_smarty_tpl->tpl_vars['result']->do_else = false;
?>
	<tr>
		<td>
			<a href="javascript:{}" onclick="window.opener.document.<?php echo attr($_smarty_tpl->tpl_vars['form_id']->value);?>
.value='<?php if ($_smarty_tpl->tpl_vars['ispub']->value == true) {
echo attr($_smarty_tpl->tpl_vars['result']->value['pubpid']);
} else {
echo attr($_smarty_tpl->tpl_vars['result']->value['pid']);
}?>'; window.opener.document.<?php echo attr($_smarty_tpl->tpl_vars['form_name']->value);?>
.value='<?php echo attr($_smarty_tpl->tpl_vars['result']->value['name']);?>
'; window.close();"><?php echo text($_smarty_tpl->tpl_vars['result']->value['name']);?>
</a>
		</td>
		<td><?php echo text($_smarty_tpl->tpl_vars['result']->value['DOB']);?>
</td>
		<td><?php echo text($_smarty_tpl->tpl_vars['result']->value['pubpid']);?>
</td>
	</tr>
<?php
}
if ($_smarty_tpl->tpl_vars['result']->do_else) {
?>
	<?php if (is_array($_smarty_tpl->tpl_vars['result_set']->value)) {?>
	<tr>
		<td><?php echo smarty_function_xlt(array('t'=>'No Results Found For Search'),$_smarty_tpl);?>
 '<?php echo text($_smarty_tpl->tpl_vars['search_string']->value);?>
'</td>
	</tr>
	<?php }
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
	</table>
  </body>
</html>
<?php }
}
