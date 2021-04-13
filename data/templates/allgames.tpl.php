<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
 <head>
  <meta charset="utf-8"/>
  <title>List of all OUYA games</title>
  <meta name="generator" content="stouyapi"/>
  <link rel="stylesheet" type="text/css" href="../datatables/datatables.min.css"/>
  <link rel="stylesheet" type="text/css" href="../datatables/jquery.dataTables.yadcf.css"/>
  <link rel="stylesheet" type="text/css" href="../ouya-allgames.css"/>
  <link rel="icon" href="../favicon.ico"/>
 </head>
 <body class="allgames">
  <header>
   <h1>List of all OUYA games</h1>
   <a href="./"><img class="ouyalogo" src="../ouya-logo.grey.svg" alt="OUYA logo" width="50"/></a>
  </header>

  <table id="allouyagames" class="display">
   <thead>
    <tr>
     <th>Game title</th>
     <th>Developer</th>
     <th>Age</th>
     <th>Players</th>
     <th>Genres</th>
     <th>Release</th>
    </tr>
   </thead>
   <tbody>
    <?php foreach ($games as $game): ?>
     <tr>
      <td>
       <a href="<?= htmlspecialchars($game->detailUrl) ?>">
        <?= htmlspecialchars($game->title) ?>
       </a>
      </td>
      <td>
       <?php if ($game->developerUrl): ?>
        <a href="<?= htmlspecialchars($game->developerUrl) ?>"><?= htmlspecialchars($game->developer) ?></a>
       <?php else: ?>
        <?= htmlspecialchars($game->developer) ?>
       <?php endif ?>
      </td>
      <td><?= htmlspecialchars($game->suggestedAge) ?></td>
      <td><?= htmlspecialchars(implode(', ', $game->players)) ?></td>
      <td><?= htmlspecialchars(implode(', ', $game->genres)) ?></td>
      <td><?= htmlspecialchars(gmdate('Y-m-d', $game->apkTimestamp)) ?></td>
     </tr>
    <?php endforeach; ?>
   </tbody>
  </table>

  <nav>
   <?php foreach ($navLinks as $url => $title): ?>
    <a rel="up" href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($title) ?></a>
   <?php endforeach ?>
  </nav>

  <script type="text/javascript" src="../datatables/jquery-3.5.1.min.js"></script>
  <script type="text/javascript" src="../datatables/datatables.min.js"></script>
  <script type="text/javascript" src="../datatables/jquery.dataTables.yadcf.js"></script>
  <script type="text/javascript">
   $(document).ready(
       function() {
           var allOuyaGamesTable = $('#allouyagames').DataTable(
               {
                   paging: false,
                   fixedHeader: true
               }
           );
           yadcf.init(
               allOuyaGamesTable,
               [
                   {
                       column_number: 0,
                       filter_type: "text",
                       filter_default_label: "Filter game title",
                   },
                   {
                       column_number: 1,
                       filter_type: "text",
                       filter_default_label: "Filter developer",
                   },
                   {
                       column_number: 2,
                       column_data_type: "text",
                       data: ["Everyone", "9+", "12+", "17+"],
                       filter_default_label: "Filter age"
                   },
                   {
                       column_number: 3,
                       column_data_type: "text",
                       text_data_delimiter: ", ",
                       filter_default_label: "Filter player number"
                   },
                   {
                       column_number: 4,
                       column_data_type: "text",
                       text_data_delimiter: ", ",
                       filter_default_label: "Filter genre"
                   }
               ]
           );
       }
   );
  </script>
 </body>
</html>
