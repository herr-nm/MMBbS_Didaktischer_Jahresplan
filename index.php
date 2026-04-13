<?php
$jsonFile = 'didakt_data.json';
$data = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : ['classes' => []];

$selId = $_GET['c'] ?? (!empty($data['classes']) ? array_key_first($data['classes']) : null);
$class = ($selId && isset($data['classes'][$selId])) ? $data['classes'][$selId] : null;

function getSorted($subjects) {
    $b = []; $u = []; if(!$subjects) return [];
    foreach ($subjects as $k => $s) { 
        $s['key'] = $k; 
        if (($s['category']??'')==='bezogen' || strpos($k,'LF')===0) $b[]=$s; 
        else $u[]=$s; 
    }
    usort($b, function($x,$y){return strnatcasecmp($x['key'],$y['key']);});
    usort($u, function($x,$y){return strcasecmp($x['name'],$y['name']);});
    return ['Berufsbezogener Lernbereich' => $b, 'Berufsübergreifend' => $u];
}

function stackPlanning($planning) {
    if (!$planning) return [];
    usort($planning, function($a, $b) { return $a['start'] <=> $b['start']; });
    $rows = [];
    foreach ($planning as $ls) {
        $placed = false;
        foreach ($rows as &$row) {
            $overlap = false;
            foreach ($row as $existing) {
                if (!($ls['end'] < $existing['start'] || $ls['start'] > $existing['end'])) {
                    $overlap = true; break;
                }
            }
            if (!$overlap) { $row[] = $ls; $placed = true; break; }
        }
        if (!$placed) { $rows[] = [$ls]; }
    }
    return $rows;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Jahresplanung - MMBbS</title>
    <style>
        :root {
            --primary-blue: #14508c;
            --border-light: #e5e7eb;
            --border-dark: #d1d5db;
            --bg-sidebar: #f9fafb;
        }

        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header-banner {
            background: #fff;
            padding: 10px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .logo-area img { height: 50px; display: block; }
        .nav-area { display: flex; gap: 15px; align-items: center; }

        .container { padding: 30px 40px; flex: 1; }

        .grid { 
            display: grid; 
            grid-template-columns: 280px repeat(13, 1fr); 
            background: #fff;
            border-right: 1px solid var(--border-dark);
            border-bottom: 1px solid var(--border-dark);
            gap: 0; 
            border-radius: 4px;
            overflow: hidden;
        }

        .cell { 
            background: #fff; 
            padding: 10px; 
            min-height: 50px; 
            position: relative;
            border-top: 1px solid var(--border-light);
            border-left: 1px solid var(--border-light);
            box-sizing: border-box;
        }

        .header-cell { 
            background: var(--primary-blue); 
            color: white; 
            text-align: center; 
            font-weight: 600; 
            font-size: 13px;
            border-top: none;
            padding: 12px 5px;
        }

        .sidebar-cell.merged {
            background: var(--bg-sidebar);
            font-size: 13px;
            border-left: 1px solid var(--border-dark);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            z-index: 5;
        }

        .cat-row { 
            grid-column: span 14; 
            background: #eceef1; 
            padding: 10px 15px; 
            font-weight: bold; 
            font-size: 12px; 
            text-transform: uppercase; 
            color: #4b5563;
            border-top: 2px solid var(--border-dark);
            border-left: 1px solid var(--border-dark);
        }

        .ls-bar {
            position: absolute;
            top: 8px;
            height: 34px;
            border-radius: 4px;
            font-size: 11px;
            padding: 6px 10px;
            box-sizing: border-box;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 10;
            border: 1px solid rgba(0,0,0,0.1);
            transition: all 0.2s;
        }

        .ls-bar.has-link:hover {
            filter: brightness(0.9);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
        }

        .ls-bar.has-link::after {
            content: '🔗';
            float: right;
            margin-left: 5px;
            font-size: 10px;
            opacity: 0.7;
        }

        footer {
            background: #fff;
            border-top: 1px solid var(--border-dark);
            padding: 20px 40px;
            margin-top: 40px;
        }
        .footer-content { display: flex; justify-content: space-between; color: #6b7280; font-size: 13px; }
        .footer-link { color: var(--primary-blue); text-decoration: none; font-weight: bold; }

        select { padding: 8px; border-radius: 4px; border: 1px solid var(--border-dark); }
        .btn-admin { text-decoration: none; background: #34495e; color: white; padding: 8px 16px; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>

<header class="header-banner">
    <div class="logo-area"><img src="logo.png" alt="Logo" onerror="this.style.visibility='hidden'"></div>
    <div class="nav-area">
        <form method="GET">
            <select name="c" onchange="this.form.submit()">
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
        <h1 style="margin-top:0; font-size: 1.5rem; color: #111827;">Didaktische Jahresplanung: <?= htmlspecialchars($class['name']) ?></h1>
        
        <div class="grid">
            <div class="cell header-cell" style="border-left: 1px solid var(--border-dark);">Fächer / Lernfelder</div>
            <?php for($i=1; $i<=13; $i++) echo "<div class='cell header-cell'>Woche $i</div>"; ?>

            <?php foreach($sorted as $catName => $subs): if(empty($subs)) continue; ?>
                <div class="cat-row"><?= $catName ?></div>
                
                <?php foreach($subs as $s): 
                    $stackedRows = stackPlanning($s['planning'] ?? []);
                    $rowCount = max(1, count($stackedRows)); 
                ?>
                    <div class="cell sidebar-cell merged" style="grid-row: span <?= $rowCount ?>; border-left: 1px solid var(--border-dark);">
                        <strong><?= htmlspecialchars($s['key']) ?></strong>
                        <small style="margin-top:5px; color:#4b5563; font-weight:normal;"><?= htmlspecialchars($s['name']) ?> (<?= $s['total_hours'] ?>h)</small>
                    </div>

                    <?php for($r=0; $r < $rowCount; $r++): ?>
                        <?php for($w=1; $w<=13; $w++): ?>
                            <div class="cell">
                                <?php 
                                if(isset($stackedRows[$r])):
                                    foreach($stackedRows[$r] as $p):
                                        if($p['start'] == $w): 
                                            $numWeeks = ($p['end'] - $p['start'] + 1);
                                            $barWidth = "calc(" . ($numWeeks * 100) . "% + " . ($numWeeks - 1) . "px - 14px)";
                                            $hasUrl = !empty($p['url']);
                                            $linkClass = $hasUrl ? 'has-link' : '';
                                ?>
                                            <?php if($hasUrl): ?>
                                                <a href="<?= htmlspecialchars($p['url']) ?>" target="_blank" style="text-decoration: none;">
                                            <?php endif; ?>

                                                <div class="ls-bar <?= $linkClass ?>" style="background:<?= $p['color'] ?>; width:<?= $barWidth ?>; left: 7px; cursor: <?= $hasUrl ? 'pointer' : 'default' ?>;" title="<?= htmlspecialchars($p['title']) . ($hasUrl ? ' (Klicken zum Öffnen)' : '') ?>">
                                                    <span style="font-weight:800"><?= htmlspecialchars($p['ls_nr']) ?>:</span> <?= htmlspecialchars($p['title']) ?>
                                                </div>

                                            <?php if($hasUrl): ?>
                                                </a>
                                            <?php endif; ?>
                                <?php 
                                        endif; 
                                    endforeach;
                                endif; 
                                ?>
                            </div>
                        <?php endfor; ?>
                    <?php endfor; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer>
    <div class="footer-content">
        <div>
            <h4>Lizenz</h4>
            © <?= date('Y') ?> MMBbS Hannover | <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" class="footer-link">GNU AGPL v3</a>
        </div>
        <div style="text-align: right;">
            <h4>Kontakt</h4>
            <a href="mailto:info@mmbbs.de" class="footer-link">info@mmbbs.de</a><br>
            <a href="https://www.mmbbs.de" target="_blank" class="footer-link">www.mmbbs.de</a>
        </div>
    </div>
</footer>

</body>
</html>