<?php
$jsonFile = 'didakt_data.json';
$data = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : ['classes' => []];
$selId = $_GET['c'] ?? (!empty($data['classes']) ? array_key_first($data['classes']) : null);
$class = ($selId && isset($data['classes'][$selId])) ? $data['classes'][$selId] : null;

function getSorted($subjects) {
    $b = []; $u = []; if(!$subjects) return [];
    foreach ($subjects as $k => $s) { $s['key'] = $k; if (($s['category']??'')==='bezogen' || strpos($k,'LF')===0) $b[]=$s; else $u[]=$s; }
    usort($b, function($x,$y){return strnatcasecmp($x['key'],$y['key']);});
    usort($u, function($x,$y){return strcasecmp($x['name'],$y['name']);});
    return ['Berufsbezogener Lernbereich' => $b, 'Berufsübergreifender Lernbereich' => $u];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Jahresplanung - MMBbS</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; margin: 0; color: #1f2937; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Header Banner */
        .header-banner {
            background: #fff;
            padding: 10px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-bottom: 1px solid #d1d5db;
        }
        .logo-area img { height: 50px; display: block; }
        .nav-area { display: flex; gap: 15px; align-items: center; }

        .container { padding: 30px 40px; flex: 1; }
        
        /* Grid Styles */
        .grid { display: grid; grid-template-columns: 280px repeat(13, 1fr); background: #fff; border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; gap: 1px; }
        .cell { background: #fff; padding: 12px; border: 0.5px solid #e5e7eb; min-height: 45px; position: relative; }
        .header-cell { background: #14508c; color: white; text-align: center; font-weight: 600; font-size: 13px; }
        .sidebar-cell { background: #f9fafb; font-size: 13px; border-right: 2px solid #d1d5db; }
        .cat-row { grid-column: span 14; background: #e5e7eb; padding: 8px 15px; font-weight: bold; font-size: 12px; text-transform: uppercase; color: #4b5563; }
        
        .ls-bar {
            position: absolute;
            top: 6px;
            height: 32px;
            border-radius: 4px;
            font-size: 11px;
            padding: 4px 8px;
            box-sizing: border-box;
            color: #111827;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 10;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        select { padding: 8px 12px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 14px; background: #fff; }
        .btn-admin { text-decoration: none; background: #34495e; color: white; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; }

        /* Footer Styles */
        footer {
            background: #fff;
            border-top: 1px solid #d1d5db;
            padding: 20px 40px;
            margin-top: 40px;
            color: #6b7280;
            font-size: 13px;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            max-width: 100%;
        }
        .footer-section h4 {
            margin: 0 0 8px 0;
            color: #374151;
            font-size: 14px;
            text-transform: uppercase;
        }
        .footer-link { color: #14508c; text-decoration: none; }
        .footer-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header class="header-banner">
    <div class="logo-area">
        <img src="logo.png" alt="Logo">
    </div>
    
    <div class="nav-area">
        <form method="GET" style="margin:0;">
            <select name="c" onchange="this.form.submit()">
                <?php if(empty($data['classes'])): ?><option>Keine Klassen</option><?php endif; ?>
                <?php foreach($data['classes'] as $id => $c): ?>
                    <option value="<?= $id ?>" <?= $selId == $id ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="admin.php" class="btn-admin">Admin-Bereich</a>
    </div>
</header>

<div class="container">
    <?php if($class): $sorted = getSorted($class['subjects']); ?>
        <h1 style="margin-top:0; font-size: 1.5rem;">Didaktische Jahresplanung: <?= htmlspecialchars($class['name']) ?></h1>
        
        <div class="grid">
            <div class="cell header-cell">Lernbereiche / Fächer</div>
            <?php for($i=1; $i<=13; $i++) echo "<div class='cell header-cell'>Block $i</div>"; ?>

            <?php foreach($sorted as $catName => $subs): if(empty($subs)) continue; ?>
                <div class="cat-row"><?= $catName ?></div>
                <?php foreach($subs as $s): ?>
                    <div class="cell sidebar-cell">
                        <strong><?= $s['key'] ?></strong><br>
                        <small><?= htmlspecialchars($s['name']) ?> (<?= $s['total_hours'] ?>h)</small>
                    </div>
                    <?php for($w=1;$w<=13;$w++): ?>
                        <div class="cell">
                            <?php foreach(($s['planning']??[]) as $p): 
                                if($p['start']==$w): 
                                    $numWeeks = ($p['end'] - $p['start'] + 1);
                                    $barWidth = "calc(" . ($numWeeks * 100) . "% + " . ($numWeeks - 1) . "px - 10px)";
                            ?>
                                <div class="ls-bar" style="background:<?= $p['color'] ?>; width:<?= $barWidth ?>; left: 5px;" title="<?= htmlspecialchars($p['title']) ?>">
                                    <b><?= htmlspecialchars($p['ls_nr']) ?>:</b> <?= htmlspecialchars($p['title']) ?>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endfor; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:80px; background:white; border-radius:12px; border:1px solid #d1d5db;">
            <h2>Willkommen zur didaktischen Jahresplanung</h2>
            <p>Es sind noch keine Planungsdaten vorhanden.</p>
        </div>
    <?php endif; ?>
</div>

<footer>
    <div class="footer-content">
    <div class="footer-section">
        <h4>Lizenz</h4>
        <p>© <?= date('Y') ?> Multi-Media-Berufsbildende Schulen Hannover (MMBbS)</p>
        <p>
            Diese Software ist freie Software unter der 
            <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" class="footer-link" style="font-weight: bold;">GNU AGPL v3</a>.
            <br>
            <small>Der Quellcode ist im <a href="https://github.com/herr-nm/MMBbS_Didaktischer_Jahresplan" target=new>Repository</a> verfügbar.</small>
        </p>
    </div>
    <div class="footer-section" style="text-align: right;">
        <h4>Kontakt</h4>
        <p>E-Mail: <a href="mailto:info@mmbbs.de" class="footer-link">info@mmbbs.de</a><br>
        Web: <a href="https://www.mmbbs.de" target="_blank" class="footer-link">www.mmbbs.de</a></p>
    </div>
</div>
</footer>

</body>
</html>