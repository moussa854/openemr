<?php
/* Smarty version 4.3.4, created on 2024-10-28 19:27:12
  from '/var/www/emr.carepointinfusion.com/gacl/admin/templates/phpgacl/pager.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_67201dd00e6ac6_43886594',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '02a5deaf052c5949cd8823092e55f78b06fcd1e4' => 
    array (
      0 => '/var/www/emr.carepointinfusion.com/gacl/admin/templates/phpgacl/pager.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_67201dd00e6ac6_43886594 (Smarty_Internal_Template $_smarty_tpl) {
?><table width="100%" cellspacing="0" cellpadding="2" border="0">
  <tr valign="middle">
    <td align="left">
<?php if ((isset($_smarty_tpl->tpl_vars['paging_data']->value['atfirstpage'])) && $_smarty_tpl->tpl_vars['paging_data']->value['atfirstpage']) {?>
      |&lt; &lt;&lt;
<?php } else { ?>
      <a href="<?php echo $_smarty_tpl->tpl_vars['link']->value;?>
page=1">|&lt;</a> <a href="<?php echo $_smarty_tpl->tpl_vars['link']->value;?>
page=<?php if ((isset($_smarty_tpl->tpl_vars['paging_data']->value['prevpage']))) {
echo text($_smarty_tpl->tpl_vars['paging_data']->value['prevpage']);
}?>">&lt;&lt;</a>
<?php }?>
    </td>
    <td align="right">
<?php if ((isset($_smarty_tpl->tpl_vars['paging_data']->value['atlastpage'])) && $_smarty_tpl->tpl_vars['paging_data']->value['atlastpage']) {?>
      &gt;&gt; &gt;|
<?php } else { ?>
      <a href="<?php echo $_smarty_tpl->tpl_vars['link']->value;?>
page=<?php if ((isset($_smarty_tpl->tpl_vars['paging_data']->value['nextpage']))) {
echo text($_smarty_tpl->tpl_vars['paging_data']->value['nextpage']);
}?>">&gt;&gt;</a> <a href="<?php echo $_smarty_tpl->tpl_vars['link']->value;?>
page=<?php if ((isset($_smarty_tpl->tpl_vars['paging_data']->value['lastpageno']))) {
echo text($_smarty_tpl->tpl_vars['paging_data']->value['lastpageno']);
}?>">&gt;|</a>
<?php }?>
    </td>
  </tr>
</table>
<?php }
}
