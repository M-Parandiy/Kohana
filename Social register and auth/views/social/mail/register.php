<form method="post">
    <div><label>Придумайте пароль: <input type="password" name="password" value="" /></label></div>
    <input type="submit" value="продолжить" />
</form>
<?php if($reload == 1) : ?>
<script>
    window.opener.location.reload();
    window.close();
</script>
<?php endif; ?>