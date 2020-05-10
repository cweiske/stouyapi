<?xml version="1.0" encoding="utf-8"?>
<html xmlns="http://www.w3.org/1999/xhtml">
 <head>
  <title>OUYA: <?= htmlspecialchars($title); ?></title>
  <link rel="stylesheet" type="text/css" href="../ouya-discover.css"/>
 </head>
 <body class="discover">
  <header>
   <h1><?= htmlspecialchars($title); ?></h1>
  </header>

  <?php foreach ($sections as $section): ?>
  <section class="row">
   <?php if ($section->title): ?>
   <h2><?= htmlspecialchars($section->title) ?></h2>
   <?php endif ?>

   <div class="tiles">
    <?php foreach ($section->tiles as $tile): ?>
    <section class="tile<?= ($tile->thumb == '') ? ' noimg' : '' ?>">
     <h3 class="title"><a href="<?= htmlspecialchars($tile->detailUrl) ?>"><?= htmlspecialchars($tile->title) ?></a></h3>
     <?php if ($tile->thumb != ''): ?>
     <a href="<?= htmlspecialchars($tile->detailUrl) ?>"><img src="<?= htmlspecialchars($tile->thumb) ?>"/></a>
     <?php endif ?>
     <?php if ($tile->type == 'app' && $tile->ratingCount > 0): ?>
     <p class="rating">
      <span class="average average-<?= round($tile->rating) ?>"><?= $tile->rating ?></span>
      <span class="count">(<?= $tile->ratingCount ?>)</span>
     </p>
     <?php endif ?>
    </section>
    <?php endforeach; ?>
   </div>

  </section>
  <?php endforeach; ?>
 </body>
</html>
