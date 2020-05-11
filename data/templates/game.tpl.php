<?xml version="1.0" encoding="utf-8"?>
<html xmlns="http://www.w3.org/1999/xhtml">
 <head>
  <title><?= htmlspecialchars($json->title); ?> - OUYA game</title>
  <meta name="generator" content="stouyapi"/>
  <link rel="stylesheet" type="text/css" href="../ouya-game.css"/>
 </head>
 <body class="game">
  <header>
   <img class="ouyalogo" src="../ouya-logo.grey.svg" alt="OUYA logo" width="20%"/>
  </header>
  <section class="text">
   <h1><?= htmlspecialchars($json->title); ?></h1>
   <dl class="meta">
    <dt>Rating</dt>
    <dd class="rating">
     <span class="average average-<?= round($json->rating->average) ?>"><?= $json->rating->average ?></span>
     <span class="count">(<?= $json->rating->count ?>)</span>
    </dd>

    <dt>Developer</dt>
    <dd class="company">
     <?= htmlspecialchars($json->developer->name) ?>
    </dd>

    <dt>Suggested age</dt>
    <dd class="contentRating">
     <?= htmlspecialchars($json->suggestedAge) ?>
    </dd>

    <dt>Number of players</dt>
    <dd class="players">
     <?= htmlspecialchars(implode(', ', $json->gamerNumbers)) ?>
    </dd>

    <dt>Download size</dt>
    <dd class="size">
     <?= number_format($json->apk->fileSize / 1024 / 1024, 2) ?> MiB
    </dd>
   </dl>

   <p class="description">
    <?= nl2br(htmlspecialchars($json->description)) ?>
   </p>
  </section>

  <section class="media">
   <h2>Screenshots</h2>
   <div class="content">
    <?php foreach ($json->mediaTiles as $tile): ?>
     <?php if ($tile->type == 'image'): ?>
      <img src="<?= htmlspecialchars($tile->urls->thumbnail) ?>" alt="Screenshot of <?= htmlspecialchars($json->title); ?>"/>
     <?php elseif ($tile->type == 'video'): ?>
      <video controls="">
       <source src="<?= htmlspecialchars($tile->url) ?>"/>
      </video>
     <?php endif ?>
    <?php endforeach ?>
   </div>
  </section>

  <section class="buttons">
   <div>
    <a href="<?= $apkDownloadUrl ?>">Download .apk</a>
    <p>
     Version <?= $json->version->number ?>, published
     <?= gmdate('Y-m-d', $json->version->publishedAt) ?>
    </p>
   </div>
  </section>

  <nav>
   <?php foreach ($navLinks as $url => $title): ?>
    <a rel="up" href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($title) ?></a>
   <?php endforeach ?>
  </nav>
 </body>
</html>
