/**
 * MODX 2 snippet: PostEngagementHydrate
 *
 * Replaces __MCL_VIEWS_<resource id>__ markers in already rendered listing HTML.
 * One parameterized SELECT per 500 unique IDs; no public API, cookies or JS.
 * Paste into a Manager snippet without an opening PHP tag.
 */
$html = isset($html) ? (string)$html : '';
if ($html === '' || strpos($html, '__MCL_VIEWS_') === false) {
    return $html;
}

if (!preg_match_all('/__MCL_VIEWS_([1-9][0-9]{0,9})__/', $html, $matches, PREG_SET_ORDER)) {
    return $html;
}

$replacements = array();
$ids = array();
foreach ($matches as $match) {
    // Always replace valid-shaped markers, including invalid/oversized IDs, with zero.
    $replacements[$match[0]] = '0';
    $resourceId = (int)$match[1];
    if ($resourceId > 0 && $resourceId <= 2147483647) {
        $ids[$resourceId] = $resourceId;
    }
}
if (!$ids) {
    return strtr($html, $replacements);
}

try {
    $prefix = (string)$modx->getOption('table_prefix');
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
        throw new RuntimeException('Invalid table prefix');
    }
    if (!$modx->connect() || !($modx->pdo instanceof PDO)) {
        throw new RuntimeException('MODX PDO connection unavailable');
    }
    $pdo = $modx->pdo;
    foreach (array_chunk(array_values($ids), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $statement = $pdo->prepare('SELECT resource_id, views FROM `'
            . $prefix . 'mcl_stats` WHERE resource_id IN (' . $placeholders . ')');
        if (!$statement || !$statement->execute($chunk)) {
            throw new RuntimeException('Cannot read view counters');
        }
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $resourceId = (int)$row['resource_id'];
            $replacements['__MCL_VIEWS_' . $resourceId . '__'] = (string)max(0, (int)$row['views']);
        }
    }
} catch (Throwable $e) {
    // Keep the listing visible if the stats table is temporarily unavailable.
    $modx->log(modX::LOG_LEVEL_ERROR, '[PostEngagementHydrate] ' . $e->getMessage());
}

return strtr($html, $replacements);
