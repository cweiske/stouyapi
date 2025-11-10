<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
 <head>
  <meta charset="utf-8"/>
  <title><?= htmlspecialchars($json->title); ?> - OUYA game bundle</title>
  <meta name="generator" content="stouyapi"/>
  <link rel="stylesheet" type="text/css" href="../ouya-game.css"/>
  <link rel="icon" href="../favicon.ico"/>
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>"/>
  <meta name="twitter:card" content="summary_large_image"/>
  <meta property="og:title" content="<?= htmlspecialchars($json->title); ?>" />
  <meta property="og:description" content="<?= htmlspecialchars(substr(strtok($json->description, '.!'), 0, 200)); ?>." />
  <meta property="og:image" content="<?= htmlspecialchars($json->stouyapi->tileImage); ?>" />
 </head>
 <body class="bundle">
  <header>
   <a href="../discover/"><img class="ouyalogo" src="../ouya-logo.grey.svg" alt="OUYA logo" width="50"/></a>
  </header>
  <section class="text">
   <h1><?= htmlspecialchars($json->title); ?></h1>
   <p class="description">
     <?= nl2br(htmlspecialchars($json->description)) ?>
   </p>
  </section>

  <section class="media">
   <h2>Screenshots</h2>
   <div class="content">
    <?php foreach ($json->mediaTiles as $tile): ?>
     <?php if ($tile->type == 'image'): ?>
      <img src="<?= htmlspecialchars($tile->urls->full) ?>" alt="Screenshot of <?= htmlspecialchars($json->title); ?>"/>
     <?php elseif ($tile->type == 'video'): ?>
      <video controls="">
       <source src="<?= htmlspecialchars($tile->url) ?>"/>
      </video>
     <?php endif ?>
    <?php endforeach ?>
   </div>
  </section>

  <section class="contents">
   <h2>Contents</h2>
   <div class="tiles">
    <?php foreach ($json->bundle->apps as $app): ?>
    <section class="tile<?= ($app->image == '') ? ' noimg' : '' ?>">
     <h3 class="title"><a href="<?= htmlspecialchars($app->detailUrl) ?>"><?= htmlspecialchars($app->title) ?></a></h3>
     <?php if ($app->image != ''): ?>
     <a href="<?= htmlspecialchars($app->detailUrl) ?>"><img src="<?= htmlspecialchars($app->image) ?>" alt="Screenshot of <?= htmlspecialchars($app->title) ?>" width="732" height="412"/></a>
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

  <section class="buttons">
   <h2>Links</h2>
   <div></div>
   <div>
    <form method="post" action="<?= htmlspecialchars($pushUrl) ?>" id="push" onsubmit="pushToMyOuya();return false;">
     <button name="push" type="submit" class="push-to-my-ouya">
      <img src="../push-to-my-ouya.png" width="335" height="63"
           alt="Push to my OUYA"
      />
     </button>
    </form>
   </div>
  </section>

  <nav>
   <?php foreach ($navLinks as $url => $title): ?>
    <a rel="up" href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($title) ?></a>
   <?php endforeach ?>
  </nav>

  <div style="display: none" class="popup" id="push-success">
   <a class="close" href="#" onclick="this.parentNode.style.display='none';return false;">⊗</a>
   <strong><?= htmlspecialchars($json->title); ?></strong>
   will start downloading to your OUYA within the next few minutes
  </div>
  <div style="display: none" class="popup" id="push-error">
   <a class="close" href="#" onclick="this.parentNode.style.display='none';return false;">⊗</a>
   <strong>Push error</strong>
   <p>error message</p>
  </div>

  <script type="text/javascript">
   function pushToMyOuya() {
       var form = document.getElementById("push");
       var req = new XMLHttpRequest();
       req.addEventListener("load", pushToMyOuyaComplete);
       req.open("POST", form.action);
       req.send();
   }
   function pushToMyOuyaComplete() {
       if (this.status / 100 == 2) {
           document.getElementById('push-success').style.display = "";
       } else {
           var err = document.getElementById('push-error');
           err.getElementsByTagName("p")[0].textContent = this.responseText;
           err.style.display = "";
       }
   }
  </script>
 </body>
</html>
