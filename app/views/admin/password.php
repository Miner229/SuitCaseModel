<form method="post" class="a-form a-card a-narrow">
  <?= Security::csrfField() ?>
  <label>Текущий пароль<input type="password" name="current" autocomplete="current-password" required></label>
  <label>Новый пароль <small>минимум 10 символов, буквы и цифры</small><input type="password" name="new" autocomplete="new-password" minlength="10" required></label>
  <label>Повторите новый пароль<input type="password" name="new2" autocomplete="new-password" minlength="10" required></label>
  <button class="a-btn">Изменить пароль</button>
</form>
