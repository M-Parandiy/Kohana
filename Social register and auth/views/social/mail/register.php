<form method="post">
    <div><label>���������� ������: <input type="password" name="password" value="" /></label></div>
    <input type="submit" value="����������" />
</form>
<?php if($reload == 1) : ?>
<script>
    window.opener.location.reload();
    window.close();
</script>
<?php endif; ?>