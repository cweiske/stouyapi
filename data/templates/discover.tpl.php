<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
 <head>
  <meta charset="utf-8"/>
  <title><?= htmlspecialchars($title); ?></title>
  <meta name="generator" content="stouyapi"/>
  <link rel="stylesheet" type="text/css" href="../ouya-discover.css"/>
  <link rel="icon" href="../favicon.ico"/>
 </head>
 <body class="discover">
  <header>
   <h1><?= htmlspecialchars($json->title); ?><?php if($subtitle) { echo ': ' . $subtitle; } ?></h1>
   <a href="./"><img class="ouyalogo" src="../ouya-logo.grey.svg" alt="OUYA logo" width="50"/></a>
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
     <a href="<?= htmlspecialchars($tile->detailUrl) ?>"><img src="<?= htmlspecialchars($tile->thumb) ?>" alt="Screenshot of <?= htmlspecialchars($tile->detailUrl) ?>"/></a>
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

  <nav>
   <?php foreach ($navLinks as $url => $title): ?>
    <a rel="up" href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($title) ?></a>
   <?php endforeach ?>
  </nav>
 </body>
</html>
