<?php
/** @var array $p @var bool|null $eager */
$eager = $eager ?? false;
$url = '/product/' . $p['slug'] . '/';
$discount = $p['old_price'] && $p['old_price'] > $p['price'] ? (int)round(100 - $p['price'] * 100 / $p['old_price']) : 0;
?>
<article class="card">
  <a class="card__img" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
    <?php if (!empty($p['image'])): ?>
      <img src="<?= e(Images::url($p['image'], 400)) ?>" srcset="<?= e(Images::url($p['image'], 400)) ?> 400w, <?= e(Images::url($p['image'], 800)) ?> 800w"
           sizes="(max-width: 600px) 50vw, (max-width: 1100px) 33vw, 280px" width="400" height="400"
           alt="<?= e($p['name']) ?>" <?= $eager ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
    <?php else: ?>
      <img src="/assets/img/no-photo.svg" width="400" height="400" alt="" loading="lazy">
    <?php endif; ?>
    <span class="card__badges">
      <span class="badge"><?= e($p['size_label']) ?></span>
      <?php if ($discount): ?><span class="badge badge--sale">−<?= $discount ?>%</span><?php endif; ?>
      <?php if (!$p['in_stock']): ?><span class="badge badge--muted">Под заказ</span><?php endif; ?>
    </span>
  </a>
  <div class="card__body">
    <h3 class="card__title"><a href="<?= e($url) ?>"><?= e($p['name']) ?></a></h3>
    <ul class="card__specs">
      <?php if ($p['width_cm']): ?><li><?= e(num_fmt($p['height_cm'])) ?>×<?= e(num_fmt($p['width_cm'])) ?>×<?= e(num_fmt($p['depth_cm'])) ?> см</li><?php endif; ?>
      <?php if ($p['volume_l']): ?><li><?= e(num_fmt($p['volume_l'])) ?> л</li><?php endif; ?>
      <?php if ($p['weight_kg']): ?><li><?= e(num_fmt($p['weight_kg'])) ?> кг</li><?php endif; ?>
      <li><?= e($p['material']) ?></li>
    </ul>
    <div class="card__bottom">
      <div class="price">
        <span class="price__now"><?= e(price_fmt($p['price'])) ?></span>
        <?php if ($discount): ?><s class="price__old"><?= e(price_fmt($p['old_price'])) ?></s><?php endif; ?>
      </div>
      <button class="btn btn--sm" type="button" data-modal="order" data-product-id="<?= (int)$p['id'] ?>" data-product-name="<?= e($p['name'] . ' — ' . price_fmt($p['price'])) ?>" data-goal="open_order">Заказать</button>
    </div>
  </div>
</article>
