/**
 * MODX 2 snippet: PostEngagement.
 * Paste into Manager without a PHP opening tag.
 */
$mode = isset($mode) ? (string)$mode : 'article';
if ($mode === 'list-ssr') {
    // Fast server-rendered lists: zero queries per card, no JavaScript or cookie.
    // Wrap the resulting complete HTML in PostEngagementHydrate (one batched SQL query).
    $listId = isset($id) && (int)$id > 0 ? (int)$id : (int)$modx->resource->get('id');
    if ($listId < 1 || $listId > 2147483647) return '';
    $listAssets = rtrim($modx->getOption('assets_url'), '/') . '/components/modx-count-like/';
    $modx->regClientCSS($listAssets . 'engagement.css');
    return '<span class="mcl-list-views" aria-label="Просмотры">'
        . '<svg class="mcl-eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg> <span>__MCL_VIEWS_' . $listId . '__</span></span>';
}
if ($mode === 'list') {
    // Do not query MySQL or create cookies per card. list.js batches IDs on DOM ready.
    $listId = isset($id) && (int)$id > 0 ? (int)$id : (int)$modx->resource->get('id');
    if ($listId < 1) return '';
    $listAssets = rtrim($modx->getOption('assets_url'), '/') . '/components/modx-count-like/';
    $modx->regClientCSS($listAssets . 'engagement.css');
    $modx->regClientScript($listAssets . 'list.js');
    $listApi = htmlspecialchars($listAssets . 'api.php', ENT_QUOTES, 'UTF-8');
    return '<span class="mcl-list-views" data-mcl-list-id="' . $listId . '" data-mcl-list-api="' . $listApi
        . '" aria-label="Просмотры"><svg class="mcl-eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg> <span data-mcl-list-count>0</span></span>';
}
if ($mode !== 'article') return '';

$id = isset($id) && (int)$id > 0 ? (int)$id : (int)$modx->resource->get('id');
$showDate = !isset($showDate) || (string)$showDate !== '0';
$showViews = !isset($showViews) || (string)$showViews !== '0';
$showLikes = !isset($showLikes) || (string)$showLikes !== '0';
$trackViews = !isset($trackViews) || (string)$trackViews !== '0';

if ($id < 1 || (!$showDate && !$showViews && !$showLikes)) {
    return '';
}
$resource = $modx->getObject('modResource', $id);
if (!$resource || !(int)$resource->get('published') || (int)$resource->get('deleted')) {
    return '';
}

$assetsUrl = rtrim($modx->getOption('assets_url'), '/') . '/components/modx-count-like/';
$modx->regClientCSS($assetsUrl . 'engagement.css');
$modx->regClientScript($assetsUrl . 'engagement.js');

$views = 0;
$likes = 0;
try {
    $prefix = $modx->getOption('table_prefix');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        return '';
    }
    $sql = 'SELECT views, likes FROM `' . $prefix . 'mcl_stats` WHERE resource_id = :id';
    $stmt = $modx->prepare($sql);
    if ($stmt && $stmt->execute(array(':id' => $id))) {
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $views = (int)$data['views'];
            $likes = (int)$data['likes'];
        }
    }
} catch (Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[PostEngagement] Database query failed: ' . $e->getMessage());
}

$esc = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$attrs = ' data-mcl-id="' . $id . '" data-mcl-track="' . ($trackViews ? '1' : '0') . '" data-mcl-api="' . $esc($assetsUrl . 'api.php') . '"';
$out = '<div class="mcl" ' . trim($attrs) . '>';
if ($showDate) {
    // In MODX 2 get('publishedon') may return a formatted date string, not a Unix timestamp.
    $rawPublished = $resource->get('publishedon');
    if (is_numeric($rawPublished)) {
        $published = (int)$rawPublished;
    } else {
        $parsed = strtotime((string)$rawPublished);
        $published = ($parsed === false) ? 0 : $parsed;
    }
    if ($published > 0) {
        $out .= '<time class="mcl-date" datetime="' . date('Y-m-d', $published) . '">' . date('d.m.Y', $published) . '</time>';
    }
}
if ($showViews) {
    $out .= '<span class="mcl-views" aria-label="Просмотры"><svg class="mcl-eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg> <span data-mcl-views>' . $views . '</span></span>';
}
if ($showLikes) {
    $out .= '<button type="button" class="mcl-like" data-mcl-like aria-pressed="false" aria-label="Нравится"><span aria-hidden="true">♥</span> <span data-mcl-likes>' . $likes . '</span></button>';
}
return $out . '</div>';
