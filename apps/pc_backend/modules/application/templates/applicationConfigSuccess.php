<?php slot('submenu') ?>
<?php include_partial('submenu') ?>
<?php end_slot() ?>

<h2>OpenSocialアプリケーションの設定</h2>

<form action="<?php echo url_for('application/applicationConfig') ?>" method="post">
<table>
<?php echo $applicationConfigForm ?>
<tr><td colspan="2"><input type="submit" value="設定変更" /></td></tr>
</table>
</form>

