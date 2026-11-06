/**
 * MODX 2 snippet: PostEngagement.
 * Paste into Manager without a PHP opening tag.
 */
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
    $published = (int)$resource->get('publishedon');
    if ($published > 0) {
        $out .= '<time class="mcl-date" datetime="' . date('Y-m-d', $published) . '">' . date('d.m.Y', $published) . '</time>';
    }
}
if ($showViews) {
    $out .= '<span class="mcl-views" aria-label="Просмотры"><span aria-hidden="true">◉</span> <span data-mcl-views>' . $views . '</span></span>';
}
if ($showLikes) {
    $out .= '<button type="button" class="mcl-like" data-mcl-like aria-pressed="false" aria-label="Нравится"><span aria-hidden="true">♥</span> <span data-mcl-likes>' . $likes . '</span></button>';
}
return $out . '</div>';
